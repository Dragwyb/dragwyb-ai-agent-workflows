<?php
/**
 * Business logic for WordPress Plugin management actions.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Integration\WordPress\Service;

use DRAGAIW\Plugin\Integration\WordPress\WordPressActionHelper;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin management domain service.
 */
final class PluginWordPressService {

	public function checkPluginActivationStatus( array $config ): array {
		$file = WordPressActionHelper::str( $config, 'plugin_file' );

		if ( '' === $file ) {
			return WordPressActionHelper::fail( __( 'Plugin file is required.', 'dragwyb-ai-agent-workflows' ) );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = is_plugin_active( $file );

		return WordPressActionHelper::ok(
			array(
				'plugin_file' => $file,
				'is_active'   => $active,
			)
		);
	}

	public function activatePlugin( array $config ): array {
		$file = WordPressActionHelper::str( $config, 'plugin_file' );

		if ( '' === $file ) {
			return WordPressActionHelper::fail( __( 'Plugin file is required.', 'dragwyb-ai-agent-workflows' ) );
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $file ) ) {
			return WordPressActionHelper::ok(
				array(
					'plugin_file'    => $file,
					'already_active' => true,
				)
			);
		}

		$result = activate_plugin( $file );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'plugin_file' => $file,
				'activated'   => true,
			)
		);
	}
}
