<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package AIAWAB\Plugin
 */

// If this file is called directly and not by WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * On WordPress 7+ the core `WordPress\AiClient\*` library is already loaded, so
 * we must not load our vendored SDK (it would shadow core's copy). Uninstall
 * only needs the plugin's own classes, so the lightweight PSR-4 autoloader is
 * sufficient there.
 */
require_once __DIR__ . '/src/Core/WordPressCompat.php';

$wfa_has_core_ai_client = wfa_has_core_ai_client();

if ( ! $wfa_has_core_ai_client && file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/autoload.php';
}

AIAWAB\Plugin\Core\Uninstaller::uninstall();
