<?php
/**
 * LLM API client for AI Agent tool-calling loops.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

use WorkflowAutomate\Plugin\Integration\Actions\TelegramSendMessageAction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes chat completions across OpenAI, Claude, and Gemini for the agent loop.
 */
class AgentLlmClient {

	private const TIMEOUT_SECONDS = 120;

	private const CLAUDE_API_VERSION = '2023-06-01';

	/**
	 * OpenAI-compatible chat completion endpoints keyed by provider slug.
	 *
	 * @var array<string, array{url: string, label: string, extra_headers?: array<string, string>}>
	 */
	private const OPENAI_COMPATIBLE = array(
		'openai' => array(
			'url' => 'https://api.openai.com/v1/chat/completions',
			'label' => 'OpenAI',
		),
		'openrouter' => array(
			'url' => 'https://openrouter.ai/api/v1/chat/completions',
			'label' => 'OpenRouter',
			'extra_headers' => array(
				'HTTP-Referer' => '',
				'X-Title' => '',
			),
		),
		'groq' => array(
			'url' => 'https://api.groq.com/openai/v1/chat/completions',
			'label' => 'Groq',
		),
		'deepseek' => array(
			'url' => 'https://api.deepseek.com/chat/completions',
			'label' => 'DeepSeek',
		),
	);

