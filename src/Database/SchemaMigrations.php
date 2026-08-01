<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Database;

use AIAWA\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use AIAWA\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use AIAWA\Plugin\Database\Migrations\CreateConnectionsTable;
use AIAWA\Plugin\Database\Migrations\CreateWebhooksTable;
use AIAWA\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use AIAWA\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use AIAWA\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use AIAWA\Plugin\Database\Migrations\CreateWorkflowsTable;

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
