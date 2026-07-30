<?php
/**
 * Webhooks admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Admin\EmptyState;
use WorkflowAutomate\Plugin\Admin\ListTableUi;
use WorkflowAutomate\Plugin\Admin\WebhookActionsController;
use WorkflowAutomate\Plugin\Admin\WebhooksListTable;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Service\SettingsService;
use WorkflowAutomate\Plugin\Service\WebhookService;
use WorkflowAutomate\Plugin\Service\WorkflowService;

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

	public const SLUG = 'wfa-webhooks';

	private WebhookService $webhooks;

	private WorkflowService $workflows;

	private SettingsService $settings;

	private WebhookActionsController $webhookActions;

	public function __construct( WebhookService $webhooks, WorkflowService $workflows, SettingsService $settings, WebhookActionsController $webhookActions ) {
		$this->webhooks = $webhooks;
		$this->workflows = $workflows;
		$this->settings = $settings;
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
		return __( 'Webhooks', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Webhooks', 'workflow-automate' );
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

		$table = new WebhooksListTable( $this->webhooks, $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap wfa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ) ),
			esc_html__( 'Add New', 'workflow-automate' )
		);
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<p class="description">' . esc_html__( 'Public endpoints that start a workflow when an external service POSTs to them. Optional HMAC signing and IP allow-lists protect each endpoint.', 'workflow-automate' ) . '</p>';

		if ( $this->settings->requireWebhookSigning() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Site settings currently require every webhook to use a signing secret.', 'workflow-automate' ) . '</p></div>';
		}

		if ( ! $table->has_items() ) {
			EmptyState::render(
				__( 'No webhooks yet', 'workflow-automate' ),
				__( 'Create a public URL that starts a workflow when an external service sends a POST request. You can require a signing secret and limit callers by IP.', 'workflow-automate' ),
				array(),
				array(
					array(
						'url' => admin_url( 'admin.php?page=' . WebhookFormPage::SLUG ),
						'label' => __( 'Add webhook', 'workflow-automate' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		echo '<form method="get" class="wfa-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		ListTableUi::openBulkForm( $this->slug(), 'wfa_webhook_bulk_action', 'wfa_webhook_bulk' );
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
			'created' => array(
				'message' => __( 'Webhook created.', 'workflow-automate' ),
				'type' => 'success',
			),
			'updated' => array(
				'message' => __( 'Webhook updated.', 'workflow-automate' ),
				'type' => 'success',
			),
			'deleted' => array(
				'message' => __( 'Webhook deleted.', 'workflow-automate' ),
				'type' => 'success',
			),
			'bulk_deleted' => array(
				'message' => __( 'Selected webhooks deleted.', 'workflow-automate' ),
				'type' => 'success',
			),
			'error' => array(
				'message' => __( 'That webhook action could not be completed. Double-check the required fields and try again.', 'workflow-automate' ),
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
