<?php
/**
 * Renders a WorkflowRun status as a small colored badge.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Domain\WorkflowRun;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared between RunsListTable and RunDetailPage (roadmap item 9) so the
 * status-to-label-and-color mapping exists in exactly one place.
 */
class RunStatusBadge {

	/**
	 * Renders an already-escaped HTML badge for a run status.
	 *
	 * @param string $status One of WorkflowRun::VALID_STATUSES.
	 *
	 * @return string
	 */
	public static function render( string $status ): string {
		return sprintf(
			'<span class="aiawa-status-badge aiawa-status-badge--%1$s">%2$s</span>',
			esc_attr( self::slug( $status ) ),
			esc_html( self::label( $status ) )
		);
	}

	/**
	 * @param string $status One of WorkflowRun::VALID_STATUSES.
	 *
	 * @return string A CSS-class-safe slug; falls back to "unknown" for any unrecognized value rather than leaking one into the class attribute.
	 */
	private static function slug( string $status ): string {
		return in_array( $status, WorkflowRun::VALID_STATUSES, true ) ? $status : 'unknown';
	}

	/**
	 * @param string $status One of WorkflowRun::VALID_STATUSES.
	 *
	 * @return string
	 */
	private static function label( string $status ): string {
		switch ( $status ) {
			case WorkflowRun::STATUS_QUEUED:
				return __( 'Queued', 'ai-agent-workflow-automation' );
			case WorkflowRun::STATUS_RUNNING:
				return __( 'Running', 'ai-agent-workflow-automation' );
			case WorkflowRun::STATUS_SUCCESS:
				return __( 'Success', 'ai-agent-workflow-automation' );
			case WorkflowRun::STATUS_FAILED:
				return __( 'Failed', 'ai-agent-workflow-automation' );
			case WorkflowRun::STATUS_PARTIAL:
				return __( 'Partial', 'ai-agent-workflow-automation' );
			default:
				return __( 'Unknown', 'ai-agent-workflow-automation' );
		}
	}
}
