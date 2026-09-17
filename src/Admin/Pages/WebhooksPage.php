<?php
/**
 * Webhooks admin page.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Admin\Pages;

use DRAGAIW\Plugin\Admin\AdminPage;
use DRAGAIW\Plugin\Admin\EmptyState;
use DRAGAIW\Plugin\Admin\ListTableUi;
use DRAGAIW\Plugin\Admin\WebhookActionsController;
use DRAGAIW\Plugin\Admin\WebhooksListTable;
use DRAGAIW\Plugin\Core\Capabilities;
use DRAGAIW\Plugin\Service\SettingsService;
use DRAGAIW\Plugin\Service\WebhookService;
use DRAGAIW\Plugin\Service\WorkflowService;

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

	public const SLUG = 'dragaiw-webhooks';

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
		return __( 'Webhooks', 'dragwyb-ai-agent-workflows' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Webhooks', 'dragwyb-ai-agent-workflows' );
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
			'dragaiw-admin',
			DRAGAIW_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			DRAGAIW_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-ai-agent-workflows' ) );
		}

		$table = new WebhooksListTable( $this->webhooks, $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragaiw-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ) ),
			esc_html__( 'Add New', 'dragwyb-ai-agent-workflows' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Public endpoints that start a workflow when an external service POSTs to them. Optional HMAC signing and IP allow-lists protect each endpoint.', 'dragwyb-ai-agent-workflows' ) . '</p>';

		if ( $this->settings->requireWebhookSigning() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Site settings currently require every webhook to use a signing secret.', 'dragwyb-ai-agent-workflows' ) . '</p></div>';
		}

		if ( ! $table->has_items() ) {
			EmptyState::render(
				__( 'No webhooks yet', 'dragwyb-ai-agent-workflows' ),
				__( 'Create a public URL that starts a workflow when an external service sends a POST request. You can require a signing secret and limit callers by IP.', 'dragwyb-ai-agent-workflows' ),
				array(),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ),
						'label'   => __( 'Add webhook', 'dragwyb-ai-agent-workflows' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		echo '<form method="get" class="dragaiw-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		ListTableUi::openBulkForm( $this->slug(), 'dragaiw_webhook_bulk_action', 'dragaiw_webhook_bulk' );
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
				'message' => __( 'Webhook created.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'updated'      => array(
				'message' => __( 'Webhook updated.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Webhook deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'bulk_deleted' => array(
				'message' => __( 'Selected webhooks deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'error'        => array(
				'message' => __( 'That webhook action could not be completed. Double-check the required fields and try again.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['dragaiw_notice'] ) ? sanitize_key( wp_unslash( $_GET['dragaiw_notice'] ) ) : '';
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
