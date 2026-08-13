<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Database;

use DragwybVisualAutomation\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateConnectionsTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateWebhooksTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use DragwybVisualAutomation\Plugin\Database\Migrations\CreateWorkflowsTable;

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
