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

	use CachesRepositoryRows;

	private const CACHE_GROUP = 'aiawa_workflows';

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

		$this->invalidateCache( $id );

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

		$this->invalidateCache( $id );

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

		$this->invalidateCache( $id );

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

		$this->invalidateCache( $id );

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

		$cache_key = $this->cacheKey( $id, $include_trashed );
		$cached    = $this->cacheGet( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql($this->table());

		if ( $include_trashed ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ) );
		}

		$workflow = $row ? Workflow::fromRow( $row ) : null;

		if ( null !== $workflow ) {
			$this->cacheSet( $cache_key, $workflow );
		}

		return $workflow;
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

		// This deliberately includes trashed workflows, i.e. the same data
		// as find( $id, true ), so it shares cache entries with that
		// variant's '_all' keys rather than maintaining a second cache.
		$cache_keys_by_id = array();

		foreach ( $ids as $id ) {
			$cache_keys_by_id[ $id ] = $this->cacheKey( $id, true );
		}

		$cached      = $this->cacheGetMultiple( array_values( $cache_keys_by_id ) );
		$map         = array();
		$missing_ids = array();

		foreach ( $cache_keys_by_id as $id => $cache_key ) {
			if ( isset( $cached[ $cache_key ] ) && false !== $cached[ $cache_key ] ) {
				$map[ $id ] = $cached[ $cache_key ];
			} else {
				$missing_ids[] = $id;
			}
		}

		if ( array() === $missing_ids ) {
			return $map;
		}

		$table        = esc_sql($this->table());
		$placeholders = implode( ', ', array_fill( 0, count( $missing_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $missing_ids )
		);

		foreach ( $rows as $row ) {
			$workflow               = Workflow::fromRow( $row );
			$map[ $workflow->id() ] = $workflow;
			$this->cacheSet( $cache_keys_by_id[ $workflow->id() ], $workflow );
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

		$table   = esc_sql($this->table());
		$updated = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "UPDATE {$table} SET run_count = run_count + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $id )
		);

		$this->invalidateCache( $id );

		return false !== $updated;
	}

	/**
	 * Builds the cache key for a single workflow id, distinguishing the
	 * "active only" and "include trashed" query variants of find() since
	 * they can return different results for the same id.
	 *
	 * @param int  $id              Workflow id.
	 * @param bool $include_trashed Whether the key is for the include-trashed variant.
	 *
	 * @return string
	 */
	private function cacheKey( int $id, bool $include_trashed ): string {
		return $include_trashed ? $id . '_all' : (string) $id;
	}

	/**
	 * Invalidates every cache entry for a workflow id (both find() variants).
	 *
	 * @param int $id Workflow id.
	 *
	 * @return void
	 */
	private function invalidateCache( int $id ): void {
		$this->cacheDelete( $this->cacheKey( $id, false ) );
		$this->cacheDelete( $this->cacheKey( $id, true ) );
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
	 * Intentionally not object-cached: the result depends on an open-ended
	 * combination of filters, page, and per_page, so caching it would need
	 * one cache key per combination, invalidated on nearly every write to
	 * this table — high complexity for little real hit rate. Only find()
	 * and findByIds() cache, since a single id has exactly one cache key.
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
		$table     = esc_sql($this->table());

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_sql is static text and placeholders
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- intentionally uncached: filter+page combinations would need one cache key per combination, invalidated on nearly every write, for little real hit rate. See paginate() docblock.
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+ and $where_sql is escaped
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params )
		);

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_sql is static text and placeholders
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- intentionally uncached, see paginate() docblock.
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