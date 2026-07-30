<?php
/**
 * LLM client backed by WordPress AI Client (prompt + tools).
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service\Agent;

use AIAWAB\Plugin\Service\Ai\AiClientBootstrap;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges OpenAI-style agent messages/tools to wp_ai_client_prompt().
 */
class AgentAiClient {

	/**
	 * Run a chat completion (optionally with tools) via the WP AI Client.
	 *
	 * @param string            $provider      WFA provider slug (openai|claude|gemini|openrouter|groq|deepseek).
	 * @param string            $model         Model id.
	 * @param array<int, mixed> $messages      OpenAI-style messages.
	 * @param array<int, mixed> $tools         OpenAI-style tool schemas.
	 * @param string            $system_prompt System prompt when not already in messages.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	public function complete(
		string $provider,
		string $model,
		array $messages,
		array $tools = array(),
		string $system_prompt = ''
	): array {
		if ( ! AiClientBootstrap::isAvailable() ) {
			return array(
				'success' => false,
				'error'   => __( 'WordPress AI Client is not available.', 'workflow-automate' ),
			);
		}

		$provider_id = AiClientBootstrap::resolveProviderId( $provider );

		$auth = AiClientBootstrap::ensureProviderAuthentication( $provider );
		if ( is_wp_error( $auth ) ) {
			return array(
				'success' => false,
				'error'   => $auth->get_error_message(),
			);
		}

		if ( ! AiClientBootstrap::isProviderConfigured( $provider ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'No API key configured for %s. Add an API key in this node.', 'workflow-automate' ),
					$provider_id
				),
			);
		}

		try {
			$converted    = $this->toAiMessages( $messages, $system_prompt );
			$ai_messages  = $converted['messages'];
			$system_instr = $converted['system'];
			$decls        = $this->toFunctionDeclarations( $tools );

			$builder = wp_ai_client_prompt( $ai_messages );
			$builder->using_provider( $provider_id );
			$builder->using_model_preference( array( $provider_id, $model ) );

			if ( '' !== $system_instr ) {
				$builder->using_system_instruction( $system_instr );
			}

			if ( ! empty( $decls ) ) {
				$builder->using_function_declarations( ...$decls );
			}

			$result = $builder->generate_text_result();

			if ( $result instanceof WP_Error ) {
				return array(
					'success' => false,
					'error'   => $result->get_error_message(),
				);
			}

			if ( ! $result instanceof GenerativeAiResult ) {
				return array(
					'success' => false,
					'error'   => __( 'The AI client returned an unexpected result.', 'workflow-automate' ),
				);
			}

			return $this->normalizeResult( $result );
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Simple text completion helper for chat actions.
	 *
	 * @param string $provider      WFA provider slug.
	 * @param string $model         Model id.
	 * @param string $prompt        User prompt.
	 * @param string $system_prompt Optional system prompt.
	 *
	 * @return array{success: bool, error?: string, content?: string, model?: string}
	 */
	public function completeSimple(
		string $provider,
		string $model,
		string $prompt,
		string $system_prompt = ''
	): array {
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$result = $this->complete( $provider, $model, $messages, array(), $system_prompt );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => $result['error'] ?? __( 'AI request failed.', 'workflow-automate' ),
			);
		}

		$message = is_array( $result['message'] ?? null ) ? $result['message'] : array();

		return array(
			'success' => true,
			'model'   => $model,
			'content' => (string) ( $message['content'] ?? '' ),
		);
	}

	/**
	 * @param array<int, mixed> $messages      OpenAI-style messages.
	 * @param string            $system_prompt Fallback system prompt.
	 *
	 * @return array{messages: list<Message>, system: string}
	 */
	private function toAiMessages( array $messages, string $system_prompt ): array {
		$out          = array();
		$system_parts = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = strtolower( (string) ( $message['role'] ?? 'user' ) );

			if ( 'system' === $role ) {
				$content = $this->stringifyContent( $message['content'] ?? '' );
				if ( '' !== $content ) {
					$system_parts[] = $content;
				}
				continue;
			}

			if ( 'tool' === $role ) {
				$tool_call_id = (string) ( $message['tool_call_id'] ?? '' );
				$name         = (string) ( $message['name'] ?? '' );
				$response     = $message['content'] ?? '';
				if ( is_string( $response ) ) {
					$decoded = json_decode( $response, true );
					if ( null !== $decoded ) {
						$response = $decoded;
					}
				}
				$out[] = new Message(
					MessageRoleEnum::user(),
					array(
						new MessagePart(
							new FunctionResponse(
								'' !== $tool_call_id ? $tool_call_id : null,
								'' !== $name ? $name : 'tool',
								$response
							)
						),
					)
				);
				continue;
			}

			if ( 'assistant' === $role || 'model' === $role ) {
				$parts = array();
				$text  = $this->stringifyContent( $message['content'] ?? '' );
				if ( '' !== $text ) {
					$parts[] = new MessagePart( $text );
				}

				$tool_calls = $message['tool_calls'] ?? null;
				if ( is_array( $tool_calls ) ) {
					foreach ( $tool_calls as $tool_call ) {
						if ( ! is_array( $tool_call ) ) {
							continue;
						}
						$fn   = is_array( $tool_call['function'] ?? null ) ? $tool_call['function'] : array();
						$name = (string) ( $fn['name'] ?? '' );
						$id   = (string) ( $tool_call['id'] ?? '' );
						$args = $fn['arguments'] ?? array();
						if ( is_string( $args ) ) {
							$decoded = json_decode( $args, true );
							$args    = is_array( $decoded ) ? $decoded : array();
						}
						if ( '' === $name && '' === $id ) {
							continue;
						}
						$parts[] = new MessagePart(
							new FunctionCall(
								'' !== $id ? $id : null,
								'' !== $name ? $name : null,
								$args
							)
						);
					}
				}

				if ( empty( $parts ) ) {
					$parts[] = new MessagePart( '' );
				}

				$out[] = new Message( MessageRoleEnum::model(), $parts );
				continue;
			}

			$text  = $this->stringifyContent( $message['content'] ?? '' );
			$out[] = new Message(
				MessageRoleEnum::user(),
				array( new MessagePart( '' !== $text ? $text : ' ' ) )
			);
		}

		$system = ! empty( $system_parts )
			? implode( "\n\n", $system_parts )
			: trim( $system_prompt );

		if ( empty( $out ) ) {
			$out[] = new Message(
				MessageRoleEnum::user(),
				array( new MessagePart( ' ' ) )
			);
		}

		return array(
			'messages' => $out,
			'system'   => $system,
		);
	}

	/**
	 * @param array<int, mixed> $tools OpenAI-style tools.
	 *
	 * @return list<FunctionDeclaration>
	 */
	private function toFunctionDeclarations( array $tools ): array {
		$decls = array();

		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}

			$fn = $tool;
			if ( isset( $tool['function'] ) && is_array( $tool['function'] ) ) {
				$fn = $tool['function'];
			} elseif ( isset( $tool['type'] ) && 'function' === $tool['type'] && isset( $tool['function'] ) ) {
				$fn = is_array( $tool['function'] ) ? $tool['function'] : array();
			}

			$name = (string) ( $fn['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$description = (string) ( $fn['description'] ?? '' );
			$parameters  = isset( $fn['parameters'] ) && is_array( $fn['parameters'] ) ? $fn['parameters'] : null;

			$decls[] = new FunctionDeclaration( $name, $description, $parameters );
		}

		return $decls;
	}

	/**
	 * @param GenerativeAiResult $result SDK result.
	 *
	 * @return array{success: bool, message: array<string, mixed>, finish_reason: string}
	 */
	private function normalizeResult( GenerativeAiResult $result ): array {
		$candidate     = $result->getCandidates()[0] ?? null;
		$finish_reason = 'stop';
		$content       = '';
		$tool_calls    = array();

		if ( null !== $candidate ) {
			$finish = $candidate->getFinishReason();
			if ( $finish->isToolCalls() ) {
				$finish_reason = 'tool_calls';
			} else {
				$finish_reason = (string) $finish->value;
			}

			$message = $candidate->getMessage();
			foreach ( $message->getParts() as $part ) {
				$type = $part->getType();
				if ( $type->isText() ) {
					$content .= (string) $part->getText();
				} elseif ( $type->isFunctionCall() ) {
					$call = $part->getFunctionCall();
					if ( null === $call ) {
						continue;
					}
					$args         = $call->getArgs();
					$tool_calls[] = array(
						'id'       => (string) ( $call->getId() ?? wp_generate_uuid4() ),
						'type'     => 'function',
						'function' => array(
							'name'      => (string) ( $call->getName() ?? '' ),
							'arguments' => is_string( $args ) ? $args : ( wp_json_encode( $args ) ?: '{}' ),
						),
					);
				}
			}
		} else {
			$content = (string) $result->toText();
		}

		$openai_message = array(
			'role'    => 'assistant',
			'content' => $content,
		);

		if ( ! empty( $tool_calls ) ) {
			$openai_message['tool_calls'] = $tool_calls;
			if ( 'stop' === $finish_reason ) {
				$finish_reason = 'tool_calls';
			}
		}

		return array(
			'success'       => true,
			'message'       => $openai_message,
			'finish_reason' => $finish_reason,
		);
	}

	/**
	 * @param mixed $content Message content.
	 */
	private function stringifyContent( $content ): string {
		if ( is_string( $content ) ) {
			return $content;
		}

		if ( is_array( $content ) ) {
			// OpenAI multimodal content parts.
			$texts = array();
			foreach ( $content as $part ) {
				if ( is_string( $part ) ) {
					$texts[] = $part;
				} elseif ( is_array( $part ) && isset( $part['text'] ) ) {
					$texts[] = (string) $part['text'];
				}
			}

			return implode( "\n", $texts );
		}

		if ( is_scalar( $content ) ) {
			return (string) $content;
		}

		return '';
	}
}
