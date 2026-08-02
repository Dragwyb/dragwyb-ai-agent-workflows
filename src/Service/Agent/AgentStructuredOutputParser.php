<?php
/**
 * Builds / validates structured JSON for AI Agent output parsers.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service\Agent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * n8n-style Structured Output Parser helpers.
 */
class AgentStructuredOutputParser {

	public const SCHEMA_FROM_JSON = 'from_json';

	public const SCHEMA_MANUAL = 'manual';

	private const DEFAULT_RETRY_PROMPT = 'Return a corrected JSON object that matches the schema.

Schema / instructions:
{instructions}

Previous attempt:
{completion}

Validation error:
{error}

Respond with JSON only — no markdown fences.';

	/**
	 * @param array<string, mixed> $parser_node Graph attachment node.
	 *
	 * @return array{
	 *     schema: array<string, mixed>,
	 *     auto_fix: bool,
	 *     retry_prompt: string,
	 *     instructions: string
	 * }|array{success: false, error: string}
	 */
	public function resolve( array $parser_node ): array {
		$config = isset( $parser_node['config'] ) && is_array( $parser_node['config'] )
			? $parser_node['config']
			: array();

		$schema_type = isset( $config['schema_type'] ) ? (string) $config['schema_type'] : self::SCHEMA_FROM_JSON;
		$schema      = $this->buildSchema( $config, $schema_type );

		if ( isset( $schema['success'] ) && false === $schema['success'] ) {
			return $schema;
		}

		/** @var array<string, mixed> $schema */
		$auto_fix = ! array_key_exists( 'auto_fix', $config ) || ! empty( $config['auto_fix'] );
		$retry    = self::DEFAULT_RETRY_PROMPT;

		if ( $auto_fix && ! empty( $config['customize_retry_prompt'] ) ) {
			$custom = isset( $config['retry_prompt'] ) ? trim( (string) $config['retry_prompt'] ) : '';

			if ( '' !== $custom ) {
				$retry = $custom;
			}
		}

		return array(
			'schema'       => $schema,
			'auto_fix'     => $auto_fix,
			'retry_prompt' => $retry,
			'instructions' => $this->buildInstructions( $schema ),
		);
	}

	/**
	 * @param array<string, mixed> $config      Parser config.
	 * @param string               $schema_type from_json|manual.
	 *
	 * @return array<string, mixed>|array{success: false, error: string}
	 */
	public function buildSchema( array $config, string $schema_type ) {
		if ( self::SCHEMA_MANUAL === $schema_type ) {
			$raw = isset( $config['json_schema'] ) ? trim( (string) $config['json_schema'] ) : '';

			if ( '' === $raw ) {
				return array(
					'success' => false,
					'error'   => __( 'JSON Schema is empty on the Structured Output Parser.', 'dragwyb-agentflow' ),
				);
			}

			$decoded = json_decode( $raw, true );

			if ( ! is_array( $decoded ) ) {
				return array(
					'success' => false,
					'error'   => __( 'Structured Output Parser JSON Schema is invalid JSON.', 'dragwyb-agentflow' ),
				);
			}

			return $decoded;
		}

		$raw = isset( $config['json_example'] ) ? trim( (string) $config['json_example'] ) : '';

		if ( '' === $raw ) {
			return array(
				'success' => false,
				'error'   => __( 'JSON Example is empty on the Structured Output Parser.', 'dragwyb-agentflow' ),
			);
		}

		$example = json_decode( $raw, true );

		if ( ! is_array( $example ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Structured Output Parser JSON Example is invalid JSON.', 'dragwyb-agentflow' ),
			);
		}

		return $this->schemaFromExample( $example );
	}

	/**
	 * @param mixed $value Example value.
	 *
	 * @return array<string, mixed>
	 */
	public function schemaFromExample( $value ): array {
		if ( is_array( $value ) ) {
			if ( $this->isListArray( $value ) ) {
				$item = $value[0] ?? null;

				return array(
					'type'  => 'array',
					'items' => null === $item
						? array( 'type' => 'string' )
						: $this->schemaFromExample( $item ),
				);
			}

			$properties = array();
			$required   = array();

			foreach ( $value as $key => $child ) {
				$key_string                = (string) $key;
				$properties[ $key_string ] = $this->schemaFromExample( $child );
				$required[]                = $key_string;
			}

			return array(
				'type'                 => 'object',
				'properties'           => $properties,
				'required'             => $required,
				'additionalProperties' => false,
			);
		}

		if ( is_bool( $value ) ) {
			return array( 'type' => 'boolean' );
		}

		if ( is_int( $value ) ) {
			return array( 'type' => 'integer' );
		}

		if ( is_float( $value ) ) {
			return array( 'type' => 'number' );
		}

		if ( null === $value ) {
			return array( 'type' => 'null' );
		}

		return array( 'type' => 'string' );
	}

