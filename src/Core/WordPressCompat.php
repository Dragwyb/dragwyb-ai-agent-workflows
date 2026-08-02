<?php
/**
 * WordPress version helpers and polyfills for declared minimum support.
 *
 * @package DragwybAgentFlow\Plugin
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dragwyb_af_wp_version' ) ) {
	/**
	 * Returns the running WordPress version without wp_get_wp_version().
	 *
	 * @return string
	 */
	function dragwyb_af_wp_version(): string {
		global $wp_version;

		return isset( $wp_version ) ? (string) $wp_version : '0.0.0';
	}
}

if ( ! function_exists( 'dragwyb_af_has_core_ai_client' ) ) {
	/**
	 * Whether WordPress core ships the AI Client API.
	 *
	 * @return bool
	 */
	function dragwyb_af_has_core_ai_client(): bool {
		return function_exists( 'wp_ai_client_prompt' )
			|| version_compare( dragwyb_af_wp_version(), '7.0-alpha', '>=' );
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for WordPress 5.9+.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 *
	 * @return bool
	 */
	function str_contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for WordPress 5.9+.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 *
	 * @return bool
	 */
	function str_starts_with( string $haystack, string $needle ): bool {
		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for WordPress 5.9+.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 *
	 * @return bool
	 */
	function str_ends_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		$needle_length = strlen( $needle );

		return substr( $haystack, -$needle_length ) === $needle;
	}
}

if ( ! function_exists( 'array_is_list' ) ) {
	/**
	 * Polyfill for WordPress 6.5+.
	 *
	 * @param array<mixed> $array Array to inspect.
	 *
	 * @return bool
	 */
	function array_is_list( array $array ): bool {
		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
