<?php
/**
 * Resolves {{nodes.id.path}} and literal values from execution context.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dot-path resolver for workflow tokens.
 */
class ContextPathResolver {

	/**
	 * @param array<string, mixed> $context Execution context.
	 * @param string               $value   Token or literal.
	 *
	 * @return mixed
	 */
	public static function resolveValue( array $context, string $value ) {
		$trimmed = trim( $value );

		if ( preg_match( '/^\{\{(.+)\}\}$/', $trimmed, $matches ) ) {
			return self::resolvePath( $context, trim( $matches[1] ) );
		}

		return $trimmed;
	}

	/**
	 * @param array<string, mixed> $context Context.
	 * @param string               $path    Dot path.
	 *
	 * @return mixed
	 */
	public static function resolvePath( array $context, string $path ) {
		$segments = explode( '.', $path );
		$current  = $context;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return '';
			}

			$current = $current[ $segment ];
		}

		return $current;
	}
}
