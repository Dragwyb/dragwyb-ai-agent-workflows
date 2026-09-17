<?php
/**
 * Ordered list of all schema migrations.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Database;

use DRAGAIW\Plugin\Database\Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable;
use DRAGAIW\Plugin\Database\Migrations\AddQueueColumnsToWorkflowRunsTable;
use DRAGAIW\Plugin\Database\Migrations\CreateConnectionsTable;
use DRAGAIW\Plugin\Database\Migrations\CreateWebhooksTable;
use DRAGAIW\Plugin\Database\Migrations\CreateWorkflowNodesTable;
use DRAGAIW\Plugin\Database\Migrations\CreateWorkflowRunLogsTable;
use DRAGAIW\Plugin\Database\Migrations\CreateWorkflowRunsTable;
use DRAGAIW\Plugin\Database\Migrations\CreateWorkflowsTable;

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
