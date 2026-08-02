<?php
/**
 * WP-Cron-driven background execution worker.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use AIAWA\Plugin\Persistence\WorkflowRunRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Claims and executes a bounded batch of queued (or abandoned) workflow
 * runs on every WP-Cron tick. This is the only caller of
 * `WorkflowRunRepository::claimBatch()` and
 * `WorkflowExecutionService::executeClaimedRun()` — see Core\Plugin for how
 * `processBatch()` gets wired to the recurring cron event, and
 * Core\Activator / Core\Deactivator for how that event is scheduled/cleared.
 */
class BackgroundRunner {

	/**
	 * The WP-Cron hook this worker's processBatch() is bound to.
	 */
	public const CRON_HOOK = 'aiawa/cron/process_queue';

	/**
	 * The custom cron_schedules key registered for that hook. WordPress
	 * ships nothing finer-grained than hourly, so a custom schedule is
	 * required for timely queue draining — see registerCronSchedule().
	 */
	public const CRON_SCHEDULE = 'aiawa_every_minute';

	/**
	 * Maximum runs claimed per cron tick. Kept modest because a single
	 * queued run can include slow third-party HTTP calls (see
	 * NodeExecutionService); a very large batch risks exceeding the host's
	 * request time limit before finishing.
	 */
	private const BATCH_SIZE = 10;

	/**
	 * Wall-clock ceiling, in seconds, on how long a single tick spends
	 * starting new runs. Any claimed runs still unprocessed when this is
	 * hit are put back in the queue (see requeue()) rather than left
	 * claimed, so they are retried on the very next tick instead of
	 * waiting out the full stale-claim window.
	 */
	private const TIME_BUDGET_SECONDS = 20;

	/**
	 * A `running` row untouched for longer than this is assumed to belong
	 * to a worker that crashed or was killed by a request timeout before
	 * it could finish, and becomes eligible for reclaim.
	 */
	private const STALE_CLAIM_MINUTES = 10;

	private WorkflowRunRepository $runs;

	private WorkflowExecutionService $executor;

	public function __construct( WorkflowRunRepository $runs, WorkflowExecutionService $executor ) {
		$this->runs     = $runs;
		$this->executor = $executor;
	}

	/**
	 * Registered against the `cron_schedules` filter to add a
	 * once-a-minute interval. WordPress core does not provide anything
	 * finer than hourly by default.
	 *
	 * @param array<string, array{interval:int,display:string}> $schedules Existing schedules.
	 *
	 * @return array<string, array{interval:int,display:string}>
	 */
	public static function registerCronSchedule( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (Workflow Automate queue)', 'dragwyb-agentflow' ),
		);

		return $schedules;
	}

	/**
	 * Claims and executes one batch of queued/abandoned runs. Bound to
	 * CRON_HOOK; never call this directly outside of that context.
	 *
	 * @return void
	 */
	public function processBatch(): void {
		$started_at = microtime( true );
		$claimed    = $this->runs->claimBatch( self::BATCH_SIZE, self::STALE_CLAIM_MINUTES );

		foreach ( $claimed as $index => $run ) {
			if ( ( microtime( true ) - $started_at ) > self::TIME_BUDGET_SECONDS ) {
				foreach ( array_slice( $claimed, $index ) as $deferred ) {
					$this->runs->requeue( $deferred->id() );
				}

				return;
			}

			$this->executor->executeClaimedRun( $run );
		}
	}
}
