<?php
/**
 * Workflow run log repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\WorkflowRunLog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_workflow_run_logs` access goes through this class.
 */
class WorkflowRunLogRepository {

	/**
	 * Defensive upper bound on logs fetched for a single run. A legitimate
	 * workflow is expected to stay well under this; it exists only to guard
	 * against pathological data, not as UI pagination.
	 */
	private const MAX_LOGS_PER_RUN = 1000;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'workflow_run_logs' ) );
	}

	/**
	 * Creates a new log row.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type int                       $run_id      Required.
	 *     @type int|null                  $node_id     The node this entry is for, if any.
	 *     @type string|null               $node_type   The node's type slug at the time it ran (see WorkflowRunLog::nodeType()).
	 *     @type string|null               $node_label  The node's label at the time it ran, if any.
	 *     @type string                    $status      One of WorkflowRunLog::STATUS_*.
	 *     @type array<string, mixed>|null $input       The node's configuration at the time it ran.
	 *     @type array<string, mixed>|null $output      The node's raw execution result.
	 *     @type string|null               $message     Human-readable outcome summary.
	 *     @type int|null                  $duration_ms How long the node took to execute.
	 * }
	 *
	 * @return WorkflowRunLog|null Null if the insert failed.
	 */
	public function insert( array $attributes ): ?WorkflowRunLog {
		global $wpdb;

		$data = array(
			'run_id'      => (int) $attributes['run_id'],
			'node_id'     => isset( $attributes['node_id'] ) ? (int) $attributes['node_id'] : null,
			'node_type'   => isset( $attributes['node_type'] ) ? (string) $attributes['node_type'] : null,
			'node_label'  => isset( $attributes['node_label'] ) ? (string) $attributes['node_label'] : null,
			'status'      => (string) $attributes['status'],
			'input_json'  => isset( $attributes['input'] ) ? wp_json_encode( $attributes['input'] ) : null,
			'output_json' => isset( $attributes['output'] ) ? wp_json_encode( $attributes['output'] ) : null,
			'message'     => isset( $attributes['message'] ) ? (string) $attributes['message'] : null,
			'duration_ms' => isset( $attributes['duration_ms'] ) ? (int) $attributes['duration_ms'] : null,
			'created_at'  => current_time( 'mysql', true ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Finds a single log entry by id.
	 *
	 * @param int $id Log id.
	 *
	 * @return WorkflowRunLog|null
	 */
	public function find( int $id ): ?WorkflowRunLog {
		global $wpdb;

		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is not user input.

		return $row ? WorkflowRunLog::fromRow( $row ) : null;
	}

	/**
	 * Returns every log entry for a run, in execution order.
	 *
	 * @param int $run_id Run id.
	 *
	 * @return WorkflowRunLog[]
	 */
	public function findByRun( int $run_id ): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE run_id = %d ORDER BY id ASC LIMIT %d", $run_id, self::MAX_LOGS_PER_RUN )
		);

		return array_map( array( WorkflowRunLog::class, 'fromRow' ), $rows );
	}

	/**
	 * Permanently removes every log entry belonging to any of the given
	 * runs. Used by WorkflowService::delete() when hard-deleting a
	 * workflow, since there is no SQL-level cascade (see
	 * CreateWorkflowRunLogsTable migration).
	 *
	 * @param int[] $run_ids Run ids.
	 *
	 * @return bool
	 */
	public function deleteByRunIds( array $run_ids ): bool {
		global $wpdb;

		if ( array() === $run_ids ) {
			return true;
		}

		$run_ids      = array_map( 'intval', $run_ids );
		$placeholders = implode( ', ', array_fill( 0, count( $run_ids ), '%d' ) );
		$table        = $this->table();

		$deleted = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "DELETE FROM {$table} WHERE run_id IN ({$placeholders})", $run_ids )
		);

		return false !== $deleted;
	}
}
