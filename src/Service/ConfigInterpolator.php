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
	 * Recursively interpolates every string value in a config array.
	 *
	 * @param array<string, mixed> $config  Node configuration.
	 * @param array<string, mixed> $context Runtime context (trigger, nodes, …).
	 *
	 * @return array<string, mixed>
	 */
	public function interpolateConfig( array $config, array $context ): array {
		foreach ( $config as $key => $value ) {
			if ( is_string( $value ) ) {
				$config[ $key ] = $this->interpolateString( $value, $context );
			} elseif ( is_array( $value ) ) {
				$config[ $key ] = $this->interpolateConfig( $value, $context );
			}
		}

		return $config;
	}

	/**
	 * @param string               $template Config string that may contain `{{tokens}}`.
	 * @param array<string, mixed> $context  Runtime context.
	 *
	 * @return string
	 */
	public function interpolateString( string $template, array $context ): string {
		if ( false === strpos( $template, '{{' ) ) {
			return $template;
		}

		return (string) preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
			function ( array $matches ) use ( $context ): string {
				$value = $this->resolvePath( $context, $matches[1] );

				if ( null === $value ) {
					return '';
				}

				if ( is_bool( $value ) ) {
					return $value ? '1' : '0';
				}

				if ( is_scalar( $value ) ) {
					return (string) $value;
				}

				if ( is_array( $value ) ) {
					$encoded = wp_json_encode( $value );

					return is_string( $encoded ) ? $encoded : '';
				}

				return '';
			},
			$template
		);
	}

	/**
	 * @param array<string, mixed> $context Context root.
	 * @param string               $path    Dot-separated path (e.g. trigger.fields.email).
	 *
	 * @return mixed|null
	 */
	private function resolvePath( array $context, string $path ) {
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
