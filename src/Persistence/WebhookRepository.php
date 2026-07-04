<?php
/**
 * Webhook repository.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Persistence;

use WorkflowAutomate\Plugin\Database\Table;
use WorkflowAutomate\Plugin\Domain\Webhook;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `wfa_webhooks` access goes through this class. Never decrypts the
 * signing secret — that stays the job of `Service\WebhookService`.
 */
class WebhookRepository {

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * @return string
	 */
	private function table(): string {
		return Table::name( 'webhooks' );
	}

	/**
	 * Creates a new webhook row.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type int|null  $workflow_id     Target workflow, or null.
	 *     @type string    $public_id       UUID for the public URL.
	 *     @type string    $signing_secret  Encrypted secret, or ''.
	 *     @type string[]  $ip_allow_list   Exact IPs and/or CIDR ranges.
	 * }
	 *
	 * @return Webhook|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?Webhook {
		global $wpdb;

		$data = array(
			'workflow_id' => isset( $attributes['workflow_id'] ) && null !== $attributes['workflow_id']
				? (int) $attributes['workflow_id']
				: null,
			'public_id' => (string) $attributes['public_id'],
			'signing_secret' => (string) ( $attributes['signing_secret'] ?? '' ),
			'ip_allow_list_json' => wp_json_encode( $attributes['ip_allow_list'] ?? array() ),
			'created_at' => current_time( 'mysql', true ),
		);

		// workflow_id may be null; $wpdb->insert needs an explicit format
		// list and treats null as SQL NULL when the format is '%d' only if
		// we pass null — which we do above.
		$formats = array( '%d', '%s', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Updates an existing webhook row. Only the provided keys are touched.
	 *
	 * @param int                  $id         Webhook id.
	 * @param array<string, mixed> $attributes Any of: workflow_id, signing_secret, ip_allow_list.
	 *
	 * @return Webhook|null Null if the webhook does not exist or the update failed.
	 */
	public function update( int $id, array $attributes ): ?Webhook {
		global $wpdb;

		$data = array();
		$formats = array();

		if ( array_key_exists( 'workflow_id', $attributes ) ) {
			$data['workflow_id'] = null !== $attributes['workflow_id'] ? (int) $attributes['workflow_id'] : null;
			$formats[] = '%d';
		}

		if ( array_key_exists( 'signing_secret', $attributes ) ) {
			$data['signing_secret'] = (string) $attributes['signing_secret'];
			$formats[] = '%s';
		}

		if ( array_key_exists( 'ip_allow_list', $attributes ) ) {
			$data['ip_allow_list_json'] = wp_json_encode( $attributes['ip_allow_list'] );
			$formats[] = '%s';
		}

		if ( array() === $data ) {
			return $this->find( $id );
		}

		$updated = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return null;
		}

		return $this->find( $id );
	}

	/**
	 * Permanently removes a webhook row.
	 *
	 * @param int $id Webhook id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Nulls `workflow_id` on every webhook that pointed at a permanently
	 * deleted workflow (application-level ON DELETE SET NULL).
	 *
	 * @param int $workflow_id Workflow id that was hard-deleted.
	 *
	 * @return void
	 */
	public function nullifyWorkflow( int $workflow_id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array( 'workflow_id' => null ),
			array( 'workflow_id' => $workflow_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Finds a single webhook by id.
	 *
	 * @param int $id Webhook id.
	 *
	 * @return Webhook|null
	 */
	public function find( int $id ): ?Webhook {
		global $wpdb;

		$table = $this->table();
		$sql = "SELECT * FROM {$table} WHERE id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $id ) );

		return $row ? Webhook::fromRow( $row ) : null;
	}

	/**
	 * Finds a single webhook by its public URL id.
	 *
	 * @param string $public_id UUID segment of the public ingress URL.
	 *
	 * @return Webhook|null
	 */
	public function findByPublicId( string $public_id ): ?Webhook {
		global $wpdb;

		$table = $this->table();
		$sql = "SELECT * FROM {$table} WHERE public_id = %s"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $public_id ) );

		return $row ? Webhook::fromRow( $row ) : null;
	}

	/**
	 * Returns a paginated list of webhooks.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int $page     1-indexed page number. Default 1.
	 *     @type int $per_page Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * @return array{items: Webhook[], total: int, page: int, per_page: int}
	 */
	public function paginate( array $args = array() ): array {
		global $wpdb;

		$page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset = ( $page - 1 ) * $per_page;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is not user input.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$list_sql = "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $per_page, $offset ) );

		return array(
			'items' => array_map( array( Webhook::class, 'fromRow' ), $rows ? $rows : array() ),
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
		);
	}
}
