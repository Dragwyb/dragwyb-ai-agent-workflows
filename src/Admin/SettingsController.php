<?php
/**
 * Handles state-changing Settings admin actions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Admin\Pages\SettingsPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Service\RunRetentionService;
use AIAWA\Plugin\Service\SettingsService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin_post.php?action=aiawa_settings_action` POST submitted
 * by each of SettingsPage's per-tab forms.
 *
 * One op per form, matching one SettingsService method each, rather than
 * a single "save everything" op: each tab only ever submits its own
 * fields, so a shared op would have to guess which fields are actually
 * present versus just unchecked (see SettingsPage's hidden-fallback-input
 * comments for the specific checkbox gotcha this sidesteps).
 */
class SettingsController {

	private const ALLOWED_OPS = array( 'general', 'retention', 'advanced', 'uninstall', 'purge_now' );

	private SettingsService $settings;

	private RunRetentionService $retention;

	public function __construct( SettingsService $settings, RunRetentionService $retention ) {
		$this->settings  = $settings;
		$this->retention = $retention;
	}

	/**
	 * Hooks the admin-post handler. There is no `admin_post_nopriv_*`
	 * counterpart: this action is never valid for a logged-out visitor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_aiawa_settings_action', array( $this, 'handle' ) );
	}

	/**
	 * Processes the request, then redirects back to the Settings screen.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ai-agent-workflow-automation' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation.
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) ) {
			$this->redirect( 'general', 'error' );
		}

		check_admin_referer( 'aiawa_settings_action_' . $op );

		switch ( $op ) {
			case 'general':
				$this->handleGeneral();
				break;

			case 'retention':
				$this->handleRetention();
				break;

			case 'advanced':
				$this->handleAdvanced();
				break;

			case 'uninstall':
				$this->handleUninstall();
				break;

			case 'purge_now':
				$this->handlePurgeNow();
				break;
		}
	}

	/**
	 * @return void
	 */
	private function handleGeneral(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$on_node_failure = isset( $_POST['on_node_failure'] ) ? sanitize_key( wp_unslash( $_POST['on_node_failure'] ) ) : SettingsService::ON_FAILURE_STOP;
		// A checkbox absent from $_POST means "unchecked"; there is no hidden fallback input for this one since it is the only checkbox on its form (see SettingsPage::renderGeneralTab()), so its own array key simply exists in $_POST regardless, guaranteeing this op always runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$display_timestamps_in_utc = ! empty( $_POST['display_timestamps_in_utc'] );

		$this->settings->updateGeneral( $on_node_failure, $display_timestamps_in_utc );

		$this->redirect( 'general', 'saved' );
	}

	/**
	 * @return void
	 */
	private function handleRetention(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$days = isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : SettingsService::DEFAULT_RETENTION_DAYS;

		$this->settings->updateRetentionDays( $days );

		$this->redirect( 'retention', 'saved' );
	}

	/**
	 * @return void
	 */
	private function handleAdvanced(): void {
		// The hidden fallback inputs in SettingsPage::renderAdvancedTab() guarantee these keys are always present, checked or not.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$background_execution_enabled = ! empty( $_POST['background_execution_enabled'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$require_webhook_signing = ! empty( $_POST['require_webhook_signing'] );

		$this->settings->updateBackgroundExecutionEnabled( $background_execution_enabled );
		$this->settings->updateRequireWebhookSigning( $require_webhook_signing );

		$this->redirect( 'advanced', 'saved' );
	}

	/**
	 * @return void
	 */
	private function handleUninstall(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$remove_data_on_uninstall = ! empty( $_POST['remove_data_on_uninstall'] );

		$this->settings->updateRemoveDataOnUninstall( $remove_data_on_uninstall );

		$this->redirect( 'advanced', 'saved' );
	}

	/**
	 * @return void
	 */
	private function handlePurgeNow(): void {
		$count = $this->retention->pruneAccordingToSettings();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => SettingsPage::SLUG,
					'tab'        => 'retention',
					'aiawa_notice' => 'purged',
					'count'      => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Redirects to a Settings tab with a notice, then exits.
	 *
	 * @param string $tab    One of SettingsPage's tab slugs.
	 * @param string $notice One of the keys understood by SettingsPage::notices().
	 *
	 * @return void
	 */
	private function redirect( string $tab, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => SettingsPage::SLUG,
					'tab'        => $tab,
					'aiawa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
