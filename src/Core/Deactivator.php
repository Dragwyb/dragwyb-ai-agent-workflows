<?php
/**
 * Plugin deactivation handler.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

use WorkflowAutomate\Plugin\Service\BackgroundRunner;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is deactivated.
 *
 * Deactivation never deletes plugin data (see Uninstaller for the opt-in
 * data removal flow); it only clears the recurring cron event so a
 * deactivated plugin does not keep waking WP-Cron up every minute. Any
 * runs already `queued` are left as-is — they simply resume processing if
 * the plugin is reactivated later, no different from any other pending
 * plugin data.
 */
class Deactivator {

	/**
	 * Deactivation callback registered via register_deactivation_hook().
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( BackgroundRunner::CRON_HOOK );
	}
}
