<?php
/**
 * Adds node-type/label snapshot columns to the workflow run logs table.
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
 * Extends `aiawa_workflow_run_logs` (created in roadmap item 7) for the
 * history UI shipped in roadmap item 9, additively — same reasoning as
 * `AddQueueColumnsToWorkflowRunsTable` from item 8: the original migration
 * already shipped, so schema evolution happens through a new migration.
 *
 * `node_id` alone is not enough to render a readable history: that
 * migration's own docblock already flagged that a node can be edited or
 * removed from the builder graph after a run references it, leaving old
 * log rows with a `node_id` that no longer resolves to anything (or
 * resolves to a *different* node's current type/label, if the builder ever
 * reuses ids). `node_type` and `node_label` snapshot what the node actually
 * was *at the moment it ran*, populated once in
 * `WorkflowExecutionService::executeNodes()` and never updated afterwards
 * — the log row becomes fully self-contained for display purposes, the
 * same way `input_json` already snapshots the node's configuration at run
 * time rather than pointing back at `aiawa_workflow_nodes.config_json`.
 */
class AddNodeSnapshotColumnsToWorkflowRunLogsTable extends Migration {

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
			node_type varchar(100) DEFAULT NULL,
			node_label varchar(191) DEFAULT NULL,
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; column names are hardcoded, not user input.
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN node_type, DROP COLUMN node_label" );
	}
}
