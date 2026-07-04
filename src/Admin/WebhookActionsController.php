<?php
/**
 * Handles state-changing Webhook admin actions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Admin\Pages\WebhooksPage;
use WorkflowAutomate\Plugin\Service\WebhookService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=wfa_webhook_action` POST submitted
 * by WebhookFormPage's create/edit forms and WebhooksListTable's delete
 * forms. Same capability/nonce/allow-list shape as
 * ConnectionActionsController.
 */
class WebhookActionsController {

	private const ALLOWED_OPS = array( 'create', 'update', 'delete' );

	private WebhookService $webhooks;

	public function __construct( WebhookService $webhooks ) {
		$this->webhooks = $webhooks;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wfa_webhook_action', array( $this, 'handle' ) );
	}

	/**
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation (and per-id for update/delete).
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) ) {
			$this->redirect( 'error' );
		}

		if ( 'create' === $op ) {
			check_admin_referer( 'wfa_webhook_action_create' );
			$this->handleCreate();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-id.
		$id = isset( $_POST['webhook_id'] ) ? absint( wp_unslash( $_POST['webhook_id'] ) ) : 0;

		if ( $id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'wfa_webhook_action_' . $op . '_' . $id );

		if ( 'update' === $op ) {
			$this->handleUpdate( $id );
		} else {
			$this->handleDelete( $id );
		}
	}

	/**
	 * @return void
	 */
	private function handleCreate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( wp_unslash( $_POST['workflow_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$signing_secret = isset( $_POST['signing_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['signing_secret'] ) ) : '';

		try {
			$this->webhooks->create( $workflow_id, $signing_secret, $this->extractIpAllowList() );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			$this->redirect( 'error' );
		}

		$this->redirect( 'created' );
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return void
	 */
	private function handleUpdate( int $id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( wp_unslash( $_POST['workflow_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$signing_secret = isset( $_POST['signing_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['signing_secret'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$clear_signing_secret = ! empty( $_POST['clear_signing_secret'] );

		try {
			$this->webhooks->update( $id, $workflow_id, $signing_secret, $clear_signing_secret, $this->extractIpAllowList() );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			$this->redirect( 'error' );
		}

		$this->redirect( 'updated' );
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return void
	 */
	private function handleDelete( int $id ): void {
		$this->webhooks->delete( $id );

		$this->redirect( 'deleted' );
	}

	/**
	 * @return string[]
	 */
	private function extractIpAllowList(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$raw = isset( $_POST['ip_allow_list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ip_allow_list'] ) ) : '';

		if ( '' === $raw ) {
			return array();
		}

		return preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
	}

	/**
	 * @param string $notice One of the keys understood by WebhooksPage::notices().
	 *
	 * @return void
	 */
	private function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => WebhooksPage::SLUG,
					'wfa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
