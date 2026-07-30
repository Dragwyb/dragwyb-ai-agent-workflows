<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Database;

use AIAWAB\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use AIAWAB\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use AIAWAB\Plugin\Database\Migrations\CreateConnectionsTable;
use AIAWAB\Plugin\Database\Migrations\CreateWebhooksTable;
use AIAWAB\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use AIAWAB\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use AIAWAB\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use AIAWAB\Plugin\Database\Migrations\CreateWorkflowsTable;

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
