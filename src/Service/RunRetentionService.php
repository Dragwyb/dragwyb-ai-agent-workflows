<?php
/**
 * Prunes old, finished workflow runs and their logs.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements the "Logging/Retention" setting (roadmap item 10) and the
 * `CURSOR_INSTRUCTIONS.md` requirement that execution history not grow
 * unbounded: a daily WP-Cron tick (see Core\Plugin::registerRetentionPruning())
 * calls pruneAccordingToSettings(), and the Settings screen's "Purge now"
 * button calls the same method on demand for an immediate cleanup.
 *
 * Only ever deletes *finished* runs older than the configured retention
 * window (see WorkflowRunRepository::idsFinishedBefore()); a `retention_days`
 * of 0 means "keep forever" and disables pruning entirely.
 */
class RunRetentionService {

	/**
	 * The WP-Cron hook this service's pruneAccordingToSettings() is bound to.
	 */
	public const CRON_HOOK = 'wfa/cron/prune_runs';

	private WorkflowRunRepository $runs;

	private WorkflowRunLogRepository $runLogs;

	private SettingsService $settings;

	public function __construct( WorkflowRunRepository $runs, WorkflowRunLogRepository $runLogs, SettingsService $settings ) {
		$this->runs = $runs;
		$this->runLogs = $runLogs;
		$this->settings = $settings;
	}

	/**
	 * Prunes according to the currently configured retention_days setting.
	 * Bound to CRON_HOOK; also called directly by the "Purge now" admin
	 * action for an on-demand run of the exact same logic.
	 *
	 * @return int Number of runs deleted (0 if retention is disabled, or nothing was eligible).
	 */
	public function pruneAccordingToSettings(): int {
		$days = $this->settings->retentionDays();

		if ( $days <= 0 ) {
			return 0;
		}

		return $this->pruneOlderThan( $days );
	}

	/**
	 * @param int $days Runs finished more than this many days ago are deleted.
	 *
	 * @return int Number of runs deleted.
	 */
	public function pruneOlderThan( int $days ): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$ids = $this->runs->idsFinishedBefore( $cutoff );

		if ( array() === $ids ) {
			return 0;
		}

		// Logs first: they are keyed by run_id, so a run row can never be
		// left with orphaned logs even if the request is interrupted
		// between these two calls.
		$this->runLogs->deleteByRunIds( $ids );

		return $this->runs->deleteByIds( $ids );
	}
}
