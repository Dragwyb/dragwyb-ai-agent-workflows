<?php
/**
 * Connections (picker) REST controller.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

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
 * Exposes a minimal, credential-free list of stored connections so the
 * builder's config panel can render a "connection" field (item 12) as a
 * picker — e.g. for `HttpRequestAction::configSchema()`'s `connection_id`
 * field. Read-only, same reasoning as `NodeTypesController`: connections
 * are only ever created/edited/deleted through the admin UI's
 * `admin-post.php` forms (`ConnectionActionsController`), never over this
 * REST API, so there is no corresponding write route.
 *
 * Deliberately returns only `id`, `label`, `integration_slug`, and
 * `auth_type` — never `encryptedCredentials()` or anything derived from
 * it. There is no "displayCredentials" here even in masked form; a picker
 * only needs to tell connections apart by name, not show what's inside
 * them.
 *
 * Fetches a single page of up to 100 connections (the repository's own
 * max `per_page`) rather than adding real pagination to this endpoint —
 * acceptable for a picker at today's expected scale; if a site ever
 * accumulates more connections than that, this is the first place to
 * revisit.
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'wfa_rest_forbidden',
				__( 'Sorry, you are not allowed to view connections.', 'workflow-automate' ),
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
