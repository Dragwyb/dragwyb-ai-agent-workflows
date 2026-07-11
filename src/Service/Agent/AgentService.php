<?php
/**
 * AI Agent execution engine (tool-calling loop).
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

use WorkflowAutomate\Plugin\Service\ConfigInterpolator;
use WorkflowAutomate\Plugin\Service\ConnectionSecretResolver;
use WorkflowAutomate\Plugin\Service\ConnectionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs an AI Agent node: memory, LLM iterations, and attached tool execution.
 */
class AgentService {

	private const DEFAULT_MAX_ITERATIONS = 5;

	private const MAX_ITERATIONS_CAP = 10;

	private ConnectionSecretResolver $secrets;

	private AgentToolSchemaBuilder $schema_builder;

	private AgentToolExecutor $tool_executor;

	private AgentLlmClient $llm_client;

	public function __construct(
		ConnectionService $connections,
		AgentToolSchemaBuilder $schema_builder,
		AgentToolExecutor $tool_executor,
		AgentLlmClient $llm_client
	) {
		$this->secrets         = new ConnectionSecretResolver( $connections );
		$this->schema_builder  = $schema_builder;
		$this->tool_executor   = $tool_executor;
		$this->llm_client      = $llm_client;
	}

	/**
	 * @param array<string, mixed> $config        Agent node config (interpolated).
	 * @param array<string, mixed> $context       Workflow execution context.
	 * @param string               $agent_node_id Agent client node id.
	 *
	 * @return array<string, mixed>
	 */
	public function execute( array $config, array $context, string $agent_node_id ): array {
		$graph_nodes = $this->graphNodesFromContext( $context );

		if ( array() === $graph_nodes ) {
			return array(
				'success' => false,
				'error'   => __( 'Workflow graph is not available for the AI Agent.', 'workflow-automate' ),
			);
		}

		$workflow_id = (int) ( $context['workflow_id'] ?? 0 );
		$attachments = AgentGraphHelper::resolveAttachments( $graph_nodes, $agent_node_id );
		$chat        = AgentGraphHelper::resolveChatModelConfig( $attachments['chat_model'], $config );

		if ( $chat['connection_id'] <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'No chat model configured. Attach a Chat Model to the agent or set an API connection.', 'workflow-automate' ),
			);
		}

		$api_key = $this->secrets->resolveBearerSecret( $chat['connection_id'] );

		if ( is_array( $api_key ) ) {
			return $api_key;
		}

		$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

		if ( '' === $prompt ) {
			return array(
				'success' => false,
				'error'   => __( 'No prompt configured for the AI Agent.', 'workflow-automate' ),
			);
		}

		$model = trim( $chat['model'] );

		if ( '' === $model ) {
			$model = $this->defaultModelForProvider( $chat['provider'] );
		}

		$system_prompt   = $this->prepareSystemPrompt(
			isset( $config['system_prompt'] ) ? trim( (string) $config['system_prompt'] ) : '',
			$config,
			$attachments
		);
		$user_message    = $this->enrichUserMessage( $prompt, $context, $attachments );
		$max_iterations  = $this->resolveMaxIterations( $config );
		$tool_schemas    = $this->schema_builder->buildSchemas( $attachments['tools'] );
		$messages        = $this->buildMessages( $config, $context, $agent_node_id, $attachments, $system_prompt, $user_message );
		$system_for_call = $this->extractSystemPrompt( $messages, $system_prompt );

		$loop = $this->runAgentLoop(
			$chat['provider'],
			$api_key,
			$model,
			$messages,
			$tool_schemas,
			$system_for_call,
			$max_iterations,
			$graph_nodes,
			$workflow_id,
			$context
		);

		if ( empty( $loop['success'] ) ) {
			return $loop;
		}

		$response = (string) ( $loop['response'] ?? '' );

		if ( isset( $config['output_format'] ) && 'json' === $config['output_format'] ) {
			$parsed = $this->parseJsonResponse( $response );

			if ( is_array( $parsed ) ) {
				$loop['parsed'] = $parsed;
			} elseif ( ! empty( $attachments['tools'] ) || ! empty( $loop['tool_calls'] ) ) {
				$loop['json_parse_warning'] = __( 'Reply was plain text, not JSON (expected when the agent uses tools).', 'workflow-automate' );
			} else {
				return array(
					'success'  => false,
					'error'    => __( 'The agent did not return valid JSON.', 'workflow-automate' ),
					'response' => $response,
				);
			}
		}

		$loop['response'] = $response;
		$loop['provider'] = $chat['provider'];
		$loop['model']    = $model;

		if ( ! empty( $attachments['memory'] ) && is_array( $attachments['memory'] ) && isset( $loop['conversation_messages'] ) ) {
			$memory_store = $this->memoryStore( $agent_node_id, $attachments['memory'], $config, $context );
			$memory_store->store( $loop['conversation_messages'] );
			unset( $loop['conversation_messages'] );
		}

		return $loop;
	}

	/**
	 * @param array<string, mixed> $context Execution context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function graphNodesFromContext( array $context ): array {
		$graph = $context['graph'] ?? array();

		if ( ! is_array( $graph ) ) {
			return array();
		}

		$nodes = $graph['nodes'] ?? array();

		if ( ! is_array( $nodes ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $nodes as $node ) {
			if ( is_array( $node ) ) {
				$normalized[] = $node;
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $config Agent config.
	 *
	 * @return int
	 */
	private function resolveMaxIterations( array $config ): int {
		$max = isset( $config['max_iterations'] ) ? (int) $config['max_iterations'] : self::DEFAULT_MAX_ITERATIONS;

		if ( $max < 1 ) {
			$max = self::DEFAULT_MAX_ITERATIONS;
		}

		return min( $max, self::MAX_ITERATIONS_CAP );
	}

	/**
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	private function defaultModelForProvider( string $provider ): string {
		if ( 'claude' === $provider ) {
			return 'claude-sonnet-4-20250514';
		}

		if ( 'gemini' === $provider ) {
			return 'gemini-2.0-flash';
		}

		if ( 'openrouter' === $provider ) {
			return 'openai/gpt-4o-mini';
		}

		if ( 'groq' === $provider ) {
			return 'llama-3.3-70b-versatile';
		}

		if ( 'deepseek' === $provider ) {
			return 'deepseek-chat';
		}

		return 'gpt-4o-mini';
	}

	/**
	 * @param array<string, mixed>      $config        Agent config.
	 * @param array<string, mixed>      $context       Context.
	 * @param string                    $agent_node_id Agent id.
	 * @param array<string, mixed>      $attachments   Resolved attachments.
	 * @param string                    $system_prompt System prompt.
	 * @param string                    $user_message  User message.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function buildMessages(
		array $config,
		array $context,
		string $agent_node_id,
		array $attachments,
		string $system_prompt,
		string $user_message
	): array {
		$messages = array();

		if ( is_array( $attachments['memory'] ) ) {
			$memory_store = $this->memoryStore( $agent_node_id, $attachments['memory'], $config, $context );

			if ( $memory_store->exists() ) {
				$messages = $memory_store->retrieve();
			} elseif ( '' !== $system_prompt ) {
				$messages[] = array(
					'role'    => 'system',
					'content' => $system_prompt,
				);
			}
		} elseif ( '' !== $system_prompt ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		return $messages;
	}

	/**
	 * @param string               $agent_node_id Agent id.
	 * @param array<string, mixed> $memory_node   Memory graph node.
	 * @param array<string, mixed> $config        Agent config.
	 * @param array<string, mixed> $context       Context.
	 *
	 * @return AgentMemoryStore
	 */
	private function memoryStore(
		string $agent_node_id,
		array $memory_node,
		array $config,
		array $context
	): AgentMemoryStore {
		$memory_config = isset( $memory_node['config'] ) && is_array( $memory_node['config'] ) ? $memory_node['config'] : array();
		$session_key   = '';

		if ( isset( $memory_config['memory_key'] ) && is_string( $memory_config['memory_key'] ) ) {
			$session_key = ( new ConfigInterpolator() )->interpolateString( $memory_config['memory_key'], $context );
		}

		return new AgentMemoryStore(
			$agent_node_id,
			(string) ( $memory_node['id'] ?? '' ),
			$memory_config,
			$session_key
		);
	}

	/**
	 * @param array<int, mixed> $messages      Messages.
	 * @param string            $system_prompt Fallback system prompt.
	 *
	 * @return string
	 */
	private function extractSystemPrompt( array $messages, string $system_prompt ): string {
		foreach ( $messages as $message ) {
			if ( is_array( $message ) && 'system' === ( $message['role'] ?? '' ) ) {
				return (string) ( $message['content'] ?? '' );
			}
		}

		return $system_prompt;
	}

	/**
	 * @param string               $provider       Provider slug.
	 * @param string               $api_key        API key.
	 * @param string               $model          Model id.
	 * @param array<int, mixed>    $messages       Messages.
	 * @param array<int, mixed>    $tool_schemas   Tool schemas.
	 * @param string               $system_prompt  System prompt for Claude/Gemini.
	 * @param int                  $max_iterations Max loop iterations.
	 * @param array<int, mixed>    $graph_nodes    Graph nodes.
	 * @param int                  $workflow_id    Workflow id.
	 * @param array<string, mixed> $context        Execution context.
	 *
	 * @return array<string, mixed>
	 */
	private function runAgentLoop(
		string $provider,
		string $api_key,
		string $model,
		array $messages,
		array $tool_schemas,
		string $system_prompt,
		int $max_iterations,
		array $graph_nodes,
		int $workflow_id,
		array $context
	): array {
		$iteration    = 0;
		$all_tool_calls = array();
		$seen_calls   = array();
		$messages     = $this->sanitizeMessages( $messages );

		while ( $iteration < $max_iterations ) {
			++$iteration;

			$completion = $this->llm_client->complete(
				$provider,
				$api_key,
				$model,
				$messages,
				$tool_schemas,
				$system_prompt
			);

			if ( empty( $completion['success'] ) ) {
				return array(
					'success' => false,
					'error'   => $completion['error'] ?? __( 'AI Agent request failed.', 'workflow-automate' ),
				);
			}

			$message = $completion['message'] ?? array();

			if ( ! is_array( $message ) ) {
				return array(
					'success' => false,
					'error'   => __( 'The AI model returned an invalid message.', 'workflow-automate' ),
				);
			}

			$messages[] = $message;

			if ( empty( $message['tool_calls'] ) || ! is_array( $message['tool_calls'] ) ) {
				return array(
					'success'       => true,
					'response'      => (string) ( $message['content'] ?? '' ),
					'iterations'    => $iteration,
					'finish_reason' => (string) ( $completion['finish_reason'] ?? 'stop' ),
					'tool_calls'    => $this->formatToolCalls( $all_tool_calls ),
					'conversation_messages' => $messages,
				);
			}

			foreach ( $message['tool_calls'] as $tool_call ) {
				if ( ! is_array( $tool_call ) ) {
					continue;
				}

				$tool_call_id = (string) ( $tool_call['id'] ?? wp_generate_uuid4() );
				$function     = is_array( $tool_call['function'] ?? null ) ? $tool_call['function'] : array();
				$function_name = (string) ( $function['name'] ?? '' );
				$raw_args      = $function['arguments'] ?? '{}';
				$args          = $this->parseToolArguments( $raw_args );

				$signature = md5( $function_name . wp_json_encode( $args ) );

				if ( isset( $seen_calls[ $signature ] ) ) {
					return array(
						'success' => false,
						'error'   => __( 'Repeated tool call detected (loop prevention).', 'workflow-automate' ),
					);
				}

				$seen_calls[ $signature ] = true;

				$tool_result = $this->tool_executor->execute(
					$function_name,
					$args,
					$graph_nodes,
					$workflow_id,
					$context
				);

				$all_tool_calls[] = array(
					'id'        => $tool_call_id,
					'name'      => $function_name,
					'arguments' => $args,
					'result'    => $tool_result,
				);

				$tool_payload = array_merge(
					array( 'name' => $function_name ),
					$tool_result
				);

				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call_id,
					'content'      => wp_json_encode( $tool_payload ) ?: '{}',
				);
			}
		}

		return array(
			'success'    => false,
			'error'      => __( 'Max iterations reached.', 'workflow-automate' ),
			'iterations' => $iteration,
			'tool_calls' => $this->formatToolCalls( $all_tool_calls ),
		);
	}

	/**
	 * @param mixed $raw_args Tool arguments from the LLM (JSON string or array).
	 *
	 * @return array<string, mixed>
	 */
	private function parseToolArguments( $raw_args ): array {
		if ( is_array( $raw_args ) ) {
			return $raw_args;
		}

		if ( ! is_string( $raw_args ) ) {
			return array();
		}

		$trimmed = trim( $raw_args );

		if ( '' === $trimmed ) {
			return array();
		}

		$decoded = json_decode( $trimmed, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$repaired = preg_replace( '/,\s*([}\]])/', '$1', $trimmed );
		$repaired = is_string( $repaired ) ? $repaired : $trimmed;

		$decoded = json_decode( $repaired, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<int, array<string, mixed>> $tool_calls Raw tool calls.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function formatToolCalls( array $tool_calls ): array {
		$formatted = array();

		foreach ( $tool_calls as $tool_call ) {
			$name = (string) ( $tool_call['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$formatted[ $name ] = array(
				'input'  => $tool_call['arguments'] ?? array(),
				'output' => $tool_call['result'] ?? array(),
			);
		}

		return $formatted;
	}

	/**
	 * @param array<int, mixed> $messages Messages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitizeMessages( array $messages ): array {
		$valid_tool_call_ids = array();
		$sanitized           = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = (string) ( $message['role'] ?? '' );

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
				foreach ( $message['tool_calls'] as $tool_call ) {
					if ( is_array( $tool_call ) && isset( $tool_call['id'] ) ) {
						$valid_tool_call_ids[ (string) $tool_call['id'] ] = true;
					}
				}

				$sanitized[] = $message;
				continue;
			}

			if ( 'tool' === $role ) {
				$tool_call_id = (string) ( $message['tool_call_id'] ?? '' );

				if ( isset( $valid_tool_call_ids[ $tool_call_id ] ) ) {
					$sanitized[] = $message;
				}

				continue;
			}

			$sanitized[] = $message;
		}

		return $sanitized;
	}

	/**
	 * @param string               $system_prompt Base system prompt.
	 * @param array<string, mixed> $config          Agent config.
	 * @param array<string, mixed> $attachments     Resolved attachments.
	 *
	 * @return string
	 */
	private function prepareSystemPrompt( string $system_prompt, array $config, array $attachments ): string {
		$parts = array();

		if ( '' !== $system_prompt ) {
			$parts[] = $system_prompt;
		}

		if ( ! empty( $attachments['tools'] ) ) {
			$parts[] = __( 'You have tools available. Use them to complete the task. Workflow trigger data is included in the user message when present—use it to fill tool parameters.', 'workflow-automate' );
			$parts[] = __( 'When calling tools, pass the final text with real values from the trigger data (customer name, order ID, amounts, etc.). Do not use {{placeholder}} or template syntax in tool arguments—write the complete message as plain text.', 'workflow-automate' );
		}

		if ( isset( $config['output_format'] ) && 'json' === $config['output_format'] && empty( $attachments['tools'] ) ) {
			$parts[] = __( 'Respond with valid JSON only. Do not include markdown fences or text outside the JSON object.', 'workflow-automate' );
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * @param string               $message     Interpolated user message.
	 * @param array<string, mixed> $context     Workflow context.
	 * @param array<string, mixed> $attachments Resolved attachments.
	 *
	 * @return string
	 */
	private function enrichUserMessage( string $message, array $context, array $attachments ): string {
		if ( empty( $attachments['tools'] ) ) {
			return $message;
		}

		$trigger = $context['trigger'] ?? null;

		if ( ! is_array( $trigger ) || array() === $trigger ) {
			return $message;
		}

		$trigger_json = wp_json_encode( $trigger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $trigger_json ) || '' === $trigger_json ) {
			return $message;
		}

		return $message . "\n\n---\n"
			. __( 'Workflow trigger data:', 'workflow-automate' )
			. "\n```json\n"
			. $trigger_json
			. "\n```";
	}

	/**
	 * @param string $response LLM response text.
	 *
	 * @return array<string, mixed>|null
	 */
	private function parseJsonResponse( string $response ): ?array {
		$trimmed = trim( $response );

		if ( '' === $trimmed ) {
			return null;
		}

		$parsed = json_decode( $trimmed, true );

		if ( is_array( $parsed ) ) {
			return $parsed;
		}

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $trimmed, $matches ) ) {
			$parsed = json_decode( trim( $matches[1] ), true );

			if ( is_array( $parsed ) ) {
				return $parsed;
			}
		}

		return null;
	}
}
