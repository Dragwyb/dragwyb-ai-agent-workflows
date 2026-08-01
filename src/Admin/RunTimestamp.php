<?php
/**
 * Formats GMT-stored timestamps for admin display.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every timestamp this plugin stores (`aiawa_workflow_runs`, `aiawa_workflows`,
 * etc.) is GMT — see `current_time( 'mysql', true )` at each insert site.
 * Historically this class's callers always converted that to the site's
 * local timezone for display via `get_date_from_gmt()`; the "General" tab
 * of the Settings screen (roadmap item 10) now lets a site choose to
 * display raw UTC instead — useful for teams comparing logs against
 * externally-hosted services that report in UTC. Kept as one static
 * helper, like RunStatusBadge/RunDuration, rather than duplicating the
 * branch at every call site (RunsListTable, RunDetailPage,
 * WorkflowsListTable).
 */
class RunTimestamp {

	/**
	 * @param string|null $gmt_datetime   MySQL datetime string in GMT, or null.
	 * @param bool        $display_in_utc From SettingsService::displayTimestampsInUtc().
	 *
	 * @return string Already safe to echo without further escaping is NOT guaranteed — callers must still esc_html() this, same as any other translatable/formatted string.
	 */
	public static function format( ?string $gmt_datetime, bool $display_in_utc ): string {
		if ( null === $gmt_datetime || '' === $gmt_datetime ) {
			return '';
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		if ( ! $display_in_utc ) {
			return get_date_from_gmt( $gmt_datetime, $format );
		}

		$timestamp = strtotime( $gmt_datetime . ' UTC' );

		if ( false === $timestamp ) {
			return '';
		}

		return sprintf(
			/* translators: %s: formatted date/time. */
			__( '%s UTC', 'workflow-automate' ),
			gmdate( $format, $timestamp )
		);
	}
}
