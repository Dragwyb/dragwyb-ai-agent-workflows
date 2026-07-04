<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package WorkflowAutomate\Plugin
 */

// If this file is called directly and not by WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/autoload.php';
}

WorkflowAutomate\Plugin\Core\Uninstaller::uninstall();
