<?php
/**
 * Plugin Name:       Workflow Automate
 * Plugin URI:        https://example.com/workflow-automate
 * Description:       Build and run visual, multi-step automation workflows in WordPress.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Workflow Automate
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       workflow-automate
 * Domain Path:       /languages
 *
 * @package WorkflowAutomate\Plugin
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Version gate.
 *
 * This block intentionally avoids any PHP 7.4+ syntax (typed properties, arrow
 * functions, etc.) so that it can still run - and fail gracefully - on PHP
 * versions older than the plugin's stated minimum. Nothing below this gate is
 * loaded unless both the PHP and WordPress version requirements are met.
 */
if ( ! defined( 'WFA_MIN_PHP_VERSION' ) ) {
	define( 'WFA_MIN_PHP_VERSION', '7.4' );
}

if ( ! defined( 'WFA_MIN_WP_VERSION' ) ) {
	define( 'WFA_MIN_WP_VERSION', '5.8' );
}

if ( version_compare( PHP_VERSION, WFA_MIN_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'wfa_php_version_notice' );

	return;
}

/**
 * Prints an admin notice when the active PHP version is too old.
 *
 * Defined as a plain function (not a class method) so it can never be the
 * cause of the fatal error it is meant to report.
 */
function wfa_php_version_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Workflow Automate requires PHP %1$s or higher. Your site is running PHP %2$s. Please ask your host to upgrade PHP, then reactivate the plugin.', 'workflow-automate' ),
				WFA_MIN_PHP_VERSION,
				PHP_VERSION
			)
		)
	);
}

define( 'WFA_VERSION', '0.1.0' );
define( 'WFA_PLUGIN_FILE', __FILE__ );
define( 'WFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*
 * Autoloading.
 *
 * Prefer a Composer-generated autoloader when one is present (e.g. in a CI
 * environment or a developer machine with Composer installed). Fall back to
 * the dependency-free PSR-4 autoloader shipped with the plugin otherwise, so
 * the plugin remains fully functional without requiring a Composer install
 * step. See README.md for details on this trade-off.
 */
if ( file_exists( WFA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WFA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require_once WFA_PLUGIN_DIR . 'src/autoload.php';
}

register_activation_hook( WFA_PLUGIN_FILE, array( 'WorkflowAutomate\\Plugin\\Core\\Activator', 'activate' ) );
register_deactivation_hook( WFA_PLUGIN_FILE, array( 'WorkflowAutomate\\Plugin\\Core\\Deactivator', 'deactivate' ) );

WorkflowAutomate\Plugin\Core\Plugin::instance()->boot();
