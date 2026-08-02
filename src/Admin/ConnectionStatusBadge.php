<?php
/**
 * Renders a Connection status as a small colored badge.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Domain\Connection;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared between ConnectionsListTable and a future connection detail view.
 * Reuses the same `.aiawa-status-badge` CSS classes RunStatusBadge already
 * introduced (roadmap item 9) rather than a second color palette.
 */
class ConnectionStatusBadge {

	/**
	 * Renders an already-escaped HTML badge for a connection status.
	 *
	 * @param int $status One of Connection::VALID_STATUSES.
	 *
	 * @return string
	 */
	public static function render( int $status ): string {
		return sprintf(
			'<span class="aiawa-status-badge aiawa-status-badge--%1$s">%2$s</span>',
			esc_attr( self::slug( $status ) ),
			esc_html( self::label( $status ) )
		);
	}

	/**
	 * @param int $status One of Connection::VALID_STATUSES.
	 *
	 * @return string A CSS-class-safe slug reusing RunStatusBadge's palette: verified maps to the same green as a successful run, failed to the same red, pending to the same neutral grey as a queued run.
	 */
	private static function slug( int $status ): string {
		switch ( $status ) {
			case Connection::STATUS_VERIFIED:
				return 'success';
			case Connection::STATUS_FAILED:
				return 'failed';
			default:
				return 'queued';
		}
	}

	/**
	 * @param int $status One of Connection::VALID_STATUSES.
	 *
	 * @return string
	 */
	private static function label( int $status ): string {
		switch ( $status ) {
			case Connection::STATUS_VERIFIED:
				return __( 'Verified', 'ai-agent-workflow-automation' );
			case Connection::STATUS_FAILED:
				return __( 'Failed', 'ai-agent-workflow-automation' );
			default:
				return __( 'Not yet verified', 'ai-agent-workflow-automation' );
		}
	}
}
