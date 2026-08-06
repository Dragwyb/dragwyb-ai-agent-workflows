<?php
/**
 * Webhooks admin page.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Admin\Pages;

use DragwybVisualAutomation\Plugin\Admin\AdminPage;
use DragwybVisualAutomation\Plugin\Admin\EmptyState;
use DragwybVisualAutomation\Plugin\Admin\ListTableUi;
use DragwybVisualAutomation\Plugin\Admin\WebhookActionsController;
use DragwybVisualAutomation\Plugin\Admin\WebhooksListTable;
use DragwybVisualAutomation\Plugin\Core\Capabilities;
use DragwybVisualAutomation\Plugin\Service\SettingsService;
use DragwybVisualAutomation\Plugin\Service\WebhookService;
use DragwybVisualAutomation\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level list of inbound webhook endpoints (roadmap item 13). Creating
 * and editing a webhook happens on `WebhookFormPage`; this screen only
 * ever links out to it via "Add New" and "Edit".
 */
class WebhooksPage implements AdminPage {

	public const SLUG = 'dragwyb-af-webhooks';

	private WebhookService $webhooks;

	private WorkflowService $workflows;

	private SettingsService $settings;

	private WebhookActionsController $webhookActions;

	public function __construct( WebhookService $webhooks, WorkflowService $workflows, SettingsService $settings, WebhookActionsController $webhookActions ) {
		$this->webhooks       = $webhooks;
		$this->workflows      = $workflows;
		$this->settings       = $settings;
		$this->webhookActions = $webhookActions;
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
		return __( 'Webhooks', 'dragwyb-visual-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Webhooks', 'dragwyb-visual-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_WEBHOOKS;
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
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-visual-automation' ) );
		}

		$table = new WebhooksListTable( $this->webhooks, $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragwyb-af-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ) ),
			esc_html__( 'Add New', 'dragwyb-visual-automation' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Public endpoints that start a workflow when an external service POSTs to them. Optional HMAC signing and IP allow-lists protect each endpoint.', 'dragwyb-visual-automation' ) . '</p>';

		if ( $this->settings->requireWebhookSigning() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Site settings currently require every webhook to use a signing secret.', 'dragwyb-visual-automation' ) . '</p></div>';
		}

		if ( ! $table->has_items() ) {
			EmptyState::render(
				__( 'No webhooks yet', 'dragwyb-visual-automation' ),
				__( 'Create a public URL that starts a workflow when an external service sends a POST request. You can require a signing secret and limit callers by IP.', 'dragwyb-visual-automation' ),
				array(),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ),
						'label'   => __( 'Add webhook', 'dragwyb-visual-automation' ),
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

		ListTableUi::openBulkForm( $this->slug(), 'dragwyb_af_webhook_bulk_action', 'dragwyb_af_webhook_bulk' );
		ListTableUi::renderPreservedFilters( $table->preservedFilters() );
		$table->display();
		ListTableUi::closeBulkForm();

		$table->renderRowActionForms();

		echo '</div>';
	}

	/**
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'created'      => array(
				'message' => __( 'Webhook created.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'updated'      => array(
				'message' => __( 'Webhook updated.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Webhook deleted.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'bulk_deleted' => array(
				'message' => __( 'Selected webhooks deleted.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'error'        => array(
				'message' => __( 'That webhook action could not be completed. Double-check the required fields and try again.', 'dragwyb-visual-automation' ),
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
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $notices[ $key ]['message'] )
		);
	}
}
