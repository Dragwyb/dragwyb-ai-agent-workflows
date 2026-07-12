<?php
/**
 * Replaces {{path.to.value}} tokens in action config strings.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets action config fields reference runtime context (trigger payload and
 * prior node outputs) with simple mustache-style tokens, e.g.
 * `{{trigger.fields.email}}` or `{{trigger.form_name}}`.
 *
 * Applied centrally in NodeExecutionService so every action gets the same
 * substitution rules without each integration reimplementing them.
 */
class ConfigInterpolator {

	/**
	 * Common LLM placeholder names mapped to trigger payload keys.
	 *
	 * @var array<string, string>
	 */
	private const TRIGGER_FIELD_ALIASES = array(
		'customer_name' => 'billing_first_name',
		'first_name' => 'billing_first_name',
		'name' => 'billing_first_name',
		'last_name' => 'billing_last_name',
		'email' => 'billing_email',
		'order_number' => 'order_id',
		'amount' => 'total',
		'order_total' => 'total',
	);

	/**
	 * Recursively interpolates every string value in a config array.
	 *
	 * @param array<string, mixed> $config  Node configuration.
	 * @param array<string, mixed> $context Runtime context (trigger, nodes, …).
	 *
	 * @return array<string, mixed>
	 */
	public function interpolateConfig( array $config, array $context ): array {
		$json_escape_body = $this->shouldJsonEscapeHttpBody( $config );

		foreach ( $config as $key => $value ) {
			if ( is_string( $value ) ) {
				$force_json = ( 'body' === $key && $json_escape_body )
					|| $this->looksLikeJsonTemplate( $value );

				$config[ $key ] = $this->interpolateString( $value, $context, $force_json );
			} elseif ( is_array( $value ) ) {
				// Key/value HTTP body rows still need JSON-safe string values.
				if ( 'body_parameters' === $key && $json_escape_body ) {
					$config[ $key ] = $this->interpolateBodyParameters( $value, $context );
				} else {
					$config[ $key ] = $this->interpolateConfig( $value, $context );
				}
			}
		}

		return $config;
	}

