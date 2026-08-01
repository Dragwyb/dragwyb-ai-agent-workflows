<?php
/**
 * Creates the workflow run logs table.
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
 * `aiawa_workflow_run_logs` holds one row per node outcome within a workflow
 * run. `node_id` intentionally has no `NOT NULL` constraint: it references
 * `aiawa_workflow_nodes.id`, but a node can later be removed from the builder
 * graph (which deletes its row) while its historical run logs are kept, so
 * `node_id` on an old log entry may point to a node that no longer exists.
 * A future "Runs" UI (roadmap item 9) is expected to resolve `node_id`
 * defensively (falling back to "deleted node") rather than assume the row
 * is always present. No SQL-level FOREIGN KEY exists for the same
 * `dbDelta()` limitation noted on the other tables; see
 * docs/internal/architecture.md §2.3.
 */
class CreateWorkflowRunLogsTable extends Migration {

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Table::name( 'workflow_run_logs' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- dbDelta() is intentionally strict about this exact layout (two spaces before "PRIMARY KEY").
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			node_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL,
			input_json longtext DEFAULT NULL,
			output_json longtext DEFAULT NULL,
			message text DEFAULT NULL,
			duration_ms int(10) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id)
		) {$charset_collate};";
		// phpcs:enable

		dbDelta( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		global $wpdb;

		$table = Table::name( 'workflow_run_logs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; DROP TABLE cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
