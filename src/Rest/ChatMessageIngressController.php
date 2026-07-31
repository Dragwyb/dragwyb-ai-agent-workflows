<?php
/**
 * Public chat-message ingress REST controller.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Integration\Triggers\ChatMessageReceivedTrigger;
use WorkflowAutomate\Plugin\Service\ChatMessageService;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowTestListenerService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives chat messages for workflows that use
 * {@see ChatMessageReceivedTrigger}, matching n8n's Chat Trigger webhook body
 * (`chatInput`, `sessionId`, optional `action`).
 */
class ChatMessageIngressController {

	private const API_NAMESPACE = 'wfa/v1';

	private const ROUTE = '/chat/(?P<endpoint_id>[0-9a-fA-F-]{36})';

	private ChatMessageService $chat;

	private WorkflowExecutionService $executor;

	private WorkflowTestListenerService $test_listener;

	public function __construct(
		ChatMessageService $chat,
		WorkflowExecutionService $executor,
		WorkflowTestListenerService $test_listener
	) {
		$this->chat          = $chat;
		$this->executor      = $executor;
		$this->test_listener = $test_listener;
	}

	/**
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'receive' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'endpoint_id' => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => static function ( $value ): bool {
								return is_string( $value ) && (bool) preg_match( '/^[0-9a-fA-F-]{36}$/', $value );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'info' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'endpoint_id' => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => static function ( $value ): bool {
								return is_string( $value ) && (bool) preg_match( '/^[0-9a-fA-F-]{36}$/', $value );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function permission( $request ) {
		$endpoint_id = (string) $request->get_param( 'endpoint_id' );
		$match       = $this->chat->findByEndpointId( $endpoint_id );

		if ( null === $match ) {
			if (
				WP_REST_Server::READABLE === $request->get_method()
				&& current_user_can( Capabilities::MANAGE_WORKFLOWS )
			) {
				return true;
			}

			return new WP_Error(
				'wfa_chat_not_found',
				__( 'No active chat endpoint found for this ID. Activate the workflow first.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$is_public = ! isset( $match['config']['public'] ) || filter_var( $match['config']['public'], FILTER_VALIDATE_BOOLEAN );

		if ( $is_public ) {
			return true;
		}

		if ( current_user_can( Capabilities::MANAGE_WORKFLOWS ) || current_user_can( Capabilities::ACCESS ) ) {
			return true;
		}

		return new WP_Error(
			'wfa_chat_forbidden',
			__( 'This chat requires a logged-in WordPress user.', 'workflow-automate' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Widget/bootstrap metadata (title, placeholder, initial messages).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function info( $request ) {
		$endpoint_id = (string) $request->get_param( 'endpoint_id' );
		$match       = $this->chat->findByEndpointId( $endpoint_id );

		if ( null === $match && current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			$match = $this->chat->findAnyByEndpointId( $endpoint_id );
		}

		if ( null === $match ) {
			return new WP_Error(
				'wfa_chat_not_found',
				__( 'No chat endpoint found for this ID.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$config   = $match['config'];
		$initial  = isset( $config['initial_messages'] ) ? (string) $config['initial_messages'] : '';
		$lines    = preg_split( '/\r\n|\r|\n/', $initial );
		$messages = array_values(
			array_filter(
				array_map( 'trim', is_array( $lines ) ? $lines : array() )
			)
		);

		return rest_ensure_response(
			array(
				'endpoint_id'       => $endpoint_id,
				'title'             => (string) ( $config['title'] ?? '' ),
				'subtitle'          => (string) ( $config['subtitle'] ?? '' ),
				'input_placeholder' => (string) ( $config['input_placeholder'] ?? '' ),
				'initial_messages'  => $messages,
				'public'            => isset( $config['public'] ) && filter_var( $config['public'], FILTER_VALIDATE_BOOLEAN ),
				'workflow_status'   => $match['workflow']->status(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive( $request ) {
		$endpoint_id = (string) $request->get_param( 'endpoint_id' );

		if ( ! $this->checkRateLimit( $endpoint_id ) ) {
			return new WP_Error(
				'wfa_chat_rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again in a minute.', 'workflow-automate' ),
				array( 'status' => 429 )
			);
		}

		$match       = $this->chat->findByEndpointId( $endpoint_id );

		if ( null === $match ) {
			return new WP_Error(
				'wfa_chat_not_found',
				__( 'No active chat endpoint found for this ID. Activate the workflow first.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$action = isset( $body['action'] ) ? (string) $body['action'] : 'sendMessage';

		if ( 'loadPreviousSession' === $action ) {
			return rest_ensure_response(
				array(
					'data' => array(),
				)
			);
		}

		$payload = ChatMessageReceivedTrigger::normalizePayload(
			array_merge(
				$body,
				array( 'endpoint_id' => $endpoint_id )
			)
		);

		if ( '' === $payload['chatInput'] ) {
			return new WP_Error(
				'wfa_chat_empty',
				__( 'chatInput is required.', 'workflow-automate' ),
				array( 'status' => 422 )
			);
		}

		$config        = $match['config'];
		$response_mode = isset( $config['response_mode'] ) ? (string) $config['response_mode'] : 'lastNode';
		$workflow_id   = $match['workflow']->id();

		if ( $this->test_listener->isListening( $workflow_id ) ) {
			$this->test_listener->capturePayload( $workflow_id, $payload, ChatMessageReceivedTrigger::SLUG );

			return rest_ensure_response(
				array(
					'status'    => 'captured',
					'sessionId' => $payload['sessionId'],
					'message'   => __( 'Payload captured for Test Flow.', 'workflow-automate' ),
				)
			);
		}

		if ( 'immediate' === $response_mode ) {
			do_action( ChatMessageReceivedTrigger::HOOK, $payload );

			return new WP_REST_Response(
				array(
					'status'    => 'accepted',
					'sessionId' => $payload['sessionId'],
					'message'   => __( 'Message accepted. The workflow will run in the background.', 'workflow-automate' ),
				),
				202
			);
		}

		try {
			$run = $this->executor->run( $workflow_id, $payload );
		} catch ( \Throwable $exception ) {
			error_log( 'WorkflowAutomate Chat Run Error: ' . $exception->getMessage() );
			return new WP_Error(
				'wfa_chat_run_failed',
				__( 'Chat execution failed.', 'workflow-automate' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'output'    => $this->chat->extractReply( $run ),
				'sessionId' => $payload['sessionId'],
				'run_id'    => $run->id(),
				'status'    => $run->status(),
			)
		);
	}

	/**
	 * Rate limiting helper using WordPress transients.
	 *
	 * @param string $endpoint_id Endpoint UUID.
	 *
	 * @return bool True if allowed, false if limit exceeded.
	 */
	private function checkRateLimit( string $endpoint_id ): bool {
		$ip            = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		$transient_key = 'wfa_chat_rl_' . md5( $ip . '|' . $endpoint_id );
		$count         = (int) get_transient( $transient_key );

		if ( $count >= 30 ) {
			return false;
		}

		set_transient( $transient_key, $count + 1, 60 );
		return true;
	}
}
