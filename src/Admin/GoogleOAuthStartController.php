<?php
/**
 * Starts the Google OAuth authorization redirect from wp-admin.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use RuntimeException;
use AIAWA\Plugin\Admin\Pages\ConnectionFormPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Service\ConnectionAuthTypes;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\GoogleOAuthService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles `admin-post.php?action=aiawa_google_oauth_authorize`.
 */
class GoogleOAuthStartController {

	private ConnectionService $connections;

	private GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_aiawa_google_oauth_authorize', array( $this, 'handle' ) );
	}

	/**
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) && ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$connection_id = isset( $_GET['connection_id'] ) ? absint( wp_unslash( $_GET['connection_id'] ) ) : 0;

		if ( $connection_id <= 0 ) {
			$this->redirectWithError( 0, __( 'Invalid connection.', 'workflow-automate' ) );
		}

		check_admin_referer( 'aiawa_google_oauth_authorize_' . $connection_id );

		$connection = $this->connections->find( $connection_id );

		if ( null === $connection || ConnectionAuthTypes::OAUTH2 !== $connection->authType() ) {
			$this->redirectWithError( $connection_id, __( 'This connection is not configured for Google OAuth.', 'workflow-automate' ) );
		}

		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- return target validated in service.
			$return_url = isset( $_GET['return_url'] ) ? esc_url_raw( wp_unslash( $_GET['return_url'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque builder node id.
			$node_id = isset( $_GET['node_id'] ) ? sanitize_text_field( wp_unslash( $_GET['node_id'] ) ) : '';
			$url     = $this->google_oauth->buildAuthorizeUrl( $connection, $return_url, $node_id );
		} catch ( RuntimeException $exception ) {
			$this->redirectWithError( $connection_id, $exception->getMessage() );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param int    $connection_id Connection id.
	 * @param string $message       Error message.
	 *
	 * @return void
	 */
	private function redirectWithError( int $connection_id, string $message ): void {
		$args = array(
			'page'       => ConnectionFormPage::SLUG,
			'aiawa_notice' => 'error',
			'aiawa_error'  => $message,
		);

		if ( $connection_id > 0 ) {
			$args['connection'] = $connection_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
