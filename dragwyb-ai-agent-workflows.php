<?php
/**
 * Plugin Name:       Dragwyb AI Agent Workflows
 * Plugin URI:        https://dragwyb.com/product/ai-workflows/
 * Description:       Build and run visual multi-step automation workflows, webhooks, and AI agent actions in WordPress.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Dragwyb
 * Author URI:        https://dragwyb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dragwyb-ai-agent-workflows
 * Domain Path:       /languages
 *
 * @package DragwybVisualAutomation\Plugin
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
if ( ! defined( 'DAIAW_MIN_PHP_VERSION' ) ) {
	define( 'DAIAW_MIN_PHP_VERSION', '7.4' );
}

if ( ! defined( 'DAIAW_MIN_WP_VERSION' ) ) {
	define( 'DAIAW_MIN_WP_VERSION', '5.8' );
}

if ( version_compare( PHP_VERSION, DAIAW_MIN_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', 'daiaw_php_version_notice' );

	return;
}

/**
 * Prints an admin notice when the active PHP version is too old.
 *
 * Defined as a plain function (not a class method) so it can never be the
 * cause of the fatal error it is meant to report.
 */
function daiaw_php_version_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Dragwyb AI Agent Workflows requires PHP %1$s or higher. Your site is running PHP %2$s. Please ask your host to upgrade PHP, then reactivate the plugin.', 'dragwyb-ai-agent-workflows' ),
				DAIAW_MIN_PHP_VERSION,
				PHP_VERSION
			)
		)
	);
}

define( 'DAIAW_VERSION', '0.1.0' );
define( 'DAIAW_PLUGIN_FILE', __FILE__ );
define( 'DAIAW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DAIAW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DAIAW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DAIAW_PLUGIN_DIR . 'src/Core/WordPressCompat.php';

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
$daiaw_has_core_ai_client = daiaw_has_core_ai_client();

if ( $daiaw_has_core_ai_client ) {
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
	require_once DAIAW_PLUGIN_DIR . 'src/autoload.php';
} elseif ( file_exists( DAIAW_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	// Pre-WP 7: load the full vendored SDK (php-ai-client + HTTP/PSR deps).
	require_once DAIAW_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require_once DAIAW_PLUGIN_DIR . 'src/autoload.php';
}

// AI provider packages (official + custom) extend core's SDK; load them either way.
if ( file_exists( DAIAW_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php' ) ) {
	require_once DAIAW_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php';
}

register_activation_hook( DAIAW_PLUGIN_FILE, array( 'DragwybVisualAutomation\\Plugin\\Core\\Activator', 'activate' ) );
register_deactivation_hook( DAIAW_PLUGIN_FILE, array( 'DragwybVisualAutomation\\Plugin\\Core\\Deactivator', 'deactivate' ) );

DragwybVisualAutomation\Plugin\Core\Plugin::instance()->boot();
