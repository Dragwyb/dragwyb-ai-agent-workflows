<?php
/**
 * Google OAuth callback REST endpoint.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Rest;

use AIAWA\Plugin\Admin\Pages\ConnectionFormPage;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\GoogleOAuthService;
use WP_REST_Request;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public callback Google redirects to after OAuth consent.
 */
class GoogleOAuthCallbackController {

	private const API_NAMESPACE = 'aiawa/v1';

	private ConnectionService $connections;

	private GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
	}

	/**
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/oauth/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handleCallback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'  => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'state' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'error' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return void
	 */
	public function handleCallback( $request ): void {
		$error = (string) $request->get_param( 'error' );

		if ( '' !== $error ) {
			$this->redirectWithNotice(
				0,
				'error',
				sprintf(
					/* translators: %s: Google OAuth error code */
					__( 'Google authorization was denied or failed (%s).', 'workflow-automate' ),
					$error
				)
			);
		}

		$state_payload = $this->google_oauth->consumeState( (string) $request->get_param( 'state' ) );

		if ( null === $state_payload ) {
			$this->redirectWithNotice(
				0,
				'error',
				__( 'OAuth state expired or was invalid. Please try connecting again.', 'workflow-automate' )
			);
		}

		$connection = $this->connections->find( $state_payload['connection_id'] );

		if ( null === $connection ) {
			$this->redirectWithNotice(
				0,
				'error',
				__( 'The connection for this authorization no longer exists.', 'workflow-automate' ),
				$state_payload
			);
		}

		$result = $this->google_oauth->exchangeAuthorizationCode(
			$connection,
			(string) $request->get_param( 'code' )
		);

		if ( empty( $result['success'] ) ) {
			$this->redirectWithNotice(
				$connection->id(),
				'error',
				isset( $result['error'] ) ? (string) $result['error'] : __( 'Failed to connect to Google.', 'workflow-automate' ),
				$state_payload
			);
		}

		$this->redirectWithNotice( $connection->id(), 'oauth_connected', '', $state_payload );
	}

	/**
	 * @param int                  $connection_id Connection id (0 when unknown).
	 * @param string               $notice        Notice key.
	 * @param string               $detail        Optional error detail.
	 * @param array<string, mixed> $state_payload OAuth state payload.
	 *
	 * @return void
	 */
	private function redirectWithNotice( int $connection_id, string $notice, string $detail = '', array $state_payload = array() ): void {
		$return_url = isset( $state_payload['return_url'] ) ? (string) $state_payload['return_url'] : '';

		if ( '' !== $return_url ) {
			$args = array(
				'aiawa_notice' => $notice,
			);

			if ( $connection_id > 0 ) {
				$args['aiawa_connection'] = $connection_id;
			}

			$node_id = isset( $state_payload['node_id'] ) ? (string) $state_payload['node_id'] : '';

			if ( '' !== $node_id ) {
				$args['aiawa_node'] = $node_id;
			}

			if ( '' !== $detail ) {
				$args['aiawa_error'] = $detail;
			}

			wp_safe_redirect( add_query_arg( $args, $return_url ) );
			exit;
		}

		$args = array(
			'page'       => ConnectionFormPage::SLUG,
			'aiawa_notice' => $notice,
		);

		if ( $connection_id > 0 ) {
			$args['connection'] = $connection_id;
		}

		if ( '' !== $detail ) {
			$args['aiawa_error'] = $detail;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
