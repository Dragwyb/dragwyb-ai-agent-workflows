<?php
/**
 * Creates the workflow runs table.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Database\Migrations;

use WorkflowAutomate\Plugin\Database\Migration;
use WorkflowAutomate\Plugin\Database\Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wfa_workflow_runs` holds one row per execution of a workflow. As with
 * `wfa_workflow_nodes`, there is no SQL-level FOREIGN KEY to `wfa_workflows`
 * because `dbDelta()` does not reliably manage foreign key constraints;
 * cascade-on-delete is instead enforced explicitly in the repository/service
 * layer. See docs/internal/architecture.md §2.3.
 *
 * `status` and `created_at` are both indexed (in addition to the composite
 * `workflow_id, created_at` index) because the run history UI planned for a
 * later roadmap item is expected to filter by status independently of a
 * specific workflow (e.g. "show me every failed run across all workflows").
 */
class CreateWorkflowRunsTable extends Migration {

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Table::name( 'workflow_runs' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned -- dbDelta() is intentionally strict about this exact layout (two spaces before "PRIMARY KEY").
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			parent_run_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			started_at datetime DEFAULT NULL,
			finished_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_created (workflow_id, created_at),
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

		$table = Table::name( 'workflow_runs' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; DROP TABLE cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
