<?php
/**
 * Connection repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\Connection;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_connections` access goes through this class. Every query is
 * built with `$wpdb->prepare()` or the `$wpdb` helper methods; the table
 * name itself is never user input, so its direct interpolation into SQL
 * strings is safe.
 *
 * Never decrypts anything — `credentials_json` is stored and returned
 * exactly as given (already individually field-encrypted by the caller,
 * `Service\ConnectionService`).
 */
class ConnectionRepository {

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'connections' ) );
	}

	/**
	 * Creates a new connection row.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type string               $integration_slug Required.
	 *     @type string               $auth_type        Required.
	 *     @type string               $label            Required.
	 *     @type array<string,string> $credentials      Field name => base64 ciphertext.
	 *     @type int                  $status           One of Connection::VALID_STATUSES.
	 * }
	 *
	 * @return Connection|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?Connection {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$data = array(
			'integration_slug' => (string) $attributes['integration_slug'],
			'auth_type'        => (string) $attributes['auth_type'],
			'label'            => (string) $attributes['label'],
			'credentials_json' => wp_json_encode( $attributes['credentials'] ?? array() ),
			'status'           => (int) ( $attributes['status'] ?? Connection::STATUS_PENDING ),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Updates an existing connection row. Only the provided keys are touched.
	 *
	 * @param int                  $id         Connection id.
	 * @param array<string, mixed> $attributes Any of: label, credentials, status.
	 *
	 * @return Connection|null Null if the connection does not exist or the update failed.
	 */
	public function update( int $id, array $attributes ): ?Connection {
		global $wpdb;

		$data    = array();
		$formats = array();

		if ( array_key_exists( 'label', $attributes ) ) {
			$data['label'] = (string) $attributes['label'];
			$formats[]     = '%s';
		}

		if ( array_key_exists( 'credentials', $attributes ) ) {
			$data['credentials_json'] = wp_json_encode( $attributes['credentials'] );
			$formats[]                = '%s';
		}

		if ( array_key_exists( 'status', $attributes ) ) {
			$data['status'] = (int) $attributes['status'];
			$formats[]      = '%d';
		}

		if ( array() === $data ) {
			return $this->find( $id );
		}

		$data['updated_at'] = current_time( 'mysql', true );
		$formats[]          = '%s';

		$updated = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return null;
		}

		return $this->find( $id );
	}

	/**
	 * Permanently removes a connection row.
	 *
	 * @param int $id Connection id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Finds a single connection by id.
	 *
	 * @param int $id Connection id.
	 *
	 * @return Connection|null
	 */
	public function find( int $id ): ?Connection {
		global $wpdb;

		$table = esc_sql($this->table());
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ? Connection::fromRow( $row ) : null;
	}

	/**
	 * Returns a paginated, optionally filtered list of connections.
	 *
	 * @param array<string, mixed> $args {
	 *     @type string $integration_slug Optional exact-match filter.
	 *     @type int    $page             1-indexed page number. Default 1.
	 *     @type int    $per_page         Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: Connection[], total: int, page: int, per_page: int}
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

		if ( ! empty( $args['integration_slug'] ) ) {
			$where[]  = 'integration_slug = %s';
			$params[] = (string) $args['integration_slug'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$table     = esc_sql($this->table());

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
			'items'    => array_map( array( Connection::class, 'fromRow' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
