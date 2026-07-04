<?php
/**
 * Plugin deactivation handler.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the plugin is deactivated.
 *
 * Deactivation never deletes plugin data (see Uninstaller for the opt-in
 * data removal flow). This class currently has nothing to clean up because
 * no scheduled events or rewrite rules exist yet; later increments that add
 * WP-Cron events or rewrite rules must extend this method to clear them.
 */
class Deactivator {

	/**
	 * Deactivation callback registered via register_deactivation_hook().
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Intentionally empty for this increment. See class docblock.
	}
}
