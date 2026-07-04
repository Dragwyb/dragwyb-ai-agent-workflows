<?php
/**
 * Connections admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Admin\ConnectionsListTable;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\SettingsService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level list of stored third-party credentials (roadmap item 11).
 * Also serves as the "entry point into connection management" that
 * `docs/internal/architecture.md` §2.5 originally described as a Settings
 * screen tab — see `SettingsPage`'s class docblock for why that tab was
 * not built once this page (reachable from the plugin's own admin menu
 * either way) existed. Creating and editing a connection's actual
 * credential fields happens on `ConnectionFormPage`; this screen only
 * ever links out to it via "Add New" and "Edit".
 */
class ConnectionsPage implements AdminPage {

	public const SLUG = 'wfa-connections';

	private ConnectionService $connections;

	private SettingsService $settings;

	public function __construct( ConnectionService $connections, SettingsService $settings ) {
		$this->connections = $connections;
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
		return __( 'Connections', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Connections', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_CONNECTIONS;
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
			'wfa-admin',
			WFA_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			WFA_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ) );
		}

		$table = new ConnectionsListTable( $this->connections, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap wfa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . ConnectionFormPage::SLUG ) ),
			esc_html__( 'Add New', 'workflow-automate' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Credentials stored here are encrypted at rest and never displayed in full once saved.', 'workflow-automate' ) . '</p>';

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?wfa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'created' => array(
				'message' => __( 'Connection created.', 'workflow-automate' ),
				'type' => 'success',
			),
			'updated' => array(
				'message' => __( 'Connection updated.', 'workflow-automate' ),
				'type' => 'success',
			),
			'deleted' => array(
				'message' => __( 'Connection deleted.', 'workflow-automate' ),
				'type' => 'success',
			),
			'error' => array(
				'message' => __( 'That connection action could not be completed. Double-check the required fields and try again.', 'workflow-automate' ),
				'type' => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key = isset( $_GET['wfa_notice'] ) ? sanitize_key( wp_unslash( $_GET['wfa_notice'] ) ) : '';
		$notices = $this->notices();

		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $notices[ $key ]['message'] )
		);
	}
}
