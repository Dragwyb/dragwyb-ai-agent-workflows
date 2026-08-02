<?php
/**
 * AI Agent execution engine (tool-calling loop).
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service\Agent;

use AIAWA\Plugin\Service\Ai\AiClientBootstrap;
use AIAWA\Plugin\Service\ConfigInterpolator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs an AI Agent node: memory, LLM iterations, and attached tool execution.
 */
class AgentService {

	private const DEFAULT_MAX_ITERATIONS = 8;

	private const MAX_ITERATIONS_CAP = 20;

	private AgentToolSchemaBuilder $schema_builder;

	private AgentToolExecutor $tool_executor;

	private AgentAiClient $llm_client;

	private AgentStructuredOutputParser $structured_parser;

	public function __construct(
		AgentToolSchemaBuilder $schema_builder,
		AgentToolExecutor $tool_executor,
		AgentAiClient $llm_client
	) {
		$this->schema_builder    = $schema_builder;
		$this->tool_executor     = $tool_executor;
		$this->llm_client        = $llm_client;
		$this->structured_parser = new AgentStructuredOutputParser();
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
				'error'   => __( 'Workflow graph is not available for the AI Agent.', 'dragwyb-agentflow' ),
			);
		}

		$config = $this->normalizeAgentConfig( $config, $graph_nodes, $agent_node_id );

		$validation = AgentValidator::validate( $config, $graph_nodes, $agent_node_id );

		if ( empty( $validation['success'] ) ) {
			return $validation;
		}

		$prompt_result = $this->resolvePrompt( $config, $context, $graph_nodes, $agent_node_id );

		if ( empty( $prompt_result['success'] ) ) {
			return $prompt_result;
		}

		$config['prompt'] = (string) ( $prompt_result['prompt'] ?? '' );

		$settings     = $this->normalizeSettings( $config );
		$max_attempts = ! empty( $settings['retry_on_fail'] ) ? max( 1, (int) $settings['max_tries'] ) : 1;
		$wait_ms      = max( 0, (int) $settings['wait_between_tries_ms'] );
		$last_result  = array(
			'success' => false,
			'error'   => __( 'AI Agent request failed.', 'dragwyb-agentflow' ),
		);

		for ( $attempt = 1; $attempt <= $max_attempts; ++$attempt ) {
			$last_result = $this->executeOnce( $config, $context, $agent_node_id, $graph_nodes );

			if ( ! empty( $last_result['success'] ) ) {
				$last_result['attempt'] = $attempt;
				return $this->finalizeAgentResult( $last_result, $config, $settings );
			}

			if ( $attempt < $max_attempts && $wait_ms > 0 ) {
				usleep( $wait_ms * 1000 );
			}
		}

		return $this->finalizeAgentResult( $last_result, $config, $settings );
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $context
	 * @param string               $agent_node_id
	 * @param array<int, mixed>    $graph_nodes
	 *
	 * @return array<string, mixed>
	 */
	private function executeOnce( array $config, array $context, string $agent_node_id, array $graph_nodes ): array {
		$workflow_id = (int) ( $context['workflow_id'] ?? 0 );
		$attachments = AgentGraphHelper::resolveAttachments( $graph_nodes, $agent_node_id );
		$chat        = AgentGraphHelper::resolveChatModelConfig( $attachments['chat_model'], $config );

		if ( ! AiClientBootstrap::isProviderConfigured( $chat['provider'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No API key configured for the chat model. Add an API key in the Chat Model node.', 'dragwyb-agentflow' ),
			);
		}

		$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

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
			$model,
			$messages,
			$tool_schemas,
			$system_for_call,
			$max_iterations,
			$graph_nodes,
			$workflow_id,
			$context
		);

		if ( empty( $loop['success'] ) && ! empty( $config['fallback_enabled'] ) && is_array( $attachments['fallback_chat_model'] ) ) {
			$loop = $this->tryFallbackModel(
				$config,
				$attachments,
				$messages,
				$tool_schemas,
				$system_for_call,
				$max_iterations,
				$graph_nodes,
				$workflow_id,
				$context,
				$loop
			);
		}

		if ( empty( $loop['success'] ) ) {
			return $loop;
		}

		return $this->formatAgentOutput(
			$loop,
			$config,
			$attachments,
			$graph_nodes,
			$chat,
			$model,
			$agent_node_id,
			$context
		);
	}

	/**
	 * Tries executing agent loop with fallback model when primary model fails.
	 */
	private function tryFallbackModel(
		array $config,
		array $attachments,
		array $messages,
		array $tool_schemas,
		string $system_for_call,
		int $max_iterations,
		array $graph_nodes,
		int $workflow_id,
		array $context,
		array $original_loop
	): array {
		$fallback = AgentGraphHelper::resolveChatModelConfig( $attachments['fallback_chat_model'], $config );

		if ( ! AiClientBootstrap::isProviderConfigured( $fallback['provider'] ) ) {
			return $original_loop;
		}

		$fallback_model = trim( $fallback['model'] );

		if ( '' === $fallback_model ) {
			$fallback_model = $this->defaultModelForProvider( $fallback['provider'] );
		}

		$fallback_loop = $this->runAgentLoop(
			$fallback['provider'],
			$fallback_model,
			$messages,
			$tool_schemas,
			$system_for_call,
			$max_iterations,
			$graph_nodes,
			$workflow_id,
			$context
		);

		if ( ! empty( $fallback_loop['success'] ) ) {
			$fallback_loop['used_fallback_model'] = true;
			$fallback_loop['provider']            = $fallback['provider'];
			$fallback_loop['model']               = $fallback_model;
			return $fallback_loop;
		}

		return $original_loop;
	}

	/**
	 * Formats and parses agent loop outputs for downstream consumption and memory storage.
	 */
	private function formatAgentOutput(
		array $loop,
		array $config,
		array $attachments,
		array $graph_nodes,
		array $chat,
		string $model,
		string $agent_node_id,
		array $context
	): array {
		$response = (string) ( $loop['response'] ?? '' );

		if ( is_array( $attachments['output_parser'] ) ) {
			$fix_provider = $chat['provider'];
			$fix_model    = $model;

			$parser_id    = (string) ( $attachments['output_parser']['id'] ?? '' );
			$parser_model = AgentGraphHelper::findParserChatModel( $graph_nodes, $parser_id );

			if ( is_array( $parser_model ) ) {
				$parser_chat = AgentGraphHelper::resolveChatModelConfig( $parser_model, array() );
				if ( AiClientBootstrap::isProviderConfigured( $parser_chat['provider'] ) ) {
					$fix_provider = $parser_chat['provider'];
					$fix_model    = trim( $parser_chat['model'] );

					if ( '' === $fix_model ) {
						$fix_model = $this->defaultModelForProvider( $fix_provider );
					}
				}
			}

			$structured = $this->applyStructuredOutputParser(
				$response,
				$attachments['output_parser'],
				$fix_provider,
				$fix_model
			);

			if ( empty( $structured['success'] ) ) {
				return $structured;
			}

			$data             = $structured['data'];
			$encoded          = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
			$loop['response'] = is_string( $encoded ) ? $encoded : '';
			$loop['parsed']   = $data;
			$loop['output']   = $data;
			$loop['json']     = is_array( $data ) ? $data : array( 'output' => $data );

			if ( ! empty( $structured['auto_fixed'] ) ) {
				$loop['structured_output_auto_fixed'] = true;
			}
		} else {
			$parsed = $this->parseJsonResponse( $response );

			if ( isset( $config['output_format'] ) && 'json' === $config['output_format'] ) {
				if ( is_array( $parsed ) ) {
					$loop['parsed'] = $parsed;
				} elseif ( ! empty( $attachments['tools'] ) || ! empty( $loop['tool_calls'] ) ) {
					$loop['json_parse_warning'] = __( 'Reply was plain text, not JSON (expected when the agent uses tools).', 'dragwyb-agentflow' );
				} else {
					return array(
						'success'  => false,
						'error'    => __( 'The agent did not return valid JSON.', 'dragwyb-agentflow' ),
						'response' => $response,
					);
				}
			}

			$loop['response'] = $response;
			$clean_output     = $this->buildCleanOutput( $response, $config );
			$loop['output']   = $clean_output;

			if ( is_array( $parsed ) ) {
				$loop['parsed'] = $parsed;
				$loop['json']   = $parsed;
			} else {
				$loop['json'] = array(
					'output' => $clean_output,
				);
			}
		}

		if ( empty( $loop['provider'] ) ) {
			$loop['provider'] = $chat['provider'];
		}

		if ( empty( $loop['model'] ) ) {
			$loop['model'] = $model;
		}

		if ( ! empty( $attachments['memory'] ) && is_array( $attachments['memory'] ) && isset( $loop['conversation_messages'] ) ) {
			$memory_store = $this->memoryStore( $agent_node_id, $attachments['memory'], $config, $context );
			$memory_store->store( $loop['conversation_messages'] );
			unset( $loop['conversation_messages'] );
		}

		return $loop;
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<int, mixed>    $graph_nodes
	 * @param string               $agent_node_id
	 *
	 * @return array<string, mixed>
	 */
	private function normalizeAgentConfig( array $config, array $graph_nodes, string $agent_node_id ): array {
		$attachments = AgentGraphHelper::resolveAttachments( $graph_nodes, $agent_node_id );

		if ( ! empty( $config['require_output_format'] ) || null !== $attachments['output_parser'] ) {
			$config['output_format'] = 'json';
		}

		return $config;
	}

	/**
	 * @param array<string, mixed> $config
	 *
	 * @return array<string, mixed>
	 */
	private function normalizeSettings( array $config ): array {
		$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();

		return array(
			'always_output_data'    => ! empty( $settings['always_output_data'] ),
			'execute_once'          => ! empty( $settings['execute_once'] ),
			'retry_on_fail'         => ! empty( $settings['retry_on_fail'] ),
			'max_tries'             => max( 1, (int) ( $settings['max_tries'] ?? 3 ) ),
			'wait_between_tries_ms' => max( 0, (int) ( $settings['wait_between_tries_ms'] ?? 1000 ) ),
			'on_error'              => (string) ( $settings['on_error'] ?? 'stop_workflow' ),
			'notes'                 => (string) ( $settings['notes'] ?? '' ),
			'display_note_in_flow'  => ! empty( $settings['display_note_in_flow'] ),
		);
	}

	/**
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $context
	 * @param array<int, mixed>    $graph_nodes
	 * @param string               $agent_node_id
	 *
	 * @return array{success: bool, prompt?: string, error?: string}
	 */
	private function resolvePrompt( array $config, array $context, array $graph_nodes, string $agent_node_id ): array {
		$prompt_source = isset( $config['prompt_source'] ) ? (string) $config['prompt_source'] : AgentValidator::PROMPT_SOURCE_DEFINE;

		if ( AgentValidator::PROMPT_SOURCE_DEFINE === $prompt_source ) {
			$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

			if ( '' === $prompt ) {
				return array(
					'success' => false,
					'error'   => __( 'No prompt configured for the AI Agent.', 'dragwyb-agentflow' ),
				);
			}

			return array(
				'success' => true,
				'prompt'  => $prompt,
			);
		}

		$prompt = $this->resolveChatTriggerPrompt( $context, $graph_nodes, $agent_node_id );

		if ( '' === $prompt ) {
			return array(
				'success' => false,
				'error'   => __( 'No chatInput value found from a connected Chat Trigger node.', 'dragwyb-agentflow' ),
			);
		}

		return array(
			'success' => true,
			'prompt'  => $prompt,
		);
	}

	/**
	 * @param array<string, mixed> $context
	 * @param array<int, mixed>    $graph_nodes
	 * @param string               $agent_node_id
	 *
	 * @return string
	 */
	private function resolveChatTriggerPrompt( array $context, array $graph_nodes, string $agent_node_id ): string {
		$trigger = $context['trigger'] ?? null;

		if ( is_array( $trigger ) ) {
			foreach ( array( 'chatInput', 'chat_input', 'message', 'prompt' ) as $key ) {
				if ( isset( $trigger[ $key ] ) && is_scalar( $trigger[ $key ] ) ) {
					$prompt = trim( (string) $trigger[ $key ] );

					if ( '' !== $prompt ) {
						return $prompt;
					}
				}
			}
		}

		$graph       = isset( $context['graph'] ) && is_array( $context['graph'] ) ? $context['graph'] : array();
		$connections = isset( $graph['connections'] ) && is_array( $graph['connections'] ) ? $graph['connections'] : array();

		foreach ( $connections as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			if ( (string) ( $connection['to'] ?? '' ) !== $agent_node_id ) {
				continue;
			}

			$source_id = (string) ( $connection['from'] ?? '' );

			if ( '' === $source_id ) {
				continue;
			}

			$source_output = $context['nodes'][ $source_id ] ?? null;

			if ( ! is_array( $source_output ) ) {
				continue;
			}

			foreach ( array( 'chatInput', 'chat_input', 'response', 'message', 'prompt' ) as $key ) {
				if ( isset( $source_output[ $key ] ) && is_scalar( $source_output[ $key ] ) ) {
					$prompt = trim( (string) $source_output[ $key ] );

					if ( '' !== $prompt ) {
						return $prompt;
					}
				}
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $result
	 * @param array<string, mixed> $config
	 * @param array<string, mixed> $settings
	 *
	 * @return array<string, mixed>
	 */
	private function finalizeAgentResult( array $result, array $config, array $settings ): array {
		if ( ! empty( $result['success'] ) ) {
			return $result;
		}

		if ( 'continue' === $settings['on_error'] || 'continue_error_output' === $settings['on_error'] ) {
			$output = array(
				'success'  => true,
				'response' => '',
				'output'   => '',
				'json'     => array( 'output' => '' ),
				'error'    => (string) ( $result['error'] ?? __( 'AI Agent request failed.', 'dragwyb-agentflow' ) ),
			);

			if ( 'continue_error_output' === $settings['on_error'] ) {
				$output['error_output'] = $output['error'];
			}

			if ( ! empty( $settings['always_output_data'] ) ) {
				$encoded            = wp_json_encode( $output ) ?: '{}';
				$output['response'] = $encoded;
				$output['output']   = $encoded;
				$output['json']     = array( 'output' => $encoded );
			}

			return $output;
		}

		return $result;
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

		/**
		 * Filters the AI Agent max tool-loop iterations (capped).
		 *
		 * @param int                  $max    Requested iterations.
		 * @param array<string, mixed> $config Agent node config.
		 */
		$max = (int) apply_filters( 'aiawa_agent_max_iterations', $max, $config );

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
	 * @param array<string, mixed> $config        Agent config.
	 * @param array<string, mixed> $context       Context.
	 * @param string               $agent_node_id Agent id.
	 * @param array<string, mixed> $attachments   Resolved attachments.
	 * @param string               $system_prompt System prompt.
	 * @param string               $user_message  User message.
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
		string $model,
		array $messages,
		array $tool_schemas,
		string $system_prompt,
		int $max_iterations,
		array $graph_nodes,
		int $workflow_id,
		array $context
	): array {
		$iteration       = 0;
		$all_tool_calls  = array();
		$seen_calls      = array();
		$created_post_id = 0;
		$messages        = $this->sanitizeMessages( $messages );

		while ( $iteration < $max_iterations ) {
			++$iteration;

			$completion = $this->llm_client->complete(
				$provider,
				$model,
				$messages,
				$tool_schemas,
				$system_prompt
			);

			if ( empty( $completion['success'] ) ) {
				return array(
					'success' => false,
					'error'   => $completion['error'] ?? __( 'AI Agent request failed.', 'dragwyb-agentflow' ),
				);
			}

			$message = $completion['message'] ?? array();

			if ( ! is_array( $message ) ) {
				return array(
					'success' => false,
					'error'   => __( 'The AI model returned an invalid message.', 'dragwyb-agentflow' ),
				);
			}

			$messages[] = $message;

			if ( empty( $message['tool_calls'] ) || ! is_array( $message['tool_calls'] ) ) {
				return array(
					'success'               => true,
					'response'              => (string) ( $message['content'] ?? '' ),
					'iterations'            => $iteration,
					'finish_reason'         => (string) ( $completion['finish_reason'] ?? 'stop' ),
					'tool_calls'            => $this->formatToolCalls( $all_tool_calls ),
					'conversation_messages' => $messages,
				);
			}

			foreach ( $message['tool_calls'] as $tool_call ) {
				if ( ! is_array( $tool_call ) ) {
					continue;
				}

				$this->executeSingleToolCall(
					$tool_call,
					$graph_nodes,
					$workflow_id,
					$context,
					$seen_calls,
					$created_post_id,
					$all_tool_calls,
					$messages
				);
			}
		}

		return array(
			'success'    => false,
			'error'      => __( 'Max iterations reached.', 'dragwyb-agentflow' ),
			'iterations' => $iteration,
			'tool_calls' => $this->formatToolCalls( $all_tool_calls ),
		);
	}

	/**
	 * Executes a single tool call requested by the LLM, managing duplicate caches and post creation limits.
	 */
	private function executeSingleToolCall(
		array $tool_call,
		array $graph_nodes,
		int $workflow_id,
		array $context,
		array &$seen_calls,
		int &$created_post_id,
		array &$all_tool_calls,
		array &$messages
	): void {
		$tool_call_id  = (string) ( $tool_call['id'] ?? wp_generate_uuid4() );
		$function      = is_array( $tool_call['function'] ?? null ) ? $tool_call['function'] : array();
		$function_name = (string) ( $function['name'] ?? '' );
		$raw_args      = $function['arguments'] ?? '{}';
		$args          = $this->parseToolArguments( $raw_args );

		$signature = md5( $function_name . wp_json_encode( $args ) );

		if ( isset( $seen_calls[ $signature ] ) && is_array( $seen_calls[ $signature ] ) ) {
			$tool_result = $seen_calls[ $signature ];
			if ( ! isset( $tool_result['note'] ) ) {
				$tool_result['note'] = __( 'Repeated identical tool call — returning cached result. Do not call the same tool with the same arguments again; continue with a different step or give the final answer.', 'dragwyb-agentflow' );
			}
		} elseif ( $created_post_id > 0 && $this->isCreatePostToolName( $function_name ) ) {
			$tool_result              = array(
				'error'   => sprintf(
					/* translators: %d: existing post id */
					__( 'A post/page was already created in this run (ID %d). Do not create another. Use Update Post with that post_id for any changes, then give the final answer.', 'dragwyb-agentflow' ),
					$created_post_id
				),
				'post_id' => $created_post_id,
			);
			$seen_calls[ $signature ] = $tool_result;
		} else {
			$tool_result              = $this->tool_executor->execute(
				$function_name,
				$args,
				$graph_nodes,
				$workflow_id,
				$context
			);
			$seen_calls[ $signature ] = $tool_result;

			if ( $this->isCreatePostToolName( $function_name ) ) {
				$new_id = $this->extractCreatedPostId( $tool_result );
				if ( $new_id > 0 ) {
					$created_post_id = $new_id;
				}
			}
		}

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
			$parts[] = __( 'You have tools available. Use them to complete the task. Workflow trigger data is included in the user message when present—use it to fill tool parameters.', 'dragwyb-agentflow' );
			$parts[] = __( 'When calling tools, pass the final text with real values from the trigger data (customer name, order ID, amounts, etc.). Do not use {{placeholder}} or template syntax in tool arguments—write the complete message as plain text.', 'dragwyb-agentflow' );
			$parts[] = __( 'Tool usage rules: Read tool results before claiming success. Prefer IDs returned by previous tools. For list tools, use limit/search filters—do not dump entire databases. Omit optional URL fields (images, media) unless you have a real direct URL. If a tool returns a warning, the main action still succeeded—do not create another post/page to “fix” it; report once and finish. Create Post at most once per task; for fixes use Update Post with the returned post_id. Never repeat the same tool call; use the prior result and continue.', 'dragwyb-agentflow' );

			if ( $this->attachmentsIncludePostTools( $attachments['tools'] ) ) {
				$parts[] = __( 'WordPress page/post design rules: When the user asks for a page, set post_type to "page". For designed/modern/colorful layouts, pass design_sections as a JSON array of sections (types: hero, heading, paragraph, columns, cta, buttons, spacer, separator, group) with colors—do not rely on a single plain paragraph. You may instead put full Gutenberg block markup (<!-- wp:... -->) in content. Omit featured_image unless you have a real direct image URL (not example.com or generic search URLs). Never claim the page was designed unless design_sections or Gutenberg block markup was used.', 'dragwyb-agentflow' );
				$parts[] = __( 'Post type from trigger: If the workflow trigger includes post_type (e.g. page), pass that same post_type to Create/Update Post unless the user explicitly requests a different type. Do not default everything to "post" when the trigger saved a page.', 'dragwyb-agentflow' );
			}
		}

		$parser_resolved = null;

		if ( is_array( $attachments['output_parser'] ?? null ) ) {
			$parser_resolved = $this->structured_parser->resolve( $attachments['output_parser'] );

			if ( isset( $parser_resolved['success'] ) && false === $parser_resolved['success'] ) {
				// Invalid schema is reported at parse time; still prefer JSON-only guidance.
				$parts[] = __( 'Respond with valid JSON only. Do not include markdown fences or text outside the JSON object.', 'dragwyb-agentflow' );
			} elseif ( ! empty( $parser_resolved['instructions'] ) ) {
				$parts[] = (string) $parser_resolved['instructions'];
			}
		} elseif ( isset( $config['output_format'] ) && 'json' === $config['output_format'] && empty( $attachments['tools'] ) ) {
			$parts[] = __( 'Respond with valid JSON only. Do not include markdown fences or text outside the JSON object.', 'dragwyb-agentflow' );
		} elseif ( empty( $attachments['tools'] ) ) {
			$parts[] = __( 'Reply with the final answer as plain text only — a single short string. Never return Python, JavaScript, curl, HTTP examples, markdown code fences, or wrappers. If the user asks for a joke, return only the joke words.', 'dragwyb-agentflow' );
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * @param array<int, mixed> $tools Attached tool nodes.
	 */
	private function attachmentsIncludePostTools( array $tools ): bool {
		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}
			$type = (string) ( $tool['type'] ?? '' );
			if ( 'wp_create_post_action' === $type || 'wp_update_post_action' === $type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $function_name LLM tool function name.
	 */
	private function isCreatePostToolName( string $function_name ): bool {
		return 0 === strpos( $function_name, 'wp_create_post_action' );
	}

	/**
	 * @param array<string, mixed> $tool_result Tool execution result.
	 */
	private function extractCreatedPostId( array $tool_result ): int {
		if ( ! empty( $tool_result['error'] ) ) {
			return 0;
		}

		if ( isset( $tool_result['post_id'] ) ) {
			return (int) $tool_result['post_id'];
		}

		if ( isset( $tool_result['data']['post_id'] ) ) {
			return (int) $tool_result['data']['post_id'];
		}

		return 0;
	}

	/**
	 * Validates agent text against the attached Structured Output Parser schema.
	 *
	 * @param string               $response    Raw model reply.
	 * @param array<string, mixed> $parser_node Output parser attachment.
	 * @param string               $provider    Chat provider.
	 * @param string               $model       Model id.
	 *
	 * @return array{success: true, data: mixed, auto_fixed?: bool}|array{success: false, error: string, response?: string}
	 */
	private function applyStructuredOutputParser(
		string $response,
		array $parser_node,
		string $provider,
		string $model
	): array {
		$resolved = $this->structured_parser->resolve( $parser_node );

		if ( isset( $resolved['success'] ) && false === $resolved['success'] ) {
			return $resolved;
		}

		/** @var array{schema: array<string, mixed>, auto_fix: bool, retry_prompt: string, instructions: string} $resolved */
		$parsed = $this->structured_parser->parseAndValidate( $response, $resolved['schema'] );

		if ( ! empty( $parsed['success'] ) ) {
			return array(
				'success' => true,
				'data'    => $parsed['data'],
			);
		}

		if ( empty( $resolved['auto_fix'] ) ) {
			return array(
				'success'  => false,
				'error'    => sprintf(
					/* translators: %s: validation error */
					__( 'Structured output validation failed: %s', 'dragwyb-agentflow' ),
					(string) ( $parsed['error'] ?? '' )
				),
				'response' => $response,
			);
		}

		$retry_user = $this->structured_parser->buildRetryUserMessage(
			$resolved['retry_prompt'],
			$resolved['instructions'],
			$response,
			(string) ( $parsed['error'] ?? '' )
		);

		$completion = $this->llm_client->complete(
			$provider,
			$model,
			array(
				array(
					'role'    => 'user',
					'content' => $retry_user,
				),
			),
			array(),
			$resolved['instructions']
		);

		if ( empty( $completion['success'] ) ) {
			return array(
				'success'  => false,
				'error'    => $completion['error'] ?? __( 'Structured output auto-fix request failed.', 'dragwyb-agentflow' ),
				'response' => $response,
			);
		}

		$message      = is_array( $completion['message'] ?? null ) ? $completion['message'] : array();
		$fixed_reply  = (string) ( $message['content'] ?? '' );
		$fixed_parsed = $this->structured_parser->parseAndValidate( $fixed_reply, $resolved['schema'] );

		if ( empty( $fixed_parsed['success'] ) ) {
			return array(
				'success'  => false,
				'error'    => sprintf(
					/* translators: %s: validation error */
					__( 'Structured output still invalid after auto-fix: %s', 'dragwyb-agentflow' ),
					(string) ( $fixed_parsed['error'] ?? '' )
				),
				'response' => $fixed_reply,
			);
		}

		return array(
			'success'    => true,
			'data'       => $fixed_parsed['data'],
			'auto_fixed' => true,
		);
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
			. __( 'Workflow trigger data:', 'dragwyb-agentflow' )
			. "\n```json\n"
			. $trigger_json
			. "\n```";
	}

	/**
	 * Builds the downstream `output` value from the raw model response.
	 *
	 * @param string               $response Raw model text.
	 * @param array<string, mixed> $config   Agent config.
	 *
	 * @return string
	 */
	private function buildCleanOutput( string $response, array $config ): string {
		$clean_enabled = ! array_key_exists( 'clean_output', $config ) || ! empty( $config['clean_output'] );

		if ( ! $clean_enabled ) {
			return $this->normalizeOutputForTransport( $response );
		}

		$stripped  = $this->stripMarkdownFences( $response );
		$extracted = $this->extractUsefulText( $stripped );
		$text      = '' !== $extracted ? $extracted : $stripped;

		return $this->normalizeOutputForTransport( $text );
	}

	/**
	 * Makes agent text safe to embed in JSON / HTTP bodies: collapses control
	 * characters and trims whitespace so downstream nodes receive a single-line string.
	 *
	 * @param string $text Cleaned model text.
	 *
	 * @return string
	 */
	private function normalizeOutputForTransport( string $text ): string {
		$text      = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $text );
		$stripped  = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		$text      = is_string( $stripped ) ? $stripped : $text;
		$collapsed = preg_replace( '/\s+/', ' ', $text );
		$text      = is_string( $collapsed ) ? $collapsed : $text;

		return trim( $text );
	}

	/**
	 * Removes surrounding markdown code fences so HTTP Request / Set nodes
	 * receive plain usable text instead of ```python ... ``` wrappers.
	 *
	 * @param string $text Raw model text.
	 *
	 * @return string
	 */
	private function stripMarkdownFences( string $text ): string {
		$trimmed = trim( $text );

		if ( '' === $trimmed ) {
			return '';
		}

		// Whole reply is a single fenced block.
		if ( preg_match( '/^```[a-zA-Z0-9_-]*\s*\r?\n([\s\S]*?)\r?\n```$/', $trimmed, $matches ) ) {
			return trim( (string) $matches[1] );
		}

		// One or more fenced blocks inside other text — prefer first fence body
		// when it is the dominant content.
		if ( preg_match( '/```[a-zA-Z0-9_-]*\s*\r?\n([\s\S]*?)\r?\n```/', $trimmed, $matches ) ) {
			$inner          = trim( (string) $matches[1] );
			$without_fences = trim(
				(string) preg_replace( '/```[a-zA-Z0-9_-]*\s*\r?\n[\s\S]*?\r?\n```/', '', $trimmed )
			);

			if ( '' === $without_fences || strlen( $inner ) >= strlen( $without_fences ) ) {
				return $inner;
			}
		}

		// Strip leftover fence markers if the model omitted closing fences.
		$trimmed = (string) preg_replace( '/^```[a-zA-Z0-9_-]*\s*\r?\n?/', '', $trimmed );
		$trimmed = (string) preg_replace( '/\r?\n?```$/', '', $trimmed );

		return trim( $trimmed );
	}

	/**
	 * Pulls the useful human answer out of code-shaped model replies.
	 *
	 * Example input:
	 *   import requests
	 *   requests.get("...", params={"joke": "Parallel lines have so much in common. Shame."})
	 *
	 * Example output:
	 *   Parallel lines have so much in common. Shame.
	 *
	 * @param string $text Fence-stripped model text.
	 *
	 * @return string Empty string when no better value was found.
	 */
	private function extractUsefulText( string $text ): string {
		$trimmed = trim( $text );

		if ( '' === $trimmed ) {
			return '';
		}

		// Already plain text — keep as-is.
		if ( ! $this->looksLikeCodeOrWrappedPayload( $trimmed ) ) {
			return $trimmed;
		}

		// Prefer explicit answer keys (joke, output, message, …).
		foreach ( array( 'joke', 'output', 'message', 'text', 'content', 'answer', 'result', 'name' ) as $key ) {
			$value = $this->extractKeyedString( $trimmed, $key );

			if ( '' !== $value ) {
				return $value;
			}
		}

		// JSON object with a single useful string field.
		$json = json_decode( $trimmed, true );

		if ( is_array( $json ) ) {
			foreach ( array( 'joke', 'output', 'message', 'text', 'content', 'answer', 'result', 'name' ) as $key ) {
				if ( isset( $json[ $key ] ) && is_scalar( $json[ $key ] ) ) {
					$value = trim( (string) $json[ $key ] );

					if ( '' !== $value ) {
						return $value;
					}
				}
			}
		}

		// Fallback: best non-URL quoted string inside the code.
		$quoted = $this->extractBestQuotedString( $trimmed );

		return $quoted;
	}

	/**
	 * @param string $text Candidate text.
	 *
	 * @return bool
	 */
	private function looksLikeCodeOrWrappedPayload( string $text ): bool {
		if ( preg_match( '/^\s*[{[]/', $text ) ) {
			$decoded = json_decode( $text, true );

			if ( is_array( $decoded ) ) {
				return true;
			}
		}

		return (bool) preg_match(
			'/\b(import\s+\w+|from\s+\w+\s+import|requests\.|fetch\(|curl\s|def\s+\w+\s*\(|console\.log|params\s*=)/i',
			$text
		);
	}

	/**
	 * @param string $text Source text.
	 * @param string $key  Field name to extract.
	 *
	 * @return string
	 */
	private function extractKeyedString( string $text, string $key ): string {
		$pattern = '/["\']?' . preg_quote( $key, '/' ) . '["\']?\s*[:=]\s*(["\'])(.*?)\1/s';

		if ( ! preg_match( $pattern, $text, $matches ) ) {
			return '';
		}

		$value = trim( (string) ( $matches[2] ?? '' ) );

		if ( '' === $value || $this->looksLikeUrl( $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * @param string $text Source text.
	 *
	 * @return string
	 */
	private function extractBestQuotedString( string $text ): string {
		if ( ! preg_match_all( '/(["\'])([^"\']{8,400})\1/', $text, $matches ) ) {
			return '';
		}

		$best       = '';
		$best_score = -1;

		foreach ( $matches[2] as $candidate ) {
			$candidate = trim( (string) $candidate );

			if ( '' === $candidate || $this->looksLikeUrl( $candidate ) ) {
				continue;
			}

			$score = strlen( $candidate );

			if ( preg_match( '/\s/', $candidate ) ) {
				$score += 50;
			}

			if ( preg_match( '/[.!?]$/', $candidate ) ) {
				$score += 20;
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $candidate;
			}
		}

		return $best;
	}

	/**
	 * @param string $value Candidate string.
	 *
	 * @return bool
	 */
	private function looksLikeUrl( string $value ): bool {
		return (bool) preg_match( '#^https?://#i', $value );
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