	/**
	 * @param array<string, mixed> $schema Schema object.
	 *
	 * @return string
	 */
	public function buildInstructions( array $schema ): string {
		$encoded = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $encoded ) ) {
			$encoded = '{}';
		}

		return __( 'You must respond with a single JSON value that validates against this JSON Schema. No markdown fences, no commentary.', 'dragwyb-agentflow' )
			. "\n\n"
			. $encoded;
	}

	/**
	 * @param string               $raw_response Model text.
	 * @param array<string, mixed> $schema       JSON schema.
	 *
	 * @return array{success: true, data: mixed}|array{success: false, error: string, raw?: string}
	 */
	public function parseAndValidate( string $raw_response, array $schema ): array {
		$data = $this->decodeJsonPayload( $raw_response );

		if ( null === $data && 'null' !== trim( $raw_response ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Model reply is not valid JSON.', 'dragwyb-agentflow' ),
				'raw'     => $raw_response,
			);
		}

		$validation = $this->validate( $data, $schema, '$' );

		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'error'   => $validation['error'],
				'raw'     => $raw_response,
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * @param string $prompt_template Retry template.
	 * @param string $instructions    Schema instructions.
	 * @param string $completion      Failed model output.
	 * @param string $error           Validation error.
	 *
	 * @return string
	 */
	public function buildRetryUserMessage(
		string $prompt_template,
		string $instructions,
		string $completion,
		string $error
	): string {
		$template = '' !== trim( $prompt_template ) ? $prompt_template : self::DEFAULT_RETRY_PROMPT;

		return str_replace(
			array( '{instructions}', '{completion}', '{error}' ),
			array( $instructions, $completion, $error ),
			$template
		);
	}

	/**
	 * @param mixed                $data   Decoded JSON.
	 * @param array<string, mixed> $schema Schema fragment.
	 * @param string               $path   JSON path for errors.
	 *
	 * @return array{valid: bool, error: string}
	 */
	public function validate( $data, array $schema, string $path = '$' ): array {
		$type = isset( $schema['type'] ) ? (string) $schema['type'] : '';

		if ( '' === $type && isset( $schema['properties'] ) ) {
			$type = 'object';
		}

		switch ( $type ) {
			case 'object':
				if ( ! is_array( $data ) || $this->isListArray( $data ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected object at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] )
					? $schema['properties']
					: array();
				$required   = isset( $schema['required'] ) && is_array( $schema['required'] )
					? $schema['required']
					: array();

				foreach ( $required as $key ) {
					$key = (string) $key;

					if ( ! array_key_exists( $key, $data ) ) {
						return array(
							'valid' => false,
							'error' => sprintf(
								/* translators: 1: property name, 2: JSON path */
								__( 'Missing required property "%1$s" at %2$s.', 'dragwyb-agentflow' ),
								$key,
								$path
							),
						);
					}
				}

				foreach ( $properties as $key => $child_schema ) {
					$key = (string) $key;

					if ( ! array_key_exists( $key, $data ) ) {
						continue;
					}

					if ( ! is_array( $child_schema ) ) {
						continue;
					}

					$child = $this->validate( $data[ $key ], $child_schema, $path . '.' . $key );

					if ( ! $child['valid'] ) {
						return $child;
					}
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'array':
				if ( ! is_array( $data ) || ! $this->isListArray( $data ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected array at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				$items = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : array( 'type' => 'string' );

				foreach ( $data as $index => $item ) {
					$child = $this->validate( $item, $items, $path . '[' . $index . ']' );

					if ( ! $child['valid'] ) {
						return $child;
					}
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'string':
				if ( ! is_string( $data ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected string at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'integer':
				if ( ! is_int( $data ) && ! ( is_string( $data ) && ctype_digit( $data ) ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected integer at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'number':
				if ( ! is_int( $data ) && ! is_float( $data ) && ! is_numeric( $data ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected number at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'boolean':
				if ( ! is_bool( $data ) ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected boolean at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			case 'null':
				if ( null !== $data ) {
					return array(
						'valid' => false,
						'error' => sprintf(
							/* translators: %s: JSON path */
							__( 'Expected null at %s.', 'dragwyb-agentflow' ),
							$path
						),
					);
				}

				return array(
					'valid' => true,
					'error' => '',
				);

			default:
				return array(
					'valid' => true,
					'error' => '',
				);
		}
	}

	/**
	 * @param string $raw Model text.
	 *
	 * @return mixed|null
	 */
	public function decodeJsonPayload( string $raw ) {
		$trimmed = trim( $raw );

		if ( '' === $trimmed ) {
			return null;
		}

		$decoded = json_decode( $trimmed, true );

		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $decoded;
		}

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $trimmed, $matches ) ) {
			$decoded = json_decode( trim( $matches[1] ), true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $decoded;
			}
		}

		if ( preg_match( '/(\{[\s\S]*\}|\[[\s\S]*\])/', $trimmed, $matches ) ) {
			$decoded = json_decode( $matches[1], true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * @param array<int|string, mixed> $value Array to inspect.
	 *
	 * @return bool
	 */
	private function isListArray( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
