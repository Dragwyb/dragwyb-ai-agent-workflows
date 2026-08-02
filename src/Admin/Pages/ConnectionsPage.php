<?php
/**
 * Connections admin page.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin\Pages;

use AIAWA\Plugin\Admin\AdminPage;
use AIAWA\Plugin\Admin\ConnectionActionsController;
use AIAWA\Plugin\Admin\ConnectionsListTable;
use AIAWA\Plugin\Admin\EmptyState;
use AIAWA\Plugin\Admin\ListTableUi;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\SettingsService;

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

	public const SLUG = 'aiawa-connections';

	private ConnectionService $connections;

	private SettingsService $settings;

	private ConnectionActionsController $connectionActions;

	public function __construct( ConnectionService $connections, SettingsService $settings, ConnectionActionsController $connectionActions ) {
		$this->connections       = $connections;
		$this->settings          = $settings;
		$this->connectionActions = $connectionActions;
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
		return __( 'Connections', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Connections', 'ai-agent-workflow-automation' );
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
			wp_die( esc_html__( 'You are not allowed to access this page.', 'ai-agent-workflow-automation' ) );
		}

		$table = new ConnectionsListTable( $this->connections, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap aiawa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . ConnectionFormPage::SLUG ) ),
			esc_html__( 'Add New', 'ai-agent-workflow-automation' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Credentials stored here are encrypted at rest and never displayed in full once saved.', 'ai-agent-workflow-automation' ) . '</p>';

		if ( ! $table->has_items() ) {
			EmptyState::render(
				__( 'No connections yet', 'ai-agent-workflow-automation' ),
				__( 'Store API keys and other credentials here, then pick them from an HTTP Request action in the workflow editor.', 'ai-agent-workflow-automation' ),
				array(),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . ConnectionFormPage::SLUG ),
						'label'   => __( 'Add connection', 'ai-agent-workflow-automation' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		echo '<form method="get" class="aiawa-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		ListTableUi::openBulkForm( $this->slug(), 'aiawa_connection_bulk_action', 'aiawa_connection_bulk' );
		ListTableUi::renderPreservedFilters( $table->preservedFilters() );
		$table->display();
		ListTableUi::closeBulkForm();

		$table->renderRowActionForms();

		echo '</div>';
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?aiawa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'created'      => array(
				'message' => __( 'Connection created.', 'ai-agent-workflow-automation' ),
				'type'    => 'success',
			),
			'updated'      => array(
				'message' => __( 'Connection updated.', 'ai-agent-workflow-automation' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Connection deleted.', 'ai-agent-workflow-automation' ),
				'type'    => 'success',
			),
			'bulk_deleted' => array(
				'message' => __( 'Selected connections deleted.', 'ai-agent-workflow-automation' ),
				'type'    => 'success',
			),
			'error'        => array(
				'message' => __( 'That connection action could not be completed. Double-check the required fields and try again.', 'ai-agent-workflow-automation' ),
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

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p>%3$s</div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $notices[ $key ]['message'] ),
			wp_kses_post( $this->noticeDetailHtml() )
		);
	}

	/**
	 * @return string
	 */
	private function noticeDetailHtml(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display detail from a prior redirect.
		$detail = isset( $_GET['aiawa_error'] ) ? sanitize_text_field( wp_unslash( $_GET['aiawa_error'] ) ) : '';

		if ( '' === $detail ) {
			return '';
		}

		return sprintf( '<p>%s</p>', esc_html( $detail ) );
	}
}
