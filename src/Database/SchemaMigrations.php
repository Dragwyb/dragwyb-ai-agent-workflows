<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Database;

use WorkflowAutomate\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use WorkflowAutomate\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use WorkflowAutomate\Plugin\Database\Migrations\CreateConnectionsTable;
use WorkflowAutomate\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use WorkflowAutomate\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use WorkflowAutomate\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use WorkflowAutomate\Plugin\Database\Migrations\CreateWorkflowsTable;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for migration order, consumed by both the
 * activation flow and the opt-in uninstall data removal flow (in reverse).
 */
class SchemaMigrations {

	/**
	 * @return array<int, class-string<Migration>>
	 */
	public static function all(): array {
		return array(
			CreateWorkflowsTable::class,
			CreateWorkflowNodesTable::class,
			CreateWorkflowRunsTable::class,
			CreateWorkflowRunLogsTable::class,
			AddQueueColumnsToWorkflowRunsTable::class,
			AddNodeSnapshotColumnsToWorkflowRunLogsTable::class,
			CreateConnectionsTable::class,
		);
	}
}
