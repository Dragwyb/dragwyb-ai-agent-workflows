<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Database;

use DragwybAgentFlow\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use DragwybAgentFlow\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateConnectionsTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateWebhooksTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use DragwybAgentFlow\Plugin\Database\Migrations\CreateWorkflowsTable;

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
			CreateWebhooksTable::class,
		);
	}
}
