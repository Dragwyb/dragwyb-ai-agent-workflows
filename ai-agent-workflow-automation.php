<?php
/**
 * Plugin Name:       AI Agent & Workflow Automation Builder
 * Plugin URI:        https://dragwyb.com/ai-agent-workflow-automation
 * Description:       Build and run visual, multi-step automation workflows in WordPress.
 * Version:           0.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Dragwyb
 * Author URI:        https://dragwyb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-agent-workflow-automation
 * Domain Path:       /languages
 *
 * @package AIAWA\Plugin
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
if ( ! defined( 'AIAWA_MIN_PHP_VERSION' ) ) {
	define( 'AIAWA_MIN_PHP_VERSION', '7.4' );
}

if ( ! defined( 'AIAWA_MIN_WP_VERSION' ) ) {
	define( 'AIAWA_MIN_WP_VERSION', '5.8' );
}

if ( version_compare( PHP_VERSION, AIAWA_MIN_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'aiawa_php_version_notice' );

	return;
}

/**
 * Prints an admin notice when the active PHP version is too old.
 *
 * Defined as a plain function (not a class method) so it can never be the
 * cause of the fatal error it is meant to report.
 */
function aiawa_php_version_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Workflow Automate requires PHP %1$s or higher. Your site is running PHP %2$s. Please ask your host to upgrade PHP, then reactivate the plugin.', 'ai-agent-workflow-automation' ),
				AIAWA_MIN_PHP_VERSION,
				PHP_VERSION
			)
		)
	);
}

define( 'AIAWA_VERSION', '0.1.0' );
define( 'AIAWA_PLUGIN_FILE', __FILE__ );
define( 'AIAWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIAWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIAWA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once AIAWA_PLUGIN_DIR . 'src/Core/WordPressCompat.php';

/*
 * Autoloading.
 *
 * Prefer a Composer-generated autoloader when one is present (e.g. in a CI
 * environment or a developer machine with Composer installed). Fall back to
 * the dependency-free PSR-4 autoloader shipped with the plugin otherwise, so
 * the plugin remains fully functional without requiring a Composer install
 * step. See README.md for details on this trade-off.
 */
/*
 * Detect WordPress 7+ core AI Client.
 *
 * Core defines wp_ai_client_prompt() (in wp-includes/ai-client.php) before
 * plugins load, and ships its own bundled `WordPress\AiClient\*` library. At
 * this point our vendored SDK has NOT been loaded yet, so this check reliably
 * reflects core capabilities and cannot be tripped by our own polyfills.
 */
$aiawa_has_core_ai_client = aiawa_has_core_ai_client();

if ( $aiawa_has_core_ai_client ) {
	/*
	 * WordPress 7+: rely entirely on the core `WordPress\AiClient\*` library.
	 *
	 * We deliberately do NOT load the plugin's root Composer autoloader here,
	 * because it maps the `WordPress\AiClient\` prefix (and its HTTP/PSR
	 * dependencies) to our own vendored copy. Composer prepends its autoloader,
	 * so loading it would shadow core's bundled SDK with a different version and
	 * a different (unscoped) dependency set, breaking the HTTP transporter and
	 * authentication binding used to verify provider credentials.
	 *
	 * Instead we register only the plugin's own classes and the vendored AI
	 * provider packages, all of which extend core's `WordPress\AiClient\*`.
	 */
	require_once AIAWA_PLUGIN_DIR . 'src/autoload.php';
} elseif ( file_exists( AIAWA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	// Pre-WP 7: load the full vendored SDK (php-ai-client + HTTP/PSR deps).
	require_once AIAWA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require_once AIAWA_PLUGIN_DIR . 'src/autoload.php';
}

// AI provider packages (official + custom) extend core's SDK; load them either way.
if ( file_exists( AIAWA_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php' ) ) {
	require_once AIAWA_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php';
}

register_activation_hook( AIAWA_PLUGIN_FILE, array( 'AIAWA\\Plugin\\Core\\Activator', 'activate' ) );
register_deactivation_hook( AIAWA_PLUGIN_FILE, array( 'AIAWA\\Plugin\\Core\\Deactivator', 'deactivate' ) );

AIAWA\Plugin\Core\Plugin::instance()->boot();
