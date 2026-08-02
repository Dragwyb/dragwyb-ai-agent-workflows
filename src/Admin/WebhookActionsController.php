<?php
/**
 * Handles state-changing Webhook admin actions.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Admin;

use InvalidArgumentException;
use RuntimeException;
use DragwybAgentFlow\Plugin\Admin\Pages\WebhooksPage;
use DragwybAgentFlow\Plugin\Core\Capabilities;
use DragwybAgentFlow\Plugin\Service\WebhookService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=dragwyb_af_webhook_action` POST submitted
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
		add_action( 'admin_post_dragwyb_af_webhook_action', array( $this, 'handle' ) );
		add_action( 'admin_init', array( $this, 'maybeHandleWebhooksBulkFromList' ), 5 );
	}

	/**
	 * Early router so bulk POST is handled before any admin output.
	 *
	 * @return void
	 */
	public function maybeHandleWebhooksBulkFromList(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( ! is_admin() || 'POST' !== $request_method ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( WebhooksPage::SLUG !== $page ) {
			return;
		}

		$this->handleWebhooksBulkFromList();
	}

	/**
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_WEBHOOKS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'dragwyb-agentflow' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation (and per-id for update/delete).
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) ) {
			$this->redirect( 'error' );
		}

		if ( 'create' === $op ) {
			check_admin_referer( 'dragwyb_af_webhook_action_create' );
			$this->handleCreate();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-id.
		$id = isset( $_POST['webhook_id'] ) ? absint( wp_unslash( $_POST['webhook_id'] ) ) : 0;

		if ( $id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'dragwyb_af_webhook_action_' . $op . '_' . $id );

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
	 * @return void
	 */
	public function handleWebhooksBulkFromList(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
		if ( empty( $_POST['dragwyb_af_webhook_bulk'] ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_WEBHOOKS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'dragwyb-agentflow' ), 403 );
		}

		if ( ! ListTableUi::verifyBulkNonce( 'dragwyb_af_webhook_bulk_action' ) ) {
			$this->redirect( 'error' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in verifyBulkNonce().
		$bulk_action = isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : ( isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' );

		if ( 'delete' !== $bulk_action ) {
			$this->redirect( 'error' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in verifyBulkNonce().
		$ids = isset( $_POST['webhooks'] ) && is_array( $_POST['webhooks'] ) ? array_map( 'absint', wp_unslash( $_POST['webhooks'] ) ) : array();

		foreach ( array_filter( $ids ) as $id ) {
			$this->webhooks->delete( $id );
		}

		$this->redirect( 'bulk_deleted' );
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
					'page'       => WebhooksPage::SLUG,
					'dragwyb_af_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
