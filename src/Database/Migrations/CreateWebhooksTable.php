<?php
/**
 * Creates the webhooks table.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Database\Migrations;

use AIAWA\Plugin\Database\Migration;
use AIAWA\Plugin\Database\Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `aiawa_webhooks` holds one row per inbound webhook endpoint (roadmap item
 * 13). `public_id` is the unguessable UUID segment of the public URL;
 * `signing_secret` stores an *encrypted* HMAC secret (or empty when
 * signature verification is off for that webhook) — architecture §2.3
 * originally typed this as VARCHAR(191), but field-level AES ciphertext
 * from `Core\Encryption` needs more room, so this migration uses TEXT
 * instead (same reasoning as `aiawa_connections.credentials_json`). No
 * SQL-level FOREIGN KEY for the same `dbDelta()` limitation noted on
 * every other table; application code nulls `workflow_id` when a
 * workflow is permanently deleted.
 */
class CreateWebhooksTable extends Migration {

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Table::name( 'webhooks' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- dbDelta() is intentionally strict about this exact layout (two spaces before "PRIMARY KEY").
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned DEFAULT NULL,
			public_id char(36) NOT NULL,
			signing_secret text NOT NULL,
			ip_allow_list_json text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY workflow_id (workflow_id)
		) {$charset_collate};";
		// phpcs:enable

		dbDelta( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		global $wpdb;

		$table = Table::name( 'webhooks' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; DROP TABLE cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
