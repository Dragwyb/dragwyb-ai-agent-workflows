<?php
/**
 * Workflow node repository.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

use AIAWA\Plugin\Database\Table;
use AIAWA\Plugin\Domain\WorkflowNode;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All `aiawa_workflow_nodes` access goes through this class.
 */
class WorkflowNodeRepository {

	/**
	 * Defensive upper bound on nodes fetched for a single workflow. A
	 * legitimate workflow graph is expected to stay well under this; it
	 * exists only to guard against pathological data, not as UI pagination.
	 */
	private const MAX_NODES_PER_WORKFLOW = 1000;

	/**
	 * @return string
	 */
	private function table(): string {
		return esc_sql( Table::name( 'workflow_nodes' ) );
	}

	/**
	 * Creates a new node row.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type int                       $workflow_id    Required.
	 *     @type string                    $client_node_id Required, unique per workflow.
	 *     @type string                    $node_type      Required.
	 *     @type string|null               $label          Optional display label.
	 *     @type array<string, mixed>|null $config         Optional node configuration.
	 * }
	 *
	 * @return WorkflowNode|null Null if the insert failed (including a duplicate client_node_id).
	 */
	public function insert( array $attributes ): ?WorkflowNode {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$data = array(
			'workflow_id'    => (int) $attributes['workflow_id'],
			'client_node_id' => (string) $attributes['client_node_id'],
			'node_type'      => (string) $attributes['node_type'],
			'label'          => isset( $attributes['label'] ) ? (string) $attributes['label'] : null,
			'config_json'    => isset( $attributes['config'] ) ? wp_json_encode( $attributes['config'] ) : null,
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $this->table(), $data, $formats );

		if ( false === $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Updates an existing node row. Only the provided keys are touched.
	 *
	 * @param int                  $id         Node id.
	 * @param array<string, mixed> $attributes Any of: label, config, node_type.
	 *
	 * @return WorkflowNode|null Null if the node does not exist or the update failed.
	 */
	public function update( int $id, array $attributes ): ?WorkflowNode {
		global $wpdb;

		$data    = array();
		$formats = array();

		if ( array_key_exists( 'node_type', $attributes ) ) {
			$data['node_type'] = (string) $attributes['node_type'];
			$formats[]         = '%s';
		}

		if ( array_key_exists( 'label', $attributes ) ) {
			$data['label'] = null === $attributes['label'] ? null : (string) $attributes['label'];
			$formats[]     = '%s';
		}

		if ( array_key_exists( 'config', $attributes ) ) {
			$data['config_json'] = null === $attributes['config'] ? null : wp_json_encode( $attributes['config'] );
			$formats[]           = '%s';
		}

		if ( array() === $data ) {
			return $this->find( $id );
		}

		$data['updated_at'] = current_time( 'mysql', true );
		$formats[]          = '%s';

		$updated = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $updated ) {
			return null;
		}

		return $this->find( $id );
	}

	/**
	 * Finds a single node by id.
	 *
	 * @param int $id Node id.
	 *
	 * @return WorkflowNode|null
	 */
	public function find( int $id ): ?WorkflowNode {
		global $wpdb;

		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is not user input.

		return $row ? WorkflowNode::fromRow( $row ) : null;
	}

	/**
	 * Returns all nodes belonging to a workflow, ordered by insertion order.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return WorkflowNode[]
	 */
	public function findByWorkflow( int $workflow_id ): array {
		global $wpdb;

		$table = $this->table();
		$rows  = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter --- $table is escaped and %i placeholder is support wp 6.2+
			$wpdb->prepare( "SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY id ASC LIMIT %d", $workflow_id, self::MAX_NODES_PER_WORKFLOW )
		);

		return array_map( array( WorkflowNode::class, 'fromRow' ), $rows );
	}

	/**
	 * Permanently removes a single node row.
	 *
	 * @param int $id Node id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Permanently removes every node belonging to a workflow. Used by
	 * WorkflowService::delete() when hard-deleting a workflow, since there
	 * is no SQL-level cascade (see CreateWorkflowNodesTable migration).
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return bool
	 */
	public function deleteByWorkflow( int $workflow_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'workflow_id' => $workflow_id ), array( '%d' ) );

		return false !== $deleted;
	}
}
