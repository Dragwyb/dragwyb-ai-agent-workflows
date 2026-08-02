<?php
/**
 * Webhook create/edit admin page.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin\Pages;

use AIAWA\Plugin\Admin\AdminPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Domain\Webhook;
use AIAWA\Plugin\Domain\Workflow;
use AIAWA\Plugin\Service\SettingsService;
use AIAWA\Plugin\Service\WebhookService;
use AIAWA\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu-hidden create/edit screen for inbound webhooks, backed by
 * WebhookActionsController. Plain server-rendered HTML, same pattern as
 * ConnectionFormPage.
 */
class WebhookFormPage implements AdminPage {

	public const SLUG = 'aiawa-webhook-form';

	private WebhookService $webhooks;

	private WorkflowService $workflows;

	private SettingsService $settings;

	public function __construct( WebhookService $webhooks, WorkflowService $workflows, SettingsService $settings ) {
		$this->webhooks  = $webhooks;
		$this->workflows = $workflows;
		$this->settings  = $settings;
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
		return __( 'Webhook', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Webhook', 'ai-agent-workflow-automation' );
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
		return false;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which webhook to edit.
		$webhook_id = isset( $_GET['webhook'] ) ? absint( wp_unslash( $_GET['webhook'] ) ) : 0;

		echo '<div class="wrap aiawa-admin-page">';
		$this->renderBackLink();

		if ( $webhook_id > 0 ) {
			$webhook = $this->webhooks->find( $webhook_id );

			if ( null === $webhook ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'That webhook does not exist.', 'ai-agent-workflow-automation' ) . '</p></div>';
				echo '</div>';

				return;
			}

			echo '<h1>' . esc_html__( 'Edit Webhook', 'ai-agent-workflow-automation' ) . '</h1>';
			$this->renderEditForm( $webhook );
		} else {
			echo '<h1>' . esc_html__( 'Add New Webhook', 'ai-agent-workflow-automation' ) . '</h1>';
			$this->renderCreateForm();
		}

		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function renderBackLink(): void {
		printf(
			'<p><a href="%1$s">&larr; %2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . WebhooksPage::SLUG ) ),
			esc_html__( 'Back to Webhooks', 'ai-agent-workflow-automation' )
		);
	}

	/**
	 * @return void
	 */
	private function renderCreateForm(): void {
		$require_signing = $this->settings->requireWebhookSigning();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-webhook-form">';
		echo '<input type="hidden" name="action" value="aiawa_webhook_action" />';
		echo '<input type="hidden" name="op" value="create" />';
		wp_nonce_field( 'aiawa_webhook_action_create' );

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->renderWorkflowRow( 0 );
		$this->renderSigningSecretRow( null, $require_signing );
		$this->renderIpAllowListRow( array() );
		echo '</tbody></table>';

		submit_button( __( 'Create Webhook', 'ai-agent-workflow-automation' ) );
		echo '</form>';
	}

	/**
	 * @param Webhook $webhook Webhook being edited.
	 *
	 * @return void
	 */
	private function renderEditForm( Webhook $webhook ): void {
		$require_signing = $this->settings->requireWebhookSigning();
		$secret_display  = $this->webhooks->displaySigningSecret( $webhook );
		$webhook_id      = (int) $webhook->id();

		echo '<p class="description">' . esc_html__( 'Public URL (POST):', 'ai-agent-workflow-automation' ) . ' <code class="aiawa-webhook-url">' . esc_html( $this->webhooks->publicUrl( $webhook ) ) . '</code></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aiawa-webhook-form">';
		echo '<input type="hidden" name="action" value="aiawa_webhook_action" />';
		echo '<input type="hidden" name="op" value="update" />';
		printf( '<input type="hidden" name="webhook_id" value="%s" />', esc_attr( $webhook_id ) );
		wp_nonce_field( 'aiawa_webhook_action_update_' . $webhook_id );

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->renderWorkflowRow( (int) ( $webhook->workflowId() ?? 0 ) );
		$this->renderSigningSecretRow( $secret_display, $require_signing );
		$this->renderIpAllowListRow( $webhook->ipAllowList() );
		echo '</tbody></table>';

		submit_button( __( 'Update Webhook', 'ai-agent-workflow-automation' ) );
		echo '</form>';
	}

	/**
	 * @param int $selected_id Currently selected workflow id, or 0.
	 *
	 * @return void
	 */
	private function renderWorkflowRow( int $selected_id ): void {
		$workflows = $this->workflows->list(
			array(
				'page'     => 1,
				'per_page' => 100,
			)
		);

		echo '<tr><th scope="row"><label for="aiawa-webhook-workflow">' . esc_html__( 'Workflow', 'ai-agent-workflow-automation' ) . '</label></th><td>';
		echo '<select name="workflow_id" id="aiawa-webhook-workflow" required>';
		echo '<option value="">' . esc_html__( 'Select a workflow…', 'ai-agent-workflow-automation' ) . '</option>';

		foreach ( $workflows['items'] as $workflow ) {
			$workflow_id = (int) $workflow->id();
			printf(
				'<option value="%1$d" %2$s>%3$s%4$s</option>',
				esc_attr( $workflow_id ),
				esc_attr( selected( $selected_id, $workflow_id, false ) ),
				esc_html( $workflow->title() ),
				Workflow::STATUS_ACTIVE === $workflow->status()
					? ''
					: ' ' . esc_html__( '(inactive — activate it before callers can use this webhook)', 'ai-agent-workflow-automation' )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Only active workflows run when the webhook is called. Draft or paused workflows return an error to the caller.', 'ai-agent-workflow-automation' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param array{configured: bool, display: string}|null $secret_display Null on create.
	 * @param bool                                          $require_signing Whether site settings require a secret.
	 *
	 * @return void
	 */
	private function renderSigningSecretRow( ?array $secret_display, bool $require_signing ): void {
		echo '<tr><th scope="row"><label for="aiawa-webhook-signing-secret">' . esc_html__( 'Signing secret', 'ai-agent-workflow-automation' ) . '</label></th><td>';

		if ( null !== $secret_display && $secret_display['configured'] ) {
			printf(
				'<p class="aiawa-webhook-current-value">%1$s <code>%2$s</code></p>',
				esc_html__( 'Currently set:', 'ai-agent-workflow-automation' ),
				esc_html( $secret_display['display'] )
			);
		}

		printf(
			'<input type="text" class="regular-text" name="signing_secret" id="aiawa-webhook-signing-secret" value="" autocomplete="off" %1$s />',
			( $require_signing && ( null === $secret_display || ! $secret_display['configured'] ) ) ? 'required' : ''
		);

		if ( null === $secret_display ) {
			echo '<p class="description">' . esc_html__( 'Optional. When set, callers must send an X-aiawa-Signature header (sha256=… HMAC of the raw body). Leave blank for an unsigned webhook.', 'ai-agent-workflow-automation' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the current secret. Enter a new value to rotate it.', 'ai-agent-workflow-automation' ) . '</p>';

			if ( ! $require_signing && $secret_display['configured'] ) {
				echo '<p><label><input type="checkbox" name="clear_signing_secret" value="1" /> ' . esc_html__( 'Remove signing secret', 'ai-agent-workflow-automation' ) . '</label></p>';
			}
		}

		if ( $require_signing ) {
			echo '<p class="description"><strong>' . esc_html__( 'Site settings require a signing secret on every webhook.', 'ai-agent-workflow-automation' ) . '</strong></p>';
		}

		echo '</td></tr>';
	}

	/**
	 * @param string[] $ip_allow_list Current entries.
	 *
	 * @return void
	 */
	private function renderIpAllowListRow( array $ip_allow_list ): void {
		echo '<tr><th scope="row"><label for="aiawa-webhook-ip-allow-list">' . esc_html__( 'IP allow-list', 'ai-agent-workflow-automation' ) . '</label></th><td>';
		printf(
			'<textarea name="ip_allow_list" id="aiawa-webhook-ip-allow-list" class="large-text code" rows="4" cols="50">%s</textarea>',
			esc_textarea( implode( "\n", $ip_allow_list ) )
		);
		echo '<p class="description">' . esc_html__( 'Optional. One IPv4/IPv6 address or IPv4 CIDR (e.g. 203.0.113.0/24) per line. Leave empty to accept requests from any IP.', 'ai-agent-workflow-automation' ) . '</p>';
		echo '</td></tr>';
	}
}
