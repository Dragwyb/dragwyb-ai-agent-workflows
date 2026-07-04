<?php
/**
 * Runtime environment requirement checks.
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
 * Verifies the site meets the plugin's minimum PHP and WordPress versions.
 *
 * The PHP version gate in the main plugin file already protects against
 * fatal parse errors on unsupported PHP. This class re-checks at activation
 * time (where WordPress core version is reliably available) so activation
 * can be safely aborted with a clear message instead of leaving the site in
 * a broken state.
 */
class Requirements {

	/**
	 * Checks whether the current environment satisfies the plugin's requirements.
	 *
	 * @return true|\WP_Error True if requirements are met, otherwise a WP_Error describing what failed.
	 */
	public static function check() {
		global $wp_version;

		$errors = new \WP_Error();

		if ( version_compare( PHP_VERSION, WFA_MIN_PHP_VERSION, '<' ) ) {
			$errors->add(
				'wfa_php_version',
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'Workflow Automate requires PHP %1$s or higher. Your site is running PHP %2$s.', 'workflow-automate' ),
					WFA_MIN_PHP_VERSION,
					PHP_VERSION
				)
			);
		}

		if ( isset( $wp_version ) && version_compare( $wp_version, WFA_MIN_WP_VERSION, '<' ) ) {
			$errors->add(
				'wfa_wp_version',
				sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'Workflow Automate requires WordPress %1$s or higher. Your site is running WordPress %2$s.', 'workflow-automate' ),
					WFA_MIN_WP_VERSION,
					$wp_version
				)
			);
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}
}
