<?php
/**
 * Handles state-changing Connection admin actions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Admin\Pages\ConnectionsPage;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=wfa_connection_action` POST
 * submitted by ConnectionFormPage's create/edit forms and
 * ConnectionsListTable's/ConnectionFormPage's delete forms.
 *
 * On a validation failure this always redirects to the Connections list
 * with a generic error notice rather than back to the originating form
 * with the invalid input preserved (contrast WorkflowActionsController,
 * which has no secrets to worry about): preserving input here would mean
 * either putting a partially-typed secret into a redirect URL (logged,
 * cached, kept in browser history) or introducing server-side flash-data
 * storage this plugin has no other use for. Both fields on the create
 * form are marked `required` in the browser, so a real user only ever
 * hits this path by bypassing that — an acceptable, documented trade-off
 * for a v1 admin screen.
 */
class ConnectionActionsController {

	private const ALLOWED_OPS = array( 'create', 'update', 'delete' );

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * Hooks the admin-post handler. There is no `admin_post_nopriv_*`
	 * counterpart: this action is never valid for a logged-out visitor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wfa_connection_action', array( $this, 'handle' ) );
	}

	/**
	 * Processes the request, then redirects back to the Connections list.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation (and per-id for update/delete).
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) ) {
			$this->redirect( 'error' );
		}

		if ( 'create' === $op ) {
			check_admin_referer( 'wfa_connection_action_create' );
			$this->handleCreate();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-id.
		$id = isset( $_POST['connection_id'] ) ? absint( wp_unslash( $_POST['connection_id'] ) ) : 0;

		if ( $id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'wfa_connection_action_' . $op . '_' . $id );

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
		$integration_slug = isset( $_POST['integration_slug'] ) ? sanitize_key( wp_unslash( $_POST['integration_slug'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$auth_type = isset( $_POST['auth_type'] ) ? sanitize_key( wp_unslash( $_POST['auth_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		try {
			$this->connections->create( $integration_slug, $auth_type, $label, $this->extractCredentialValues() );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			$this->redirect( 'error' );
		}

		$this->redirect( 'created' );
	}

	/**
	 * @param int $id Connection id.
	 *
	 * @return void
	 */
	private function handleUpdate( int $id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		try {
			$this->connections->update( $id, $label, $this->extractCredentialValues() );
		} catch ( InvalidArgumentException | RuntimeException $e ) {
			$this->redirect( 'error' );
		}

		$this->redirect( 'updated' );
	}

	/**
	 * @param int $id Connection id.
	 *
	 * @return void
	 */
	private function handleDelete( int $id ): void {
		$this->connections->delete( $id );

		$this->redirect( 'deleted' );
	}

	/**
	 * Reads the `credential[field] => value` submission into a plain,
	 * sanitized array. Values are still secrets in transit, so only
	 * `sanitize_text_field()` (strips tags/normalizes whitespace, does not
	 * otherwise alter typical API key/token/password character sets) is
	 * applied — no more aggressive filtering that risks silently
	 * corrupting a credential's actual bytes.
	 *
	 * @return array<string,string>
	 */
	private function extractCredentialValues(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in handle().
		$raw = isset( $_POST['credential'] ) && is_array( $_POST['credential'] ) ? wp_unslash( $_POST['credential'] ) : array();

		$values = array();

		foreach ( $raw as $field => $value ) {
			$values[ sanitize_key( (string) $field ) ] = sanitize_text_field( (string) $value );
		}

		return $values;
	}

	/**
	 * Redirects to the Connections list with a notice, then exits.
	 *
	 * @param string $notice One of the keys understood by ConnectionsPage::notices().
	 *
	 * @return void
	 */
	private function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => ConnectionsPage::SLUG,
					'wfa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
