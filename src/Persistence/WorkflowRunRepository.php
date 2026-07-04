<?php
/**
 * Workflow run repository.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Persistence;

use WorkflowAutomate\Plugin\Database\Table;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `wfa_workflow_runs` access goes through this class.
 */
class WorkflowRunRepository {

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * @return string
	 */
	private function table(): string {
		return Table::name( 'workflow_runs' );
	}

	/**
	 * Starts a new run row. Callers are expected to pass a status of
	 * WorkflowRun::STATUS_RUNNING immediately, since this engine is
	 * synchronous (there is no separate "queued" phase yet — see
	 * WorkflowExecutionService).
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type int      $workflow_id    Required.
	 *     @type int|null $parent_run_id  Optional, for re-runs.
	 *     @type string   $status         One of WorkflowRun::VALID_STATUSES.
	 * }
	 *
	 * @return WorkflowRun|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?WorkflowRun {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$data = array(
			'workflow_id' => (int) $attributes['workflow_id'],
			'parent_run_id' => isset( $attributes['parent_run_id'] ) ? (int) $attributes['parent_run_id'] : null,
			'status' => (string) ( $attributes['status'] ?? WorkflowRun::STATUS_QUEUED ),
			'started_at' => $now,
			'created_at' => $now,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
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
				'status' => $status,
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
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.

		return $row ? WorkflowRun::fromRow( $row ) : null;
	}

	/**
	 * Returns a paginated list of runs for a single workflow, most recent
	 * first. Intended for the run history UI (a later roadmap item); the
	 * execution engine itself only ever needs insert()/finish()/find().
	 *
	 * @param int                   $workflow_id Workflow id.
	 * @param array<string, mixed>  $args        {
	 *     @type int $page     1-indexed page number. Default 1.
	 *     @type int $per_page Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: WorkflowRun[], total: int, page: int, per_page: int}
	 */
	public function paginateForWorkflow( int $workflow_id, array $args = array() ): array {
		global $wpdb;

		$page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset = ( $page - 1 ) * $per_page;

		$table = $this->table();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE workflow_id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $workflow_id ) );

		$list_sql = "SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $workflow_id, $per_page, $offset ) );

		return array(
			'items' => array_map( array( WorkflowRun::class, 'fromRow' ), $rows ),
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns every run id belonging to a workflow, so callers can cascade
	 * into `wfa_workflow_run_logs` (which is keyed by run id, not workflow
	 * id) before removing the runs themselves.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return int[]
	 */
	public function idsForWorkflow( int $workflow_id ): array {
		global $wpdb;

		$table = $this->table();
		$sql = "SELECT id FROM {$table} WHERE workflow_id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $workflow_id ) ) );
	}

	/**
	 * Permanently removes every run belonging to a workflow. Used by
	 * WorkflowService::delete() when hard-deleting a workflow. Callers must
	 * remove dependent `wfa_workflow_run_logs` rows first (see
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
