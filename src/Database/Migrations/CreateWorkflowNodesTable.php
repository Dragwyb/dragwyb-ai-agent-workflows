<?php
/**
 * Creates the workflow nodes table.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Database\Migrations;

use DragwybAgentFlow\Plugin\Database\Migration;
use DragwybAgentFlow\Plugin\Database\Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `dragwyb_af_workflow_nodes` holds one row per node in a workflow's graph. There
 * is no SQL-level FOREIGN KEY to `dragwyb_af_workflows` because `dbDelta()` does
 * not reliably manage foreign key constraints; cascade-on-delete is instead
 * enforced explicitly in the repository/service layer. See
 * docs/internal/architecture.md §2.3.
 */
class CreateWorkflowNodesTable extends Migration {

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Table::name( 'workflow_nodes' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- dbDelta() is intentionally strict about this exact layout (two spaces before "PRIMARY KEY").
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			client_node_id varchar(64) NOT NULL,
			node_type varchar(100) NOT NULL,
			label varchar(191) DEFAULT NULL,
			config_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_id (workflow_id),
			UNIQUE KEY workflow_client_node (workflow_id, client_node_id)
		) {$charset_collate};";
		// phpcs:enable

		dbDelta( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		global $wpdb;

		$table = esc_sql( Table::name( 'workflow_nodes' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is not user input; DROP TABLE cannot be parameterized; schema DDL is never a caching candidate.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
