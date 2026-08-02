<?php
/**
 * Workflow run repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\WorkflowRun;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_workflow_runs` access goes through this class.
 */
class WorkflowRunRepository {

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Defensive upper bound on how many runs a single retention-pruning
	 * pass (cron tick or manual "Purge now" click) deletes. A site with a
	 * larger backlog than this is caught up over several daily cron ticks
	 * rather than one query attempting to delete an unbounded number of
	 * rows in one request — same reasoning as BackgroundRunner::BATCH_SIZE.
	 */
	private const MAX_PRUNE_BATCH = 5000;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'workflow_runs' ) );
	}

	/**
	 * Starts a new run row, either to execute immediately (status
	 * `running`, the synchronous path from `WorkflowExecutionService::run()`)
	 * or to be picked up later by `claimBatch()` (status `queued`, the
	 * background path from `queue()` and from retry scheduling).
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type int                    $workflow_id      Required.
	 *     @type int|null               $parent_run_id    Optional, set on retry rows to link back to the attempt that spawned them.
	 *     @type string                 $status           One of WorkflowRun::VALID_STATUSES. Default `queued`.
	 *     @type array<string,mixed>    $trigger_payload  Optional. Persisted as JSON so a background-claimed run can rebuild its execution context.
	 *     @type int                    $attempts         Optional, 1-indexed. Default 1.
	 *     @type string                 $next_attempt_at  Optional MySQL datetime (GMT). When set, `claimBatch()` will not claim the row until this time.
	 * }
	 *
	 * @return WorkflowRun|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?WorkflowRun {
		global $wpdb;

		$status = (string) ( $attributes['status'] ?? WorkflowRun::STATUS_QUEUED );
		$now    = current_time( 'mysql', true );

		$data = array(
			'workflow_id'          => (int) $attributes['workflow_id'],
			'parent_run_id'        => isset( $attributes['parent_run_id'] ) ? (int) $attributes['parent_run_id'] : null,
			'status'               => $status,
			'trigger_payload_json' => ! empty( $attributes['trigger_payload'] ) ? wp_json_encode( $attributes['trigger_payload'] ) : null,
			'attempts'             => isset( $attributes['attempts'] ) ? max( 1, (int) $attributes['attempts'] ) : 1,
			'next_attempt_at'      => isset( $attributes['next_attempt_at'] ) ? (string) $attributes['next_attempt_at'] : null,
			// A queued row has not started yet; its clock starts when
			// claimBatch() claims it. A row created as `running` (the
			// synchronous path) starts immediately.
			'started_at'           => WorkflowRun::STATUS_RUNNING === $status ? $now : null,
			'created_at'           => $now,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Reports whether this workflow already has a `queued` or `running` run
	 * whose trigger payload contains the given JSON fragment. Lets callers
	 * collapse duplicate firings of one real-world event (WordPress emits
	 * `save_post` more than once for a single editor "Update") into one run
	 * instead of a pile-up that repeats every third-party API call.
	 *
	 * @param int    $workflow_id    Workflow id.
	 * @param string $payload_needle Raw JSON fragment to match, e.g. `"post_id":70`.
	 *
	 * @return bool
	 */
	public function hasPendingRunMatchingPayload( int $workflow_id, string $payload_needle ): bool {
		global $wpdb;

		if ( '' === $payload_needle ) {
			return false;
		}

		$table = $this->table();
		$like  = '%' . $wpdb->esc_like( $payload_needle ) . '%';

		$found = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
				"SELECT id FROM {$table}
					WHERE workflow_id = %d
						AND status IN ( %s, %s )
						AND trigger_payload_json LIKE %s
					LIMIT 1",
				$workflow_id,
				WorkflowRun::STATUS_QUEUED,
				WorkflowRun::STATUS_RUNNING,
				$like
			)
		);

		return null !== $found;
	}

	/**
	 * Atomically claims up to `$limit` runs that are ready to execute, and
	 * returns them. "Ready" means either a fresh `queued` row whose
	 * `next_attempt_at` (if any) has passed, or a `running` row that has
	 * been running for longer than `$stale_after_minutes` — recovered on
	 * the assumption its worker crashed or was killed by a request
	 * timeout before it could call finish() (see BackgroundRunner). A
	 * reclaimed stale row is re-executed from the beginning; there is no
	 * per-node resume support, so this can duplicate already-succeeded
	 * side effects in that specific failure scenario — a known limitation,
	 * not a bug, of a synchronous, non-idempotent node execution model.
	 *
	 * Race safety: this uses a single atomic `UPDATE ... ORDER BY id LIMIT`
	 * statement (native MySQL syntax) to flip matching rows to `running`
	 * and stamp them with a fresh, unique `claim_token`. Because it is one
	 * statement, two overlapping cron invocations cannot both claim the
	 * same row — MySQL's row locking during the UPDATE serializes them,
	 * and the loser's WHERE clause will no longer match. The immediately
	 * following SELECT filters on that same token, so each invocation only
	 * ever sees the exact rows it just claimed itself.
	 *
	 * @param int $limit               Maximum number of runs to claim.
	 * @param int $stale_after_minutes Minutes after which a `running` row is considered abandoned and eligible for reclaim.
	 *
	 * @return WorkflowRun[]
	 */
	public function claimBatch( int $limit, int $stale_after_minutes ): array {
		global $wpdb;

		$table        = $this->table();
		$now          = current_time( 'mysql', true );
		$claim_token  = wp_generate_uuid4();
		$stale_before = gmdate( 'Y-m-d H:i:s', time() - ( $stale_after_minutes * MINUTE_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
				"UPDATE {$table}
					SET status = %s, claim_token = %s, started_at = %s
					WHERE
						( status = %s AND ( next_attempt_at IS NULL OR next_attempt_at <= %s ) )
						OR ( status = %s AND started_at IS NOT NULL AND started_at <= %s )
					ORDER BY id ASC
					LIMIT %d",
				WorkflowRun::STATUS_RUNNING,
				$claim_token,
				$now,
				WorkflowRun::STATUS_QUEUED,
				$now,
				WorkflowRun::STATUS_RUNNING,
				$stale_before,
				$limit
			)
		);

		if ( 0 === (int) $wpdb->rows_affected ) {
			return array();
		}

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE claim_token = %s ORDER BY id ASC", $claim_token )
		);

		return array_map( array( WorkflowRun::class, 'fromRow' ), $rows );
	}

	/**
	 * Reverts a claimed row back to `queued` without touching its retry
	 * bookkeeping, so the next cron tick can pick it up again immediately
	 * instead of waiting for stale-claim recovery. Used by BackgroundRunner
	 * when it runs out of time budget partway through a claimed batch.
	 *
	 * @param int $id Run id.
	 *
	 * @return bool
	 */
	public function requeue( int $id ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'      => WorkflowRun::STATUS_QUEUED,
				'claim_token' => null,
				'started_at'  => null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Marks a run as finished with a terminal status.
	 *
	 * @param int    $id     Run id.
	 * @param string $status One of WorkflowRun::VALID_STATUSES.
	 *
	 * @return WorkflowRun|null Null if the run does not exist or the update failed.
	 */
	public function finish( int $id, string $status ): ?WorkflowRun {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'      => $status,
				'finished_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return null;
		}

		return $this->find( $id );
	}

	/**
	 * Finds a single run by id.
	 *
	 * @param int $id Run id.
	 *
	 * @return WorkflowRun|null
	 */
	public function find( int $id ): ?WorkflowRun {
		global $wpdb;

		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is not user input.

		return $row ? WorkflowRun::fromRow( $row ) : null;
	}

	/**
	 * Returns a paginated list of runs for a single workflow, most recent
	 * first. Intended for the run history UI (a later roadmap item); the
	 * execution engine itself only ever needs insert()/finish()/find().
	 *
	 * @param int                  $workflow_id Workflow id.
	 * @param array<string, mixed> $args        {
	 *     @type int $page     1-indexed page number. Default 1.
	 *     @type int $per_page Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: WorkflowRun[], total: int, page: int, per_page: int}
	 */
	public function paginateForWorkflow( int $workflow_id, array $args = array() ): array {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = $this->table();

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE workflow_id = %d", $workflow_id )
		);

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $workflow_id, $per_page, $offset )
		);

		return array(
			'items'    => array_map( array( WorkflowRun::class, 'fromRow' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns a paginated, optionally filtered list of runs across every
	 * workflow, most recent first. Backs the top-level "Runs" history
	 * screen (roadmap item 9); `paginateForWorkflow()` above remains for
	 * any future single-workflow-scoped view.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int    $workflow_id Optional. Restrict to a single workflow.
	 *     @type string $status      Optional. One of WorkflowRun::VALID_STATUSES.
	 *     @type int    $page        1-indexed page number. Default 1.
	 *     @type int    $per_page    Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: WorkflowRun[], total: int, page: int, per_page: int}
	 */
	public function paginateAll( array $args = array() ): array {
		global $wpdb;

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		// `id > %d` is always true (id is an AUTO_INCREMENT primary key
		// starting at 1); it guarantees $where/$params are never empty so
		// every query below can go through $wpdb->prepare() unconditionally,
		// instead of branching between a prepared and an unprepared call.
		$where  = array( 'id > %d' );
		$params = array( 0 );

		if ( ! empty( $args['workflow_id'] ) ) {
			$where[]  = 'workflow_id = %d';
			$params[] = (int) $args['workflow_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$table     = $this->table();

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+ and $where_sql is escaped
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params )
		);

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber --- $table is escaped and %i placeholder is support wp 6.2+ and $where_sql is escaped
			$wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", $list_params )
		);

		return array(
			'items'    => array_map( array( WorkflowRun::class, 'fromRow' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns every run id belonging to a workflow, so callers can cascade
	 * into `aiawa_workflow_run_logs` (which is keyed by run id, not workflow
	 * id) before removing the runs themselves.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return int[]
	 */
	public function idsForWorkflow( int $workflow_id ): array {
		global $wpdb;

		$table = $this->table();

		return array_map(
			'intval',
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE workflow_id = %d", $workflow_id ) )
		);
	}

	/**
	 * Returns up to MAX_PRUNE_BATCH ids of *finished* runs older than a
	 * cutoff, for RunRetentionService. Deliberately scoped to
	 * `finished_at` (not `created_at`): retention is about how long to
	 * keep completed history, not about how old a row is — a `queued`/
	 * `running` row is never eligible here regardless of age (a stuck one
	 * is BackgroundRunner::claimBatch()'s stale-claim recovery's problem
	 * to solve, not retention's).
	 *
	 * @param string $cutoff_gmt MySQL datetime (GMT). Runs finished strictly before this are eligible.
	 *
	 * @return int[]
	 */
	public function idsFinishedBefore( string $cutoff_gmt ): array {
		global $wpdb;

		$table = $this->table();

		return array_map(
			'intval',
			$wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
				$wpdb->prepare( "SELECT id FROM {$table} WHERE finished_at IS NOT NULL AND finished_at < %s ORDER BY id ASC LIMIT %d", $cutoff_gmt, self::MAX_PRUNE_BATCH )
			)
		);
	}

	/**
	 * Permanently removes the given runs. Callers must remove dependent
	 * `aiawa_workflow_run_logs` rows first (see `WorkflowRunLogRepository::deleteByRunIds()`),
	 * same cascade-ordering requirement as `deleteByWorkflow()`.
	 *
	 * @param int[] $ids Run ids.
	 *
	 * @return int Number of rows deleted.
	 */
	public function deleteByIds( array $ids ): int {
		global $wpdb;

		if ( array() === $ids ) {
			return 0;
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$table        = $this->table();

		$deleted = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+ and $placeholders is escaped
			$wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids )
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Permanently removes every run belonging to a workflow. Used by
	 * WorkflowService::delete() when hard-deleting a workflow. Callers must
	 * remove dependent `aiawa_workflow_run_logs` rows first (see
	 * idsForWorkflow()).
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return bool
	 */
	public function deleteByWorkflow( int $workflow_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'workflow_id' => $workflow_id ), array( '%d' ) );

		return false !== $deleted;
	}
}
