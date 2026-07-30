<?php
/**
 * Creates the connections table.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Database\Migrations;

use AIAWAB\Plugin\Database\Migration;
use AIAWAB\Plugin\Database\Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wfa_connections` holds one row per stored third-party credential
 * (roadmap item 11). `credentials_json` is a JSON object of
 * `{field: ciphertext}` pairs — each *value* individually encrypted (see
 * `Core\Encryption`) before the object is ever serialized, not the whole
 * blob encrypted as one opaque unit, matching the field-level approach
 * `docs/internal/architecture.md` §2.3 already specified. No SQL-level
 * FOREIGN KEY exists for the same `dbDelta()` limitation noted on every
 * other table.
 */
class CreateConnectionsTable extends Migration {

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Table::name( 'connections' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- dbDelta() is intentionally strict about this exact layout (two spaces before "PRIMARY KEY").
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			integration_slug varchar(100) NOT NULL,
			auth_type varchar(30) NOT NULL,
			label varchar(191) NOT NULL,
			credentials_json longtext NOT NULL,
			status tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY integration_slug (integration_slug),
			KEY status (status)
		) {$charset_collate};";
		// phpcs:enable

		dbDelta( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		global $wpdb;

		$table = Table::name( 'connections' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; DROP TABLE cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
