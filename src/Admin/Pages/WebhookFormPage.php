<?php
/**
 * Webhook create/edit admin page.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Admin\Pages;

use DragwybAgentFlow\Plugin\Admin\AdminPage;
use DragwybAgentFlow\Plugin\Core\Capabilities;
use DragwybAgentFlow\Plugin\Domain\Webhook;
use DragwybAgentFlow\Plugin\Domain\Workflow;
use DragwybAgentFlow\Plugin\Service\SettingsService;
use DragwybAgentFlow\Plugin\Service\WebhookService;
use DragwybAgentFlow\Plugin\Service\WorkflowService;

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

	public const SLUG = 'dragwyb-af-webhook-form';

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
		return __( 'Webhook', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Webhook', 'dragwyb-agentflow' );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which webhook to edit.
		$webhook_id = isset( $_GET['webhook'] ) ? absint( wp_unslash( $_GET['webhook'] ) ) : 0;

		echo '<div class="wrap dragwyb-af-admin-page">';
		$this->renderBackLink();

		if ( $webhook_id > 0 ) {
			$webhook = $this->webhooks->find( $webhook_id );

			if ( null === $webhook ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'That webhook does not exist.', 'dragwyb-agentflow' ) . '</p></div>';
				echo '</div>';

				return;
			}

			echo '<h1>' . esc_html__( 'Edit Webhook', 'dragwyb-agentflow' ) . '</h1>';
			$this->renderEditForm( $webhook );
		} else {
			echo '<h1>' . esc_html__( 'Add New Webhook', 'dragwyb-agentflow' ) . '</h1>';
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
			esc_html__( 'Back to Webhooks', 'dragwyb-agentflow' )
		);
	}

	/**
	 * @return void
	 */
	private function renderCreateForm(): void {
		$require_signing = $this->settings->requireWebhookSigning();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dragwyb-af-webhook-form">';
		echo '<input type="hidden" name="action" value="dragwyb_af_webhook_action" />';
		echo '<input type="hidden" name="op" value="create" />';
		wp_nonce_field( 'dragwyb_af_webhook_action_create' );

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->renderWorkflowRow( 0 );
		$this->renderSigningSecretRow( null, $require_signing );
		$this->renderIpAllowListRow( array() );
		echo '</tbody></table>';

		submit_button( __( 'Create Webhook', 'dragwyb-agentflow' ) );
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

		echo '<p class="description">' . esc_html__( 'Public URL (POST):', 'dragwyb-agentflow' ) . ' <code class="dragwyb-af-webhook-url">' . esc_html( $this->webhooks->publicUrl( $webhook ) ) . '</code></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dragwyb-af-webhook-form">';
		echo '<input type="hidden" name="action" value="dragwyb_af_webhook_action" />';
		echo '<input type="hidden" name="op" value="update" />';
		printf( '<input type="hidden" name="webhook_id" value="%s" />', esc_attr( $webhook_id ) );
		wp_nonce_field( 'dragwyb_af_webhook_action_update_' . $webhook_id );

		echo '<table class="form-table" role="presentation"><tbody>';
		$this->renderWorkflowRow( (int) ( $webhook->workflowId() ?? 0 ) );
		$this->renderSigningSecretRow( $secret_display, $require_signing );
		$this->renderIpAllowListRow( $webhook->ipAllowList() );
		echo '</tbody></table>';

		submit_button( __( 'Update Webhook', 'dragwyb-agentflow' ) );
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

		echo '<tr><th scope="row"><label for="dragwyb-af-webhook-workflow">' . esc_html__( 'Workflow', 'dragwyb-agentflow' ) . '</label></th><td>';
		echo '<select name="workflow_id" id="dragwyb-af-webhook-workflow" required>';
		echo '<option value="">' . esc_html__( 'Select a workflow…', 'dragwyb-agentflow' ) . '</option>';

		foreach ( $workflows['items'] as $workflow ) {
			$workflow_id = (int) $workflow->id();
			printf(
				'<option value="%1$d" %2$s>%3$s%4$s</option>',
				esc_attr( $workflow_id ),
				esc_attr( selected( $selected_id, $workflow_id, false ) ),
				esc_html( $workflow->title() ),
				Workflow::STATUS_ACTIVE === $workflow->status()
					? ''
					: ' ' . esc_html__( '(inactive — activate it before callers can use this webhook)', 'dragwyb-agentflow' )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Only active workflows run when the webhook is called. Draft or paused workflows return an error to the caller.', 'dragwyb-agentflow' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param array{configured: bool, display: string}|null $secret_display Null on create.
	 * @param bool                                          $require_signing Whether site settings require a secret.
	 *
	 * @return void
	 */
	private function renderSigningSecretRow( ?array $secret_display, bool $require_signing ): void {
		echo '<tr><th scope="row"><label for="dragwyb-af-webhook-signing-secret">' . esc_html__( 'Signing secret', 'dragwyb-agentflow' ) . '</label></th><td>';

		if ( null !== $secret_display && $secret_display['configured'] ) {
			printf(
				'<p class="dragwyb-af-webhook-current-value">%1$s <code>%2$s</code></p>',
				esc_html__( 'Currently set:', 'dragwyb-agentflow' ),
				esc_html( $secret_display['display'] )
			);
		}

		printf(
			'<input type="text" class="regular-text" name="signing_secret" id="dragwyb-af-webhook-signing-secret" value="" autocomplete="off" %1$s />',
			( $require_signing && ( null === $secret_display || ! $secret_display['configured'] ) ) ? 'required' : ''
		);

		if ( null === $secret_display ) {
			echo '<p class="description">' . esc_html__( 'Optional. When set, callers must send an X-dragwyb-af-Signature header (sha256=… HMAC of the raw body). Leave blank for an unsigned webhook.', 'dragwyb-agentflow' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the current secret. Enter a new value to rotate it.', 'dragwyb-agentflow' ) . '</p>';

			if ( ! $require_signing && $secret_display['configured'] ) {
				echo '<p><label><input type="checkbox" name="clear_signing_secret" value="1" /> ' . esc_html__( 'Remove signing secret', 'dragwyb-agentflow' ) . '</label></p>';
			}
		}

		if ( $require_signing ) {
			echo '<p class="description"><strong>' . esc_html__( 'Site settings require a signing secret on every webhook.', 'dragwyb-agentflow' ) . '</strong></p>';
		}

		echo '</td></tr>';
	}

	/**
	 * @param string[] $ip_allow_list Current entries.
	 *
	 * @return void
	 */
	private function renderIpAllowListRow( array $ip_allow_list ): void {
		echo '<tr><th scope="row"><label for="dragwyb-af-webhook-ip-allow-list">' . esc_html__( 'IP allow-list', 'dragwyb-agentflow' ) . '</label></th><td>';
		printf(
			'<textarea name="ip_allow_list" id="dragwyb-af-webhook-ip-allow-list" class="large-text code" rows="4" cols="50">%s</textarea>',
			esc_textarea( implode( "\n", $ip_allow_list ) )
		);
		echo '<p class="description">' . esc_html__( 'Optional. One IPv4/IPv6 address or IPv4 CIDR (e.g. 203.0.113.0/24) per line. Leave empty to accept requests from any IP.', 'dragwyb-agentflow' ) . '</p>';
		echo '</td></tr>';
	}
}
