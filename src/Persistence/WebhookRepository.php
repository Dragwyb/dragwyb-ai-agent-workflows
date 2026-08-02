<?php
/**
 * Webhook repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\Webhook;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_webhooks` access goes through this class. Never decrypts the
 * signing secret — that stays the job of `Service\WebhookService`.
 */
class WebhookRepository {

	use CachesRepositoryRows;

	private const CACHE_GROUP = 'aiawa_webhooks';

	private const MAX_PER_PAGE = 100;

	private const DEFAULT_PER_PAGE = 20;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'webhooks' ) );
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
			'workflow_id'        => isset( $attributes['workflow_id'] ) && null !== $attributes['workflow_id']
				? (int) $attributes['workflow_id']
				: null,
			'public_id'          => (string) $attributes['public_id'],
			'signing_secret'     => (string) ( $attributes['signing_secret'] ?? '' ),
			'ip_allow_list_json' => wp_json_encode( $attributes['ip_allow_list'] ?? array() ),
			'created_at'         => current_time( 'mysql', true ),
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

		$data    = array();
		$formats = array();

		if ( array_key_exists( 'workflow_id', $attributes ) ) {
			$data['workflow_id'] = null !== $attributes['workflow_id'] ? (int) $attributes['workflow_id'] : null;
			$formats[]           = '%d';
		}

		if ( array_key_exists( 'signing_secret', $attributes ) ) {
			$data['signing_secret'] = (string) $attributes['signing_secret'];
			$formats[]              = '%s';
		}

		if ( array_key_exists( 'ip_allow_list', $attributes ) ) {
			$data['ip_allow_list_json'] = wp_json_encode( $attributes['ip_allow_list'] );
			$formats[]                  = '%s';
		}

		if ( array() === $data ) {
			return $this->find( $id );
		}

		$updated = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return null;
		}

		$this->cacheDelete( (string) $id );

		$webhook = $this->find( $id );

		if ( null !== $webhook ) {
			// public_id is immutable, so this is always the same key the
			// row was (or would be) cached under via findByPublicId().
			$this->cacheDelete( $this->publicIdCacheKey( $webhook->publicId() ) );
		}

		return $webhook;
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

		// Looked up first (cache-aware, so usually free) purely to learn
		// the public_id cache key that also needs invalidating.
		$webhook = $this->find( $id );

		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		$this->cacheDelete( (string) $id );

		if ( null !== $webhook ) {
			$this->cacheDelete( $this->publicIdCacheKey( $webhook->publicId() ) );
		}

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Nulls `workflow_id` on every webhook that pointed at a permanently
	 * deleted workflow (application-level ON DELETE SET NULL).
	 *
	 * Not cache-invalidated here: this bulk `UPDATE ... WHERE workflow_id`
	 * doesn't cheaply tell us which individual ids/public_ids it touched,
	 * and workflow hard-delete is a rare admin action, so any affected
	 * webhook's cached `workflow_id` field briefly going stale is low
	 * impact and self-heals the next time that specific webhook is
	 * written through update()/delete().
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

		$cache_key = (string) $id;
		$cached    = $this->cacheGet( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql($this->table());
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		$webhook = $row ? Webhook::fromRow( $row ) : null;

		if ( null !== $webhook ) {
			$this->cacheSet( $cache_key, $webhook );
		}

		return $webhook;
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

		$cache_key = $this->publicIdCacheKey( $public_id );
		$cached    = $this->cacheGet( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql($this->table());
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s", $public_id ) );

		$webhook = $row ? Webhook::fromRow( $row ) : null;

		if ( null !== $webhook ) {
			$this->cacheSet( $cache_key, $webhook );
		}

		return $webhook;
	}

	/**
	 * @param string $public_id UUID segment of the public ingress URL.
	 *
	 * @return string
	 */
	private function publicIdCacheKey( string $public_id ): string {
		return 'public_' . $public_id;
	}

	/**
	 * Returns a paginated list of webhooks.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int $page     1-indexed page number. Default 1.
	 *     @type int $per_page Rows per page, clamped to [1, 100]. Default 20.
	 * }
	 *
	 * Intentionally not object-cached: the result depends on an open-ended
	 * combination of filters, page, and per_page, so caching it would need
	 * one cache key per combination, invalidated on nearly every write to
	 * this table — high complexity for little real hit rate. Only find()
	 * and findByPublicId() cache, since each has exactly one cache key.
	 *
	 * @return array{items: Webhook[], total: int, page: int, per_page: int}
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

		if ( ! empty( $args['workflow_id'] ) ) {
			$where[]  = 'workflow_id = %d';
			$params[] = (int) $args['workflow_id'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$table     = esc_sql($this->table());

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- see paginate() docblock.
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+ and $where_sql is escaped
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params )
		);

		$list_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- see paginate() docblock.
		$rows        = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber --- $table is escaped and %i placeholder is support wp 6.2+ and $where_sql is escaped
			$wpdb->prepare( "SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", $list_params )
		);

		return array(
			'items'    => array_map( array( Webhook::class, 'fromRow' ), $rows ? $rows : array() ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}