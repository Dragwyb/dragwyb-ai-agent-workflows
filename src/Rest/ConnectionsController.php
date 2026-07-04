<?php
/**
 * Connections REST controller (list + create for the builder).
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Domain\Connection;
use WorkflowAutomate\Plugin\Service\ConnectionAuthTypes;
use WorkflowAutomate\Plugin\Service\ConnectionService;
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

	private const API_NAMESPACE = 'wfa/v1';

	private const ROUTE = '/connections';

	private const MAX_ITEMS = 100;

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
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
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'getItems' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
					'args' => array(),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'createItem' ),
					'permission_callback' => array( $this, 'createPermissionsCheck' ),
					'args' => array(
						'label' => array(
							'type' => 'string',
							'required' => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'integration_slug' => array(
							'type' => 'string',
							'required' => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'auth_type' => array(
							'type' => 'string',
							'required' => true,
							'enum' => ConnectionAuthTypes::VALID,
						),
						'credentials' => array(
							'type' => 'object',
							'required' => true,
						),
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
				'wfa_rest_forbidden',
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
				'wfa_rest_forbidden',
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
				'page' => 1,
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
				'wfa_rest_invalid',
				__( 'Credentials must be an object of field values.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		// Only accept known credential field names for the chosen auth type;
		// never store arbitrary keys from the client.
		$auth_type = (string) $request->get_param( 'auth_type' );
		$allowed   = array_keys( ConnectionAuthTypes::fields( $auth_type ) );
		$filtered  = array();

		foreach ( $allowed as $field ) {
			if ( isset( $credentials[ $field ] ) ) {
				$filtered[ $field ] = (string) $credentials[ $field ];
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
			return new WP_Error( 'wfa_rest_invalid', $exception->getMessage(), array( 'status' => 400 ) );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'wfa_rest_server_error', $exception->getMessage(), array( 'status' => 500 ) );
		}

		$response = rest_ensure_response( $this->serialize( $connection ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * @param Connection $connection Connection to serialize.
	 *
	 * @return array{id: int, label: string, integration_slug: string, auth_type: string, auth_type_label: string}
	 */
	private function serialize( Connection $connection ): array {
		return array(
			'id' => $connection->id(),
			'label' => $connection->label(),
			'integration_slug' => $connection->integrationSlug(),
			'auth_type' => $connection->authType(),
			'auth_type_label' => ConnectionAuthTypes::label( $connection->authType() ),
		);
	}
}
