<?php
/**
 * Plugin activation handler.
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
 * Runs once when the plugin is activated.
 *
 * Responsible only for activation-time concerns: verifying the environment
 * and stamping installation metadata. Database schema creation is introduced
 * in a later roadmap increment and will be invoked from here once it exists.
 */
class Activator {

	/**
	 * Activation callback registered via register_activation_hook().
	 *
	 * @return void
	 */
	public static function activate(): void {
		$requirements = Requirements::check();

		if ( is_wp_error( $requirements ) ) {
			deactivate_plugins( WFA_PLUGIN_BASENAME );

			wp_die(
				esc_html( implode( ' ', $requirements->get_error_messages() ) ),
				esc_html__( 'Plugin activation error', 'workflow-automate' ),
				array( 'back_link' => true )
			);
		}

		if ( false === Options::get( 'installed_at' ) ) {
			Options::add( 'installed_at', time(), true );
		}

		Options::update( 'db_version', WFA_VERSION );
	}
}
