<?php
/**
 * Connections REST controller (list + create for the builder).
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Rest;

use InvalidArgumentException;
use RuntimeException;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Domain\Connection;
use AIAWA\Plugin\Service\AiModelsService;
use AIAWA\Plugin\Service\ConnectionAuthTypes;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\GoogleOAuthService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credential-free list of connections for the builder picker, plus a create
 * route so authors can add an API key inline on a node (without leaving the
 * builder for the Connections admin screen). Responses never include
 * decrypted secrets — only id/label/auth metadata.
 */
class ConnectionsController {

	private const API_NAMESPACE = 'aiawa/v1';

	private const ROUTE = '/connections';

	private const MAX_ITEMS = 100;

	private ConnectionService $connections;

	private AiModelsService $ai_models;

	private GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, AiModelsService $ai_models, GoogleOAuthService $google_oauth ) {
		$this->connections  = $connections;
		$this->ai_models    = $ai_models;
		$this->google_oauth = $google_oauth;
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItems' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createItem' ),
					'permission_callback' => array( $this, 'createPermissionsCheck' ),
					'args'                => array(
						'label'            => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'integration_slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'auth_type'        => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => ConnectionAuthTypes::VALID,
						),
						'credentials'      => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE . '/(?P<id>[\d]+)/oauth/authorize-url',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getOAuthAuthorizeUrl' ),
				'permission_callback' => array( $this, 'createPermissionsCheck' ),
				'args'                => array(
					'id'         => array(
						'type'     => 'integer',
						'required' => true,
					),
					'return_url' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'node_id'    => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE . '/(?P<id>[\d]+)/models',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getModels' ),
				'permission_callback' => array( $this, 'permissionsCheck' ),
				'args'                => array(
					'id'        => array(
						'type'     => 'integer',
						'required' => true,
					),
					'node_type' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function permissionsCheck( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) && ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
			return new WP_Error(
				'aiawa_rest_forbidden',
				__( 'Sorry, you are not allowed to view connections.', 'workflow-automate' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Creating from the builder is allowed for workflow authors as well as
	 * connection managers — otherwise inline API-key entry cannot work.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function createPermissionsCheck( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) && ! current_user_can( Capabilities::MANAGE_CONNECTIONS ) ) {
			return new WP_Error(
				'aiawa_rest_forbidden',
				__( 'Sorry, you are not allowed to create connections.', 'workflow-automate' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response
	 */
	public function getItems( $request ) {
		$page = $this->connections->list(
			array(
				'page'     => 1,
				'per_page' => self::MAX_ITEMS,
			)
		);

		return rest_ensure_response( array_map( array( $this, 'serialize' ), $page['items'] ) );
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( $request ) {
		$credentials = $request->get_param( 'credentials' );

		if ( ! is_array( $credentials ) ) {
			return new WP_Error(
				'aiawa_rest_invalid',
				__( 'Credentials must be an object of field values.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		// Only accept known credential field names for the chosen auth type;
		// never store arbitrary keys from the client.
		$auth_type = (string) $request->get_param( 'auth_type' );
		$allowed   = array_keys(
			ConnectionAuthTypes::OAUTH2 === $auth_type
				? ConnectionAuthTypes::editableFields( $auth_type )
				: ConnectionAuthTypes::fields( $auth_type )
		);
		$filtered  = array();

		foreach ( $allowed as $field ) {
			if ( isset( $credentials[ $field ] ) ) {
				$val                = wp_unslash( (string) $credentials[ $field ] );
				$filtered[ $field ] = trim( sanitize_text_field( $val ) );
			}
		}

		try {
			$connection = $this->connections->create(
				(string) $request->get_param( 'integration_slug' ),
				$auth_type,
				(string) $request->get_param( 'label' ),
				$filtered
			);
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'aiawa_rest_invalid', $exception->getMessage(), array( 'status' => 400 ) );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'aiawa_rest_server_error', $exception->getMessage(), array( 'status' => 500 ) );
		}

		$response = rest_ensure_response( $this->serialize( $connection ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getModels( $request ) {
		$node_type = (string) $request->get_param( 'node_type' );

		$result = $this->ai_models->listForConnection( (int) $request['id'], $node_type );

		return rest_ensure_response( $result );
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getOAuthAuthorizeUrl( $request ) {
		$connection = $this->connections->find( (int) $request['id'] );

		if ( null === $connection ) {
			return new WP_Error(
				'aiawa_rest_not_found',
				__( 'Connection not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		if ( ConnectionAuthTypes::OAUTH2 !== $connection->authType() ) {
			return new WP_Error(
				'aiawa_rest_invalid',
				__( 'This connection is not a Google OAuth connection.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		try {
			$authorize_url = $this->google_oauth->buildAuthorizeUrl(
				$connection,
				(string) $request->get_param( 'return_url' ),
				(string) $request->get_param( 'node_id' )
			);
		} catch ( \RuntimeException $exception ) {
			return new WP_Error(
				'aiawa_rest_invalid',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'authorize_url'   => $authorize_url,
				'callback_url'    => $this->google_oauth->callbackUrl(),
				'credentials_url' => GoogleOAuthService::GOOGLE_CREDENTIALS_URL,
			)
		);
	}

	/**
	 * @param Connection $connection Connection to serialize.
	 *
	 * @return array{id: int, label: string, integration_slug: string, auth_type: string, auth_type_label: string, oauth_connected?: bool}
	 */
	private function serialize( Connection $connection ): array {
		$data = array(
			'id'               => $connection->id(),
			'label'            => $connection->label(),
			'integration_slug' => $connection->integrationSlug(),
			'auth_type'        => $connection->authType(),
			'auth_type_label'  => ConnectionAuthTypes::label( $connection->authType() ),
		);

		if ( ConnectionAuthTypes::OAUTH2 === $connection->authType() ) {
			$data['oauth_connected'] = $this->google_oauth->isConnected( $connection );
		}

		return $data;
	}
}