	/**
	 * @param array<string, mixed> $config Node configuration.
	 *
	 * @return bool
	 */
	private function shouldJsonEscapeHttpBody( array $config ): bool {
		$content_type = isset( $config['body_content_type'] )
			? (string) $config['body_content_type']
			: 'json';

		if ( 'json' !== $content_type ) {
			return false;
		}

		if ( array_key_exists( 'send_body', $config ) && ! $config['send_body'] ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $template Config string.
	 *
	 * @return bool
	 */
	private function looksLikeJsonTemplate( string $template ): bool {
		$trimmed = ltrim( $template );

		return str_starts_with( $trimmed, '{' ) || str_starts_with( $trimmed, '[' );
	}

	/**
	 * @param array<int, mixed>    $rows    Body parameter rows.
	 * @param array<string, mixed> $context Runtime context.
	 *
	 * @return array<int, mixed>
	 */
	private function interpolateBodyParameters( array $rows, array $context ): array {
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( isset( $row['name'] ) && is_string( $row['name'] ) ) {
				$rows[ $index ]['name'] = $this->interpolateString( $row['name'], $context, false );
			}

			if ( isset( $row['value'] ) && is_string( $row['value'] ) ) {
				// Values are later passed through wp_json_encode — keep raw text here.
				$rows[ $index ]['value'] = $this->interpolateString( $row['value'], $context, false );
			}
		}

		return $rows;
	}

	/**
	 * @param string               $template   Config string that may contain `{{tokens}}`.
	 * @param array<string, mixed> $context    Runtime context.
	 * @param bool                 $force_json Escape scalars for JSON string contexts.
	 *
	 * @return string
	 */
	public function interpolateString( string $template, array $context, bool $force_json = false ): string {
		if ( false === strpos( $template, '{{' ) ) {
			return $template;
		}

		// Auto-detect JSON templates even when the caller did not opt in.
		if ( ! $force_json && $this->looksLikeJsonTemplate( $template ) ) {
			$force_json = true;
		}

		$pattern = '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/';

		if ( ! preg_match_all( $pattern, $template, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $template;
		}

		$result = '';
		$cursor = 0;

		foreach ( $matches[0] as $index => $match ) {
			$token = $match[0];
			$pos   = (int) $match[1];
			$path  = (string) $matches[1][ $index ][0];

			$result .= substr( $template, $cursor, $pos - $cursor );

			$value = $this->resolvePath( $context, $path );

			if ( null === $value ) {
				$result .= $this->formatInterpolatedValue( '', $template, $pos, $force_json );
			} elseif ( is_bool( $value ) ) {
				$inside_json = $force_json && $this->isInsideJsonString( $template, $pos );
				$result     .= $inside_json
					? $this->formatInterpolatedValue( $value ? 'true' : 'false', $template, $pos, true )
					: ( $value ? '1' : '0' );
			} elseif ( is_scalar( $value ) ) {
				$resolved = (string) $value;

				if ( str_contains( $resolved, '<!-- wp:' ) ) {
					$resolved = TriggerPayloadNormalizer::plainTextFromPostContent( $resolved );
				}

				$result .= $this->formatInterpolatedValue( $resolved, $template, $pos, $force_json );
			} elseif ( is_array( $value ) ) {
				$encoded = wp_json_encode( $value );
				$result .= is_string( $encoded ) ? $encoded : '';
			}

			$cursor = $pos + strlen( $token );
		}

		$result .= substr( $template, $cursor );

		return $result;
	}

	/**
	 * Formats a resolved token for insertion. When the token sits inside a
	 * JSON string, control characters and quotes are escaped so the resulting
	 * body remains valid JSON — matching how n8n injects expression results.
	 *
	 * @param string $value      Resolved scalar text.
	 * @param string $template   Full template string.
	 * @param int    $offset     Byte offset of the token in $template.
	 * @param bool   $force_json Whether this template should use JSON escaping.
	 *
	 * @return string
	 */
	private function formatInterpolatedValue(
		string $value,
		string $template,
		int $offset,
		bool $force_json
	): string {
		if ( $force_json && $this->isInsideJsonString( $template, $offset ) ) {
			$encoded = wp_json_encode( $value );

			if ( ! is_string( $encoded ) || strlen( $encoded ) < 2 ) {
				return '';
			}

			// Strip surrounding quotes — the template already provides them.
			return substr( $encoded, 1, -1 );
		}

		return $value;
	}

	/**
	 * Returns true when $offset is inside a JSON double-quoted string,
	 * including when whitespace surrounds the token (TokenField pills).
	 *
	 * @param string $template Full template.
	 * @param int    $offset   Token start offset.
	 *
	 * @return bool
	 */
	private function isInsideJsonString( string $template, int $offset ): bool {
		$in_string = false;
		$length    = strlen( $template );

		for ( $i = 0; $i < $offset && $i < $length; $i++ ) {
			$char = $template[ $i ];

			if ( '\\' === $char && $in_string ) {
				++$i;
				continue;
			}

			if ( '"' === $char ) {
				$in_string = ! $in_string;
			}
		}

		return $in_string;
	}

	/**
	 * @param array<string, mixed> $context Context root.
	 * @param string               $path    Dot-separated path (e.g. trigger.fields.email).
	 *
	 * @return mixed|null
	 */
	private function resolvePath( array $context, string $path ) {
		$value = $this->resolvePathSegments( $context, $path );

		if ( null !== $value ) {
			return $value;
		}

		if ( str_starts_with( $path, 'trigger.' ) ) {
			return null;
		}

		$trigger_paths = array(
			'trigger.' . $path,
			'trigger.fields.' . $path,
		);

		foreach ( $trigger_paths as $trigger_path ) {
			$value = $this->resolvePathSegments( $context, $trigger_path );

			if ( null !== $value ) {
				return $value;
			}
		}

		if ( isset( self::TRIGGER_FIELD_ALIASES[ $path ] ) ) {
			return $this->resolvePathSegments( $context, 'trigger.' . self::TRIGGER_FIELD_ALIASES[ $path ] );
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $context Context root.
	 * @param string               $path    Dot-separated path.
	 *
	 * @return mixed|null
	 */
	private function resolvePathSegments( array $context, string $path ) {
		$segments = explode( '.', $path );
		$current  = $context;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return null;
			}

			$current = $current[ $segment ];
		}

		return $current;
	}
}
