<?php
/**
 * Node types REST controller.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Domain\Contracts\NodeTypeInterface;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the server-side `NodeTypeRegistry` (item 5) to the builder's node
 * palette. Read-only by design — node types are registered in PHP via the
 * `wfa/nodes/register` action, never created/edited over HTTP — so this is
 * a plain class rather than a `WP_REST_Controller` subclass: there is no
 * CRUD/schema-derivation machinery to inherit for a single GET route.
 */
class NodeTypesController {

	private const API_NAMESPACE = 'wfa/v1';

	private const ROUTE = '/node-types';

	private NodeTypeRegistry $registry;

	public function __construct( NodeTypeRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE,
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'getItems' ),
				'permission_callback' => array( $this, 'permissionsCheck' ),
				'args' => array(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function permissionsCheck( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			return new WP_Error(
				'wfa_rest_forbidden',
				__( 'Sorry, you are not allowed to view node types.', 'workflow-automate' ),
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
		return rest_ensure_response(
			array(
				'triggers' => array_map( array( $this, 'serialize' ), $this->registry->triggers() ),
				'actions' => array_map( array( $this, 'serialize' ), $this->registry->actions() ),
			)
		);
	}

	/**
	 * @param NodeTypeInterface $node_type Trigger or action to serialize.
	 *
	 * @return array{slug: string, label: string, description: string, config_schema: array<string, mixed>}
	 */
	private function serialize( NodeTypeInterface $node_type ): array {
		return array(
			'slug' => $node_type->slug(),
			'label' => $node_type->label(),
			'description' => $node_type->description(),
			'config_schema' => $node_type->configSchema(),
		);
	}
}
