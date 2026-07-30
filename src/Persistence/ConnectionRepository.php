<?php
/**
 * Connection repository.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Persistence;

use AIAWAB\Plugin\Database\Table;
use AIAWAB\Plugin\Domain\Connection;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `wfa_connections` access goes through this class. Every query is
 * built with `$wpdb->prepare()` or the `$wpdb` helper methods; the table
 * name itself is never user input, so its direct interpolation into SQL
 * strings is safe (each occurrence is annotated for WPCS accordingly).
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
		return Table::name( 'connections' );
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

		$table = $this->table();
		$sql   = "SELECT * FROM {$table} WHERE id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $id ) );

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

		$where  = array();
		$params = array();

		if ( ! empty( $args['integration_slug'] ) ) {
			$where[]  = 'integration_slug = %s';
			$params[] = (string) $args['integration_slug'];
		}

		$where_sql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';
		$table     = $this->table();

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input; $where_sql contains only static fragments and placeholders.
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input; $where_sql contains only static fragments and placeholders.
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'items'    => array_map( array( Connection::class, 'fromRow' ), $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
