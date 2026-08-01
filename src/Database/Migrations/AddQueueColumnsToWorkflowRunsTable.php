<?php
/**
 * Adds background-queue columns to the workflow runs table.
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
 * Extends `aiawa_workflow_runs` (created in roadmap item 7) for background/
 * queued execution (roadmap item 8), rather than editing
 * `CreateWorkflowRunsTable` in place — that migration already shipped and
 * may have run against a live site, so schema evolution happens through a
 * new, additive migration instead, exactly like a real production upgrade.
 *
 * `dbDelta()` diffs a full `CREATE TABLE` statement against the live table
 * and only adds what's missing, so re-issuing the complete column list
 * (unchanged columns included) is the correct, idiomatic way to add columns
 * with it — there is no separate "ALTER TABLE ADD COLUMN" dbDelta helper.
 *
 * New columns:
 * - `trigger_payload_json`: a queued run executes in a *later*, different
 *   request than the one that queued it, so the triggering payload has to
 *   be persisted rather than kept in memory (unlike the synchronous path).
 * - `attempts` / `next_attempt_at`: retry/backoff bookkeeping. `attempts`
 *   is 1-indexed ("this is attempt N"); existing rows backfill to `1`,
 *   which is correct for them (a synchronous run from item 7 only ever had
 *   one attempt).
 * - `claim_token`: lets `WorkflowRunRepository::claimBatch()` identify
 *   exactly which rows *this* invocation claimed via a single atomic
 *   `UPDATE ... LIMIT`, with no transaction required — see that method's
 *   docblock for the full race-condition reasoning.
 */
class AddQueueColumnsToWorkflowRunsTable extends Migration {

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
			trigger_payload_json longtext DEFAULT NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 1,
			next_attempt_at datetime DEFAULT NULL,
			claim_token varchar(36) DEFAULT NULL,
			started_at datetime DEFAULT NULL,
			finished_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_created (workflow_id, created_at),
			KEY status (status),
			KEY status_next_attempt (status, next_attempt_at),
			KEY claim_token (claim_token)
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

		// Dropping a column that is the sole member of an index (claim_token)
		// or part of a composite one (status_next_attempt) makes MySQL adjust
		// or drop that index automatically; no separate DROP INDEX is needed.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input; column names are hardcoded, not user input.
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN trigger_payload_json, DROP COLUMN attempts, DROP COLUMN next_attempt_at, DROP COLUMN claim_token" );
	}
}
