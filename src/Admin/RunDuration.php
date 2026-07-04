<?php
/**
 * Formats run/node execution durations for display.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use WorkflowAutomate\Plugin\Domain\WorkflowRun;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared between RunsListTable and RunDetailPage (roadmap item 9). Kept as
 * plain string formatting only — no calculation belongs here that the
 * domain/persistence layers don't already provide.
 */
class RunDuration {

	/**
	 * Formats a run's total duration from its started_at/finished_at
	 * timestamps (both stored in GMT — see WorkflowRun — so a plain
	 * strtotime() diff is safe without any timezone conversion).
	 *
	 * Precision is whole seconds only: `wfa_workflow_runs` timestamps come
	 * from current_time( 'mysql', true ), which has no sub-second
	 * resolution. Good enough for a history list; not a substitute for the
	 * per-node millisecond timings already shown in the run detail view.
	 *
	 * @param WorkflowRun $run The run.
	 *
	 * @return string
	 */
	public static function forRun( WorkflowRun $run ): string {
		if ( null === $run->startedAt() || null === $run->finishedAt() ) {
			return __( '—', 'workflow-automate' );
		}

		$seconds = max( 0, strtotime( $run->finishedAt() . ' UTC' ) - strtotime( $run->startedAt() . ' UTC' ) );

		return self::formatSeconds( $seconds );
	}

	/**
	 * Formats a single node's execution time, stored with millisecond
	 * precision (unlike run-level timestamps).
	 *
	 * @param int|null $duration_ms Milliseconds, or null if unavailable.
	 *
	 * @return string
	 */
	public static function forNode( ?int $duration_ms ): string {
		if ( null === $duration_ms ) {
			return __( '—', 'workflow-automate' );
		}

		if ( $duration_ms < 1000 ) {
			return sprintf(
				/* translators: %d: duration in milliseconds. */
				__( '%d ms', 'workflow-automate' ),
				$duration_ms
			);
		}

		return self::formatSeconds( (int) round( $duration_ms / 1000 ) );
	}

	/**
	 * @param int $seconds Non-negative whole seconds.
	 *
	 * @return string
	 */
	private static function formatSeconds( int $seconds ): string {
		if ( $seconds < 60 ) {
			return sprintf(
				/* translators: %d: duration in seconds. */
				_n( '%d second', '%d seconds', $seconds, 'workflow-automate' ),
				$seconds
			);
		}

		$minutes = (int) floor( $seconds / 60 );
		$remaining = $seconds % 60;

		return sprintf(
			/* translators: 1: minutes, 2: seconds. */
			__( '%1$dm %2$ds', 'workflow-automate' ),
			$minutes,
			$remaining
		);
	}
}
