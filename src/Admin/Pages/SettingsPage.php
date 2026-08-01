<?php
/**
 * Settings admin page.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin\Pages;

use AIAWA\Plugin\Admin\AdminPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Service\SettingsService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three-tab settings screen (roadmap item 10): General, Logging & Retention,
 * Advanced. Deliberately built as a plain server-rendered admin page with
 * its own `admin-post.php` controller (`SettingsController`) rather than
 * the native WordPress Settings API that `docs/internal/architecture.md`
 * §2.5 originally proposed for it — see that section's "Implementation
 * note (roadmap item 10)" for the reasoning behind that deviation. Every
 * other state-changing admin screen in this plugin (WorkflowsListTable,
 * RunsListTable) already uses this exact controller pattern, so this page
 * follows it too rather than introducing a second, different mechanism for
 * saving data.
 *
 * The "Security/API Keys" tab `docs/internal/architecture.md` §2.5
 * describes is deliberately not built here, even after roadmap item 11
 * (Connections) shipped: that bullet's entire purpose was "an entry point
 * into connection management," and `ConnectionsPage` is now its own
 * top-level admin menu entry (see `Core\Plugin::registerAdmin()`) — a
 * Settings tab that just links to a page already in the main plugin menu
 * would be a redundant second entry point to the exact same screen, not a
 * distinct settings group. See `docs/internal/architecture.md` §2.5's
 * "Implementation note (roadmap item 11)" for the full reasoning.
 */
class SettingsPage implements AdminPage {

	public const SLUG = 'aiawa-settings';

	private const TABS = array( 'general', 'retention', 'advanced' );

	private const DEFAULT_TAB = 'general';

	private SettingsService $settings;

	public function __construct( SettingsService $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pageTitle(): string {
		return __( 'Workflow Automate Settings', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Settings', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_SETTINGS;
	}

	/**
	 * {@inheritDoc}
	 */
	public function showInMenu(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueueAssets(): void {
		wp_enqueue_style(
			'aiawa-admin',
			AIAWA_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			AIAWA_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ) );
		}

		$tab = $this->currentTab();

		echo '<div class="wrap aiawa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();
		$this->renderTabs( $tab );

		switch ( $tab ) {
			case 'retention':
				$this->renderRetentionTab();
				break;

			case 'advanced':
				$this->renderAdvancedTab();
				break;

			default:
				$this->renderGeneralTab();
				break;
		}

		echo '</div>';
	}

	/**
	 * @return string One of self::TABS.
	 */
	private function currentTab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector, not a state change.
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::DEFAULT_TAB;

		return in_array( $requested, self::TABS, true ) ? $requested : self::DEFAULT_TAB;
	}

