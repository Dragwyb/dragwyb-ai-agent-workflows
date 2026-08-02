<?php
/**
 * Workflow repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\Workflow;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_workflows` access goes through this class. Every query is built
 * with `$wpdb->prepare()` or the `$wpdb` helper methods; the table name
 * itself is never user input, so its direct interpolation into SQL strings
 * is safe.
 */
class WorkflowRepository {

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'workflows' ) );
	}

	/**
	 * Creates a new workflow row.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type string                    $title    Required title.
	 *     @type int                       $status   One of Workflow::VALID_STATUSES.
	 *     @type array<string, mixed>      $graph    Builder graph.
	 *     @type array<string, mixed>|null $settings Per-workflow settings.
	 * }
	 *
	 * @return Workflow|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?Workflow {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$data = array(
			'title'              => (string) $attributes['title'],
			'status'             => (int) ( $attributes['status'] ?? Workflow::STATUS_DRAFT ),
			'definition_version' => (int) ( $attributes['definition_version'] ?? 1 ),
			'graph_json'         => wp_json_encode( $attributes['graph'] ?? array() ),
			'settings_json'      => isset( $attributes['settings'] ) ? wp_json_encode( $attributes['settings'] ) : null,
			'run_count'          => 0,
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		$formats = array( '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id, true );
	}

	/**
	 * Updates an existing workflow row. Only the provided keys are touched.
	 *
	 * @param int                  $id         Workflow id.
	 * @param array<string, mixed> $attributes Any of: title, status, definition_version, graph, settings.
	 *
	 * @return Workflow|null Null if the workflow does not exist or the update failed.
	 */
	public function update( int $id, array $attributes ): ?Workflow {
		global $wpdb;

		$data    = array();
		$formats = array();

		if ( array_key_exists( 'title', $attributes ) ) {
			$data['title'] = (string) $attributes['title'];
			$formats[]     = '%s';
		}

		if ( array_key_exists( 'status', $attributes ) ) {
			$data['status'] = (int) $attributes['status'];
			$formats[]      = '%d';
		}

		if ( array_key_exists( 'definition_version', $attributes ) ) {
			$data['definition_version'] = (int) $attributes['definition_version'];
			$formats[]                  = '%d';
		}

		if ( array_key_exists( 'graph', $attributes ) ) {
			$data['graph_json'] = wp_json_encode( $attributes['graph'] );
			$formats[]          = '%s';
		}

		if ( array_key_exists( 'settings', $attributes ) ) {
			$data['settings_json'] = null === $attributes['settings'] ? null : wp_json_encode( $attributes['settings'] );
			$formats[]             = '%s';
		}

		if ( array() === $data ) {
			return $this->find( $id, true );
		}

		$data['updated_at'] = current_time( 'mysql', true );
		$formats[]          = '%s';

		$updated = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return null;
		}

		return $this->find( $id, true );
	}

	/**
	 * Marks a workflow as trashed without deleting the row.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return bool
	 */
	public function softDelete( int $id ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'deleted_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Clears a workflow's trashed state.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return bool
	 */
	public function restore( int $id ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'deleted_at' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Permanently removes a workflow row. Callers are responsible for
	 * cascading deletion of dependent rows (see WorkflowService::delete()).
	 *
	 * @param int $id Workflow id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Finds a single workflow by id.
	 *
	 * @param int  $id              Workflow id.
	 * @param bool $include_trashed Whether to also match soft-deleted rows.
	 *
	 * @return Workflow|null
	 */
	public function find( int $id, bool $include_trashed = false ): ?Workflow {
		global $wpdb;

		$table = $this->table();

		if ( $include_trashed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ) );
		}

		return $row ? Workflow::fromRow( $row ) : null;
	}

	/**
	 * Batch-fetches multiple workflows by id, keyed by id. Used to resolve
	 * workflow titles for a list of runs (roadmap item 9's history screen)
	 * without an N+1 `find()` per row.
	 *
	 * Deliberately includes trashed workflows (unlike find()'s default): a
	 * run's history shouldn't hide which workflow it belonged to just
	 * because that workflow was later trashed. A hard-deleted workflow (no
	 * row left at all) is simply absent from the returned map; callers
	 * are expected to handle that case explicitly.
	 *
	 * @param int[] $ids Workflow ids.
	 *
	 * @return array<int, Workflow>
	 */
	public function findByIds( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		if ( array() === $ids ) {
			return array();
		}

		$table        = $this->table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids )
		);

		$map = array();

		foreach ( $rows as $row ) {
			$workflow               = Workflow::fromRow( $row );
			$map[ $workflow->id() ] = $workflow;
		}

		return $map;
	}

	/**
	 * Atomically increments `run_count` by one. Uses `run_count = run_count
	 * + 1` at the SQL level rather than a read-then-write round trip so
	 * concurrent runs of the same workflow can never lose an increment.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return bool
	 */
	public function incrementRunCount( int $id ): bool {
		global $wpdb;

		$table   = $this->table();
		$updated = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "UPDATE {$table} SET run_count = run_count + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $id )
		);

		return false !== $updated;
	}

	/**
	 * Returns a paginated, optionally filtered list of workflows.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int  $status          Optional status filter.
	 *     @type bool $include_trashed Whether to include soft-deleted rows alongside active ones. Default false.
	 *     @type bool $only_trashed    Whether to return *only* soft-deleted rows (a dedicated "Trash" view). Takes
	 *                                 precedence over `include_trashed` when true. Default false.
	 *     @type int  $page            1-indexed page number. Default 1.
	 *     @type int  $per_page        Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: Workflow[], total: int, page: int, per_page: int}
	 */
	public function paginate( array $args = array() ): array {
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

		if ( ! empty( $args['only_trashed'] ) ) {
			$where[] = 'deleted_at IS NOT NULL';
		} elseif ( empty( $args['include_trashed'] ) ) {
			$where[] = 'deleted_at IS NULL';
		}

		if ( isset( $args['status'] ) ) {
			$where[]  = 'status = %d';
			$params[] = (int) $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
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
			'items'    => array_map( array( Workflow::class, 'fromRow' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
