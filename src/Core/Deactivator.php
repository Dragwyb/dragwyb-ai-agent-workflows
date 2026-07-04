<?php
/**
 * Plugin deactivation handler.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

use WorkflowAutomate\Plugin\Service\BackgroundRunner;
use WorkflowAutomate\Plugin\Service\RunRetentionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is deactivated.
 *
 * Deactivation never deletes plugin data (see Uninstaller for the opt-in
 * data removal flow); it only clears this plugin's recurring cron events
 * (the background execution queue and the retention-pruning job) so a
 * deactivated plugin does not keep waking WP-Cron up. Any runs already
 * `queued` are left as-is — they simply resume processing if the plugin
 * is reactivated later, no different from any other pending plugin data.
 */
class Deactivator {

	/**
	 * Deactivation callback registered via register_deactivation_hook().
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( BackgroundRunner::CRON_HOOK );
		wp_clear_scheduled_hook( RunRetentionService::CRON_HOOK );
	}
}