	/**
	 * @param string               $provider    openai|claude|gemini|openrouter|groq|deepseek.
	 * @param string               $api_key     API key.
	 * @param string               $model       Model id.
	 * @param array<int, mixed>    $messages    OpenAI-style messages.
	 * @param array<int, mixed>    $tools       OpenAI-style tool schemas.
	 * @param string               $system_prompt System prompt when not already in messages.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	public function complete(
		string $provider,
		string $api_key,
		string $model,
		array $messages,
		array $tools,
		string $system_prompt = ''
	): array {
		if ( 'claude' === $provider ) {
			return $this->completeClaude( $api_key, $model, $messages, $tools, $system_prompt );
		}

		if ( 'gemini' === $provider ) {
			return $this->completeGemini( $api_key, $model, $messages, $tools, $system_prompt );
		}

		$config = self::OPENAI_COMPATIBLE[ $provider ] ?? self::OPENAI_COMPATIBLE['openai'];

		return $this->completeOpenAiCompatible( $api_key, $model, $messages, $tools, $config );
	}

	/**
	 * @param string            $api_key  API key.
	 * @param string            $model    Model id.
	 * @param array<int, mixed> $messages Messages.
	 * @param array<int, mixed> $tools    Tool schemas.
	 * @param array<string, mixed> $config Endpoint config from OPENAI_COMPATIBLE.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	private function completeOpenAiCompatible(
		string $api_key,
		string $model,
		array $messages,
		array $tools,
		array $config
	): array {
		$label = (string) ( $config['label'] ?? 'OpenAI' );
		$url   = (string) ( $config['url'] ?? self::OPENAI_COMPATIBLE['openai']['url'] );

		$payload = array(
			'model'    => $model,
			'messages' => $messages,
		);

		if ( array() !== $tools ) {
			$payload['tools']       = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		);

		if ( isset( $config['extra_headers'] ) && is_array( $config['extra_headers'] ) ) {
			foreach ( $config['extra_headers'] as $header_name => $header_value ) {
				if ( 'HTTP-Referer' === $header_name && '' === $header_value ) {
					$headers[ $header_name ] = home_url( '/' );
					continue;
				}

				if ( 'X-Title' === $header_name && '' === $header_value ) {
					$headers[ $header_name ] = get_bloginfo( 'name' ) ?: 'Workflow Automate';
					continue;
				}

				if ( '' !== (string) $header_value ) {
					$headers[ $header_name ] = (string) $header_value;
				}
			}
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, $label );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] ) ? (string) $result['error'] : sprintf(
					/* translators: %s: provider name */
					__( '%s request failed.', 'workflow-automate' ),
					$label
				),
			);
		}

		$decoded = is_array( $result['response'] ?? null ) ? $result['response'] : array();
		$choice  = $decoded['choices'][0] ?? null;

		if ( ! is_array( $choice ) || empty( $choice['message'] ) || ! is_array( $choice['message'] ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( '%s returned an empty response.', 'workflow-automate' ),
					$label
				),
			);
		}

		return array(
			'success'       => true,
			'message'       => $choice['message'],
			'finish_reason' => (string) ( $choice['finish_reason'] ?? 'stop' ),
		);
	}

	/**
	 * @param string            $api_key       API key.
	 * @param string            $model         Model id.
	 * @param array<int, mixed> $messages      Messages.
	 * @param array<int, mixed> $tools         Tool schemas.
	 * @param string            $system_prompt System prompt.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	private function completeClaude(
		string $api_key,
		string $model,
		array $messages,
		array $tools,
		string $system_prompt
	): array {
		$claude_messages = $this->messagesToClaude( $messages );
		$claude_tools    = $this->toolsToClaude( $tools );

		$payload = array(
			'model'      => $model,
			'max_tokens' => 4096,
			'messages'   => $claude_messages,
		);

		if ( '' !== $system_prompt ) {
			$payload['system'] = $system_prompt;
		}

		if ( array() !== $claude_tools ) {
			$payload['tools'] = $claude_tools;
		}

		$response = wp_safe_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => self::CLAUDE_API_VERSION,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Claude' );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] ) ? (string) $result['error'] : __( 'Claude request failed.', 'workflow-automate' ),
			);
		}

		return $this->claudeResponseToNormalized( is_array( $result['response'] ?? null ) ? $result['response'] : array() );
	}

	/**
	 * @param string            $api_key       API key.
	 * @param string            $model         Model id.
	 * @param array<int, mixed> $messages      Messages.
	 * @param array<int, mixed> $tools         Tool schemas.
	 * @param string            $system_prompt System prompt.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	private function completeGemini(
		string $api_key,
		string $model,
		array $messages,
		array $tools,
		string $system_prompt
	): array {
		$payload = array(
			'contents' => $this->messagesToGemini( $messages ),
		);

		if ( '' !== $system_prompt ) {
			$payload['systemInstruction'] = array(
				'parts' => array(
					array( 'text' => $system_prompt ),
				),
			);
		}

		$declarations = $this->toolsToGemini( $tools );

		if ( array() !== $declarations ) {
			$payload['tools'] = array(
				array( 'functionDeclarations' => $declarations ),
			);
		}

		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Gemini' );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] ) ? (string) $result['error'] : __( 'Gemini request failed.', 'workflow-automate' ),
			);
		}

		return $this->geminiResponseToNormalized( is_array( $result['response'] ?? null ) ? $result['response'] : array() );
	}

	/**
	 * @param array<int, mixed> $messages Messages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function messagesToClaude( array $messages ): array {
		$claude_messages = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = (string) ( $message['role'] ?? '' );

			if ( 'system' === $role ) {
				continue;
			}

			if ( 'tool' === $role ) {
				$claude_messages[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => (string) ( $message['tool_call_id'] ?? '' ),
							'content'     => (string) ( $message['content'] ?? '' ),
						),
					),
				);
				continue;
			}

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
				$content = array();

				if ( ! empty( $message['content'] ) ) {
					$content[] = array(
						'type' => 'text',
						'text' => (string) $message['content'],
					);
				}

				foreach ( $message['tool_calls'] as $tool_call ) {
					if ( ! is_array( $tool_call ) ) {
						continue;
					}

					$args = $tool_call['function']['arguments'] ?? array();

					if ( is_string( $args ) ) {
						$decoded = json_decode( $args, true );
						$args    = is_array( $decoded ) ? $decoded : array();
					}

					$content[] = array(
						'type'  => 'tool_use',
						'id'    => (string) ( $tool_call['id'] ?? '' ),
						'name'  => (string) ( $tool_call['function']['name'] ?? '' ),
						'input' => is_array( $args ) ? $args : array(),
					);
				}

				$claude_messages[] = array(
					'role'    => 'assistant',
					'content' => $content,
				);
				continue;
			}

			$claude_messages[] = array(
				'role'    => 'user' === $role ? 'user' : 'assistant',
				'content' => (string) ( $message['content'] ?? '' ),
			);
		}

		return $claude_messages;
	}

	/**
	 * @param array<int, mixed> $tools OpenAI-style tools.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function toolsToClaude( array $tools ): array {
		$claude_tools = array();

		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) || empty( $tool['function'] ) || ! is_array( $tool['function'] ) ) {
				continue;
			}

			$function   = $tool['function'];
			$parameters = $function['parameters'] ?? array( 'type' => 'object', 'properties' => (object) array() );

			if ( $parameters instanceof \stdClass ) {
				$parameters = (array) $parameters;
			}

			$claude_tools[] = array(
				'name'         => (string) ( $function['name'] ?? '' ),
				'description'  => (string) ( $function['description'] ?? '' ),
				'input_schema' => $parameters,
			);
		}

		return $claude_tools;
	}

	/**
	 * @param array<string, mixed> $decoded Claude API response.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	private function claudeResponseToNormalized( array $decoded ): array {
		$content_blocks = $decoded['content'] ?? array();

		if ( ! is_array( $content_blocks ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Claude returned an empty response.', 'workflow-automate' ),
			);
		}

		$text       = '';
		$tool_calls = array();

		foreach ( $content_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) ( $block['text'] ?? '' );
			}

			if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
				$tool_calls[] = array(
					'id'       => (string) ( $block['id'] ?? wp_generate_uuid4() ),
					'type'     => 'function',
					'function' => array(
						'name'      => (string) ( $block['name'] ?? '' ),
						'arguments' => wp_json_encode( is_array( $block['input'] ?? null ) ? $block['input'] : array() ),
					),
				);
			}
		}

		$message = array(
			'role'    => 'assistant',
			'content' => $text,
		);

		if ( array() !== $tool_calls ) {
			$message['tool_calls'] = $tool_calls;
		}

		return array(
			'success'       => true,
			'message'       => $message,
			'finish_reason' => array() !== $tool_calls ? 'stop' : 'tool_calls',
		);
	}

	/**
	 * @param array<int, mixed> $messages Messages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function messagesToGemini( array $messages ): array {
		$contents = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = (string) ( $message['role'] ?? '' );

			if ( 'system' === $role ) {
				continue;
			}

			if ( 'tool' === $role ) {
				$decoded = json_decode( (string) ( $message['content'] ?? '{}' ), true );
				$name    = is_array( $decoded ) && isset( $decoded['name'] ) ? (string) $decoded['name'] : 'tool';
				$response_data = is_array( $decoded ) ? $decoded : array( 'result' => $decoded );

				$contents[] = array(
					'role'  => 'user',
					'parts' => array(
						array(
							'functionResponse' => array(
								'name'     => $name,
								'response' => $this->toGeminiStruct( $response_data ),
							),
						),
					),
				);
				continue;
			}

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) && is_array( $message['tool_calls'] ) ) {
				$parts = array();

				if ( ! empty( $message['content'] ) ) {
					$parts[] = array( 'text' => (string) $message['content'] );
				}

				foreach ( $message['tool_calls'] as $tool_call ) {
					if ( ! is_array( $tool_call ) ) {
						continue;
					}

					$args = $tool_call['function']['arguments'] ?? array();

					if ( is_string( $args ) ) {
						$parsed = json_decode( $args, true );
						$args   = is_array( $parsed ) ? $parsed : array();
					}

					$parts[] = array(
						'functionCall' => array(
							'name' => (string) ( $tool_call['function']['name'] ?? '' ),
							'args' => $this->toGeminiStruct( is_array( $args ) ? $args : array() ),
						),
					);
				}

				$contents[] = array(
					'role'  => 'model',
					'parts' => $parts,
				);
				continue;
			}

			$contents[] = array(
				'role'  => 'assistant' === $role ? 'model' : 'user',
				'parts' => array(
					array( 'text' => (string) ( $message['content'] ?? '' ) ),
				),
			);
		}

		return $contents;
	}

	/**
	 * @param array<int, mixed> $tools OpenAI-style tools.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function toolsToGemini( array $tools ): array {
		$declarations = array();

		foreach ( $tools as $tool ) {
			if ( ! is_array( $tool ) || empty( $tool['function'] ) || ! is_array( $tool['function'] ) ) {
				continue;
			}

			$function = $tool['function'];
			$name     = (string) ( $function['name'] ?? '' );

			if ( '' === $name ) {
				continue;
			}

			$parameters = $function['parameters'] ?? array();

			if ( $parameters instanceof \stdClass ) {
				$parameters = (array) $parameters;
			}

			if ( ! is_array( $parameters ) ) {
				$parameters = array();
			}

			$declaration = array(
				'name'        => $name,
				'description' => (string) ( $function['description'] ?? '' ),
			);

			$json_schema = $this->sanitizeGeminiParametersSchema( $parameters );

			$declaration['parametersJsonSchema'] = $json_schema;

			$declarations[] = $declaration;
		}

		return $declarations;
	}

	/**
	 * Normalizes OpenAI-style JSON Schema for Gemini function declarations.
	 *
	 * Gemini 2.5+ expects `parametersJsonSchema` (standard JSON Schema), not the
	 * legacy protobuf-style `parameters` field.
	 *
	 * @param array<string, mixed> $parameters Tool parameter schema.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitizeGeminiParametersSchema( array $parameters ): array {
		$properties = $parameters['properties'] ?? array();

		if ( $properties instanceof \stdClass ) {
			$properties = (array) $properties;
		}

		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$sanitized_properties = array();

		foreach ( $properties as $key => $property ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $property ) ) {
				continue;
			}

			$type = strtolower( (string) ( $property['type'] ?? 'string' ) );

			if ( ! in_array( $type, array( 'string', 'integer', 'number', 'boolean', 'array', 'object' ), true ) ) {
				$type = 'string';
			}

			$entry = array(
				'type' => $type,
			);

			if ( ! empty( $property['description'] ) && is_string( $property['description'] ) ) {
				$entry['description'] = $property['description'];
			}

			$sanitized_properties[ $key ] = $entry;
		}

		$schema = array(
			'type'       => 'object',
			'properties' => (object) $sanitized_properties,
		);

		$required = $parameters['required'] ?? array();

		if ( is_array( $required ) && array() !== $required ) {
			$filtered_required = array_values(
				array_filter(
					$required,
					static function ( $field ) use ( $sanitized_properties ): bool {
						return is_string( $field ) && array_key_exists( $field, $sanitized_properties );
					}
				)
			);

			if ( array() !== $filtered_required ) {
				$schema['required'] = $filtered_required;
			}
		}

		return $schema;
	}

	/**
	 * @param array<string, mixed> $decoded Gemini API response.
	 *
	 * @return array{success: bool, error?: string, message?: array<string, mixed>, finish_reason?: string}
	 */
	private function geminiResponseToNormalized( array $decoded ): array {
		$parts = $decoded['candidates'][0]['content']['parts'] ?? array();

		if ( ! is_array( $parts ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Gemini returned an empty response.', 'workflow-automate' ),
			);
		}

		$text       = '';
		$tool_calls = array();

		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}

			if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$text .= $part['text'];
			}

			if ( isset( $part['functionCall'] ) && is_array( $part['functionCall'] ) ) {
				$call = $part['functionCall'];
				$tool_calls[] = array(
					'id'       => wp_generate_uuid4(),
					'type'     => 'function',
					'function' => array(
						'name'      => (string) ( $call['name'] ?? '' ),
						'arguments' => wp_json_encode( is_array( $call['args'] ?? null ) ? $call['args'] : array() ),
					),
				);
			}
		}

		$message = array(
			'role'    => 'assistant',
			'content' => trim( $text ),
		);

		if ( array() !== $tool_calls ) {
			$message['tool_calls'] = $tool_calls;
		}

		return array(
			'success'       => true,
			'message'       => $message,
			'finish_reason' => array() !== $tool_calls ? 'stop' : 'tool_calls',
		);
	}

	/**
	 * Converts PHP arrays to stdClass so wp_json_encode emits JSON objects for Gemini Struct fields.
	 *
	 * Empty arrays become `{}` (not `[]`), which the Gemini API requires for functionCall.args
	 * and functionResponse.response.
	 *
	 * @param array<string|int, mixed> $data Struct data.
	 *
	 * @return \stdClass
	 */
	private function toGeminiStruct( array $data ): \stdClass {
		if ( array() === $data ) {
			return new \stdClass();
		}

		$object = new \stdClass();

		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}

			$object->{$key} = $this->toGeminiValue( $value );
		}

		return $object;
	}

	/**
	 * @param mixed $value Value to encode for Gemini.
	 *
	 * @return mixed
	 */
	private function toGeminiValue( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array() === $value ) {
			return new \stdClass();
		}

		if ( array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
			return array_map( array( $this, 'toGeminiValue' ), $value );
		}

		return $this->toGeminiStruct( $value );
	}
}
