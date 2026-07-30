<?php
/**
 * Plugin management catalog definitions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress\Catalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WordPress Action Catalog definitions for Plugin Management.
 */
final class PluginActionCatalog {

	/**
	 * @param callable(string, string, array<string, mixed>=): array<string, mixed> $field
	 * @param callable(string, string): array{value: string, label: string}         $option
	 * @param array<string, string>                                                 $groups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions( callable $field, callable $option, array $groups ): array {
		$definitions = array();

		$definitions[] = array(
			'slug'          => 'wp_check_plugin_activation_status_action',
			'label'         => __( 'Check Plugin Activation Status', 'workflow-automate' ),
			'description'   => __( 'Checks whether a plugin is currently active.', 'workflow-automate' ),
			'group'         => 'plugin',
			'group_label'   => $groups['plugin'],
			'method'        => 'checkPluginActivationStatus',
			'method_args'   => array(),
			'config_schema' => array(
				'plugin_file' => $field( 'string', __( 'Plugin File (e.g. akismet/akismet.php)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_activate_plugin_action',
			'label'         => __( 'Activate Plugin', 'workflow-automate' ),
			'description'   => __( 'Activates an installed but inactive plugin.', 'workflow-automate' ),
			'group'         => 'plugin',
			'group_label'   => $groups['plugin'],
			'method'        => 'activatePlugin',
			'method_args'   => array(),
			'config_schema' => array(
				'plugin_file' => $field( 'string', __( 'Plugin File (e.g. akismet/akismet.php)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
