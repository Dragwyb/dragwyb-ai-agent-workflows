<?php
/**
 * Connections admin page.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Admin\Pages;

use DragwybAgentFlow\Plugin\Admin\AdminPage;
use DragwybAgentFlow\Plugin\Admin\ConnectionActionsController;
use DragwybAgentFlow\Plugin\Admin\ConnectionsListTable;
use DragwybAgentFlow\Plugin\Admin\EmptyState;
use DragwybAgentFlow\Plugin\Admin\ListTableUi;
use DragwybAgentFlow\Plugin\Core\Capabilities;
use DragwybAgentFlow\Plugin\Service\ConnectionService;
use DragwybAgentFlow\Plugin\Service\SettingsService;

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

	public const SLUG = 'dragwyb-af-connections';

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
		return __( 'Connections', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Connections', 'dragwyb-agentflow' );
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
			'dragwyb-af-admin',
			DRAGWYB_AF_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			DRAGWYB_AF_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-agentflow' ) );
		}

		$table = new ConnectionsListTable( $this->connections, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragwyb-af-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . ConnectionFormPage::SLUG ) ),
			esc_html__( 'Add New', 'dragwyb-agentflow' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Credentials stored here are encrypted at rest and never displayed in full once saved.', 'dragwyb-agentflow' ) . '</p>';

		if ( ! $table->has_items() ) {
			EmptyState::render(
				__( 'No connections yet', 'dragwyb-agentflow' ),
				__( 'Store API keys and other credentials here, then pick them from an HTTP Request action in the workflow editor.', 'dragwyb-agentflow' ),
				array(),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . ConnectionFormPage::SLUG ),
						'label'   => __( 'Add connection', 'dragwyb-agentflow' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		echo '<form method="get" class="dragwyb-af-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		ListTableUi::openBulkForm( $this->slug(), 'dragwyb_af_connection_bulk_action', 'dragwyb_af_connection_bulk' );
		ListTableUi::renderPreservedFilters( $table->preservedFilters() );
		$table->display();
		ListTableUi::closeBulkForm();

		$table->renderRowActionForms();

		echo '</div>';
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?dragwyb_af_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'created'      => array(
				'message' => __( 'Connection created.', 'dragwyb-agentflow' ),
				'type'    => 'success',
			),
			'updated'      => array(
				'message' => __( 'Connection updated.', 'dragwyb-agentflow' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Connection deleted.', 'dragwyb-agentflow' ),
				'type'    => 'success',
			),
			'bulk_deleted' => array(
				'message' => __( 'Selected connections deleted.', 'dragwyb-agentflow' ),
				'type'    => 'success',
			),
			'error'        => array(
				'message' => __( 'That connection action could not be completed. Double-check the required fields and try again.', 'dragwyb-agentflow' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['dragwyb_af_notice'] ) ? sanitize_key( wp_unslash( $_GET['dragwyb_af_notice'] ) ) : '';
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
		$detail = isset( $_GET['dragwyb_af_error'] ) ? sanitize_text_field( wp_unslash( $_GET['dragwyb_af_error'] ) ) : '';

		if ( '' === $detail ) {
			return '';
		}

		return sprintf( '<p>%s</p>', esc_html( $detail ) );
	}
}
