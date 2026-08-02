<?php
/**
 * REST controller for AI provider models / credentials.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Rest;

use DragwybAgentFlow\Plugin\Core\Capabilities;
use DragwybAgentFlow\Plugin\Service\Ai\AiClientBootstrap;
use DragwybAgentFlow\Plugin\Service\AiModelsService;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes AI model listing and in-builder credential save/clear.
 */
class AiProvidersController extends WP_REST_Controller {

	private AiModelsService $ai_models;

	public function __construct( AiModelsService $ai_models ) {
		$this->namespace = 'dragwyb_af/v1';
		$this->rest_base = 'ai';
		$this->ai_models = $ai_models;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/models',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getModels' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
					'args'                => array(
						'provider'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'node_type' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getStatus' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/credentials',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveCredentials' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
					'args'                => array(
						'provider' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'api_key'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => static function ( $value ) {
								return is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clearCredentials' ),
					'permission_callback' => array( $this, 'permissionsCheck' ),
					'args'                => array(
						'provider' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function permissionsCheck(): bool {
		return current_user_can( Capabilities::MANAGE_WORKFLOWS )
			|| current_user_can( Capabilities::MANAGE_CONNECTIONS )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getModels( $request ) {
		$provider  = (string) $request->get_param( 'provider' );
		$node_type = (string) $request->get_param( 'node_type' );

		$key = '' !== $provider ? $provider : $node_type;
		if ( '' === $key ) {
			return new WP_Error(
				'dragwyb_af_rest_invalid',
				__( 'Provider is required.', 'dragwyb-agentflow' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $this->ai_models->listForProvider( $key ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function getStatus( $request ) {
		unset( $request );

		$providers = array();
		foreach ( array_unique( array_values( AiClientBootstrap::PROVIDER_IDS ) ) as $provider_id ) {
			$providers[ $provider_id ] = AiClientBootstrap::hasStoredProviderApiKey( $provider_id );
		}

		return rest_ensure_response(
			array(
				'available' => AiClientBootstrap::isAvailable(),
				'providers' => $providers,
			)
		);
	}

	/**
	 * Save a site-wide API key for a provider (validated against the provider).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function saveCredentials( $request ) {
		$provider = (string) $request->get_param( 'provider' );
		$api_key  = (string) $request->get_param( 'api_key' );

		$result = AiClientBootstrap::saveProviderApiKey( $provider, $api_key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$provider_id = AiClientBootstrap::resolveProviderId( $provider );

		return rest_ensure_response(
			array(
				'success'    => true,
				'provider'   => $provider_id,
				'configured' => true,
			)
		);
	}

	/**
	 * Clear a site-wide API key for a provider.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clearCredentials( $request ) {
		$provider = (string) $request->get_param( 'provider' );

		$result = AiClientBootstrap::clearProviderApiKey( $provider );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$provider_id = AiClientBootstrap::resolveProviderId( $provider );

		return rest_ensure_response(
			array(
				'success'    => true,
				'provider'   => $provider_id,
				'configured' => false,
			)
		);
	}
}
