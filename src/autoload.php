<?php
/**
 * Dependency-free PSR-4 autoloader fallback.
 *
 * Used only when no Composer-generated `vendor/autoload.php` is present.
 * Maps the `WorkflowAutomate\Plugin\` namespace prefix to this directory,
 * following the standard PSR-4 file resolution algorithm.
 *
 * @package WorkflowAutomate\Plugin
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		$prefix = 'WorkflowAutomate\\Plugin\\';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = __DIR__ . DIRECTORY_SEPARATOR . $relative_path;

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