	/**
	 * @param string $current Currently active tab.
	 *
	 * @return void
	 */
	private function renderTabs( string $current ): void {
		$labels = array(
			'general'   => __( 'General', 'workflow-automate' ),
			'retention' => __( 'Logging & Retention', 'workflow-automate' ),
			'advanced'  => __( 'Advanced', 'workflow-automate' ),
		);

		echo '<h2 class="nav-tab-wrapper">';

		foreach ( $labels as $tab => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			);
			$class = $tab === $current ? 'nav-tab nav-tab-active' : 'nav-tab';

			printf( '<a href="%1$s" class="%2$s">%3$s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}

		echo '</h2>';
	}

	/**
	 * @return void
	 */
	private function renderGeneralTab(): void {
		$on_failure  = $this->settings->onNodeFailure();
		$display_utc = $this->settings->displayTimestampsInUtc();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-settings-form">';
		echo '<input type="hidden" name="action" value="aiawa_settings_action" />';
		echo '<input type="hidden" name="op" value="general" />';
		wp_nonce_field( 'aiawa_settings_action_general' );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'When a node fails', 'workflow-automate' ) . '</th><td>';
		printf(
			'<label><input type="radio" name="on_node_failure" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( SettingsService::ON_FAILURE_STOP ),
			checked( SettingsService::ON_FAILURE_STOP, $on_failure, false ),
			esc_html__( 'Stop the run at the first failing node (recommended)', 'workflow-automate' )
		);
		printf(
			'<label><input type="radio" name="on_node_failure" value="%1$s" %2$s /> %3$s</label>',
			esc_attr( SettingsService::ON_FAILURE_CONTINUE ),
			checked( SettingsService::ON_FAILURE_CONTINUE, $on_failure, false ),
			esc_html__( 'Continue running the remaining nodes', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'Applies to every workflow; there is currently no per-workflow override.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Timestamps', 'workflow-automate' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="display_timestamps_in_utc" value="1" %1$s /> %2$s</label>',
			checked( true, $display_utc, false ),
			esc_html__( 'Display run and workflow timestamps in UTC instead of this site\'s local timezone', 'workflow-automate' )
		);
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save General Settings', 'workflow-automate' ) );
		echo '</form>';
	}

	/**
	 * @return void
	 */
	private function renderRetentionTab(): void {
		$days = $this->settings->retentionDays();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-settings-form">';
		echo '<input type="hidden" name="action" value="aiawa_settings_action" />';
		echo '<input type="hidden" name="op" value="retention" />';
		wp_nonce_field( 'aiawa_settings_action_retention' );

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="aiawa-retention-days">' . esc_html__( 'Keep finished run history for', 'workflow-automate' ) . '</label></th><td>';
		printf(
			'<input type="number" id="aiawa-retention-days" name="retention_days" min="%1$d" max="%2$d" value="%3$d" class="small-text" /> %4$s',
			(int) SettingsService::MIN_RETENTION_DAYS,
			(int) SettingsService::MAX_RETENTION_DAYS,
			(int) $days,
			esc_html__( 'days', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'A daily background job automatically removes finished runs (and their logs) older than this. Set to 0 to keep history forever.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save Retention Settings', 'workflow-automate' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Purge now', 'workflow-automate' ) . '</h2>';
		echo '<p>' . esc_html__( 'Immediately deletes finished runs older than the retention period above, instead of waiting for the next daily cleanup.', 'workflow-automate' ) . '</p>';

		if ( $days <= 0 ) {
			echo '<p><em>' . esc_html__( 'Retention is set to "keep forever", so there is nothing to purge.', 'workflow-automate' ) . '</em></p>';

			return;
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="aiawa_settings_action" />';
		echo '<input type="hidden" name="op" value="purge_now" />';
		wp_nonce_field( 'aiawa_settings_action_purge_now' );
		submit_button( __( 'Purge Now', 'workflow-automate' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * @return void
	 */
	private function renderAdvancedTab(): void {
		$background_enabled      = $this->settings->backgroundExecutionEnabled();
		$require_webhook_signing = $this->settings->requireWebhookSigning();
		$remove_data             = $this->settings->removeDataOnUninstall();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-settings-form">';
		echo '<input type="hidden" name="action" value="aiawa_settings_action" />';
		echo '<input type="hidden" name="op" value="advanced" />';
		wp_nonce_field( 'aiawa_settings_action_advanced' );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Background execution', 'workflow-automate' ) . '</th><td>';
		// Hidden fallback ensures the field is always present in $_POST
		// even when the checkbox is unchecked, so SettingsController can
		// tell "left checked" apart from "explicitly unchecked" instead of
		// only ever seeing the field when it is checked.
		echo '<input type="hidden" name="background_execution_enabled" value="0" />';
		printf(
			'<label><input type="checkbox" name="background_execution_enabled" value="1" %1$s /> %2$s</label>',
			checked( true, $background_enabled, false ),
			esc_html__( 'Run live-triggered workflows in the background via WP-Cron', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'Recommended. Disabling this runs triggered workflows immediately, on the same request that fired them — only useful on hosts where WP-Cron is unreliable or disabled.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Webhook signing', 'workflow-automate' ) . '</th><td>';
		echo '<input type="hidden" name="require_webhook_signing" value="0" />';
		printf(
			'<label><input type="checkbox" name="require_webhook_signing" value="1" %1$s /> %2$s</label>',
			checked( true, $require_webhook_signing, false ),
			esc_html__( 'Require a signing secret on every inbound webhook', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, webhooks without a signing secret cannot be created or called. Individual webhooks can still require signing when this is off.', 'workflow-automate' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Save Advanced Settings', 'workflow-automate' ) );
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Uninstall', 'workflow-automate' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-settings-form aiawa-settings-danger-zone">';
		echo '<input type="hidden" name="action" value="aiawa_settings_action" />';
		echo '<input type="hidden" name="op" value="uninstall" />';
		wp_nonce_field( 'aiawa_settings_action_uninstall' );

		echo '<p><strong>' . esc_html__( 'This plugin keeps all of its data when deleted, by default.', 'workflow-automate' ) . '</strong></p>';
		echo '<input type="hidden" name="remove_data_on_uninstall" value="0" />';
		printf(
			'<label><input type="checkbox" name="remove_data_on_uninstall" value="1" %1$s /> %2$s</label>',
			checked( true, $remove_data, false ),
			esc_html__( 'Permanently delete all workflows, runs, logs, and settings when this plugin is deleted', 'workflow-automate' )
		);
		echo '<p class="description">' . esc_html__( 'This only takes effect when the plugin is deleted from the Plugins screen, not on deactivation.', 'workflow-automate' ) . '</p>';
		submit_button( __( 'Save Uninstall Setting', 'workflow-automate' ), 'delete' );
		echo '</form>';
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?aiawa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'saved'  => array(
				'message' => __( 'Settings saved.', 'workflow-automate' ),
				'type'    => 'success',
			),
			// The generic message here is only a fallback; renderNotice()
			// always replaces it with a count-specific one via _n().
			'purged' => array(
				'message' => __( 'Old runs purged.', 'workflow-automate' ),
				'type'    => 'success',
			),
			'error'  => array(
				'message' => __( 'Your settings could not be saved.', 'workflow-automate' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['aiawa_notice'] ) ? sanitize_key( wp_unslash( $_GET['aiawa_notice'] ) ) : '';
		$notices = $this->notices();

		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}

		$message = $notices[ $key ]['message'];

		if ( 'purged' === $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display count; not used for any decision, only appended to a message.
			$count   = isset( $_GET['count'] ) ? absint( wp_unslash( $_GET['count'] ) ) : 0;
			$message = sprintf(
				/* translators: %d: number of runs deleted. */
				_n( 'Purged %d old run.', 'Purged %d old runs.', $count, 'workflow-automate' ),
				$count
			);
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $message )
		);
	}
}
