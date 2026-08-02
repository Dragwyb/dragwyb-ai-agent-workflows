<?php
/**
 * Normalizes raw WordPress hook arguments into JSON-friendly trigger payloads.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns catalog hook argument lists into named keys (post_title, post_content,
 * etc.) so the builder variable picker and {{trigger.*}} tokens work without
 * knowing WordPress hook signatures.
 */
class TriggerPayloadNormalizer {

	/**
	 * @param string            $hook_name WordPress action hook name.
	 * @param array<int, mixed> $args      Variadic hook arguments.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalize( string $hook_name, array $args ): array {
		if ( self::looksStructured( $args ) ) {
			return self::ensurePostContent( $args );
		}

		if ( self::isPostHook( $hook_name ) ) {
			return self::normalizePostHook( $hook_name, $args );
		}

		return self::wrapRaw( $hook_name, $args );
	}

	/**
	 * @param array<string, mixed> $payload Payload that may already be normalized.
	 *
	 * @return bool
	 */
	private static function looksStructured( array $payload ): bool {
		return isset( $payload['source'] ) && isset( $payload['event'] );
	}

	/**
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalizePostHook( string $hook_name, array $args ): array {
		$post_id = 0;
		$post    = null;
		$extra   = array();

		switch ( $hook_name ) {
			case 'transition_post_status':
				$post = $args[2] ?? null;
				if ( $post instanceof \WP_Post ) {
					$post_id = (int) $post->ID;
				}
				$extra['new_status'] = isset( $args[0] ) ? (string) $args[0] : '';
				$extra['old_status'] = isset( $args[1] ) ? (string) $args[1] : '';
				break;

			case 'post_updated':
			case 'wp_after_insert_post':
				$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
				$post    = $args[1] ?? null;
				if ( isset( $args[2] ) ) {
					$extra['is_update'] = (bool) $args[2];
				}
				break;

			case 'save_post':
			case 'wp_insert_post':
				$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
				$post    = $args[1] ?? null;
				if ( isset( $args[2] ) ) {
					$extra['is_update'] = (bool) $args[2];
				}
				break;

			case 'add_meta_boxes':
				$extra['post_type'] = isset( $args[0] ) ? (string) $args[0] : '';
				$post               = $args[1] ?? null;
				if ( $post instanceof \WP_Post ) {
					$post_id = (int) $post->ID;
				}
				break;

			case 'before_delete_post':
				$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
				$post    = $args[1] ?? null;
				break;

			case 'wp_save_post_revision':
				$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
				break;

			default:
				$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
				break;
		}

		if ( ! $post instanceof \WP_Post && $post_id > 0 ) {
			$post = get_post( $post_id );
		}

		$payload = array_merge(
			array(
				'source'  => 'wordpress',
				'event'   => $hook_name,
				'post_id' => $post_id,
			),
			$extra
		);

		if ( $post instanceof \WP_Post ) {
			$payload = array_merge( $payload, self::postFields( $post ) );
		} elseif ( $post_id > 0 ) {
			$content                      = self::loadPostContent( $post_id );
			$payload['post_content']      = $content;
			$payload['post_content_text'] = self::humanReadablePostContent( $content );
		}

		return $payload;
	}

	/**
	 * @param \WP_Post $post Post object from a hook.
	 *
	 * @return array<string, mixed>
	 */
	private static function postFields( \WP_Post $post ): array {
		$post_id = (int) $post->ID;
		$content = self::loadPostContent( $post_id );

		return array(
			'post_title'        => (string) $post->post_title,
			'post_content'      => $content,
			'post_content_text' => self::humanReadablePostContent( $content ),
			'post_excerpt'      => (string) $post->post_excerpt,
			'post_status'       => (string) $post->post_status,
			'post_type'         => (string) $post->post_type,
			'post_name'         => (string) $post->post_name,
			'post_author'       => (int) $post->post_author,
			'post_date'         => (string) $post->post_date,
			'post_modified'     => (string) $post->post_modified,
			'post_parent'       => (int) $post->post_parent,
			'guid'              => (string) $post->guid,
		);
	}

	/**
	 * Loads post content from the database so save_post captures the latest body.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	private static function loadPostContent( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$content = get_post_field( 'post_content', $post_id, 'raw' );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Backfills post_content when an older capture omitted it.
	 *
	 * @param array<string, mixed> $payload Structured payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function ensurePostContent( array $payload ): array {
		if ( isset( $payload['post_content'] ) && '' !== (string) $payload['post_content'] ) {
			if ( ! isset( $payload['post_content_text'] ) || '' === (string) $payload['post_content_text'] ) {
				$payload['post_content_text'] = self::humanReadablePostContent( (string) $payload['post_content'] );
			}

			return $payload;
		}

		$post_id = isset( $payload['post_id'] ) ? (int) $payload['post_id'] : 0;

		if ( $post_id <= 0 && isset( $payload['ID'] ) ) {
			$post_id = (int) $payload['ID'];
		}

		if ( $post_id > 0 ) {
			$content                      = self::loadPostContent( $post_id );
			$payload['post_content']      = $content;
			$payload['post_content_text'] = self::humanReadablePostContent( $content );
		}

		return $payload;
	}

	/**
	 * Strips Gutenberg block comments and HTML tags for human-readable text.
	 *
	 * @param string $content Raw post content.
	 *
	 * @return string
	 */
	public static function plainTextFromPostContent( string $content ): string {
		if ( '' === $content ) {
			return '';
		}

		$plain = (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/', '', $content );
		$plain = wp_strip_all_tags( $plain );
		$plain = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $plain ) );
	}

	/**
	 * @param string $content Raw post content.
	 *
	 * @return string
	 */
	private static function humanReadablePostContent( string $content ): string {
		return self::plainTextFromPostContent( $content );
	}

	/**
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return array<string, mixed>
	 */
	private static function wrapRaw( string $hook_name, array $args ): array {
		return array(
			'source' => 'wordpress',
			'event'  => $hook_name,
			'args'   => self::jsonSafe( $args ),
		);
	}

	/**
	 * @param mixed $value Arbitrary hook argument value.
	 *
	 * @return mixed
	 */
	private static function jsonSafe( $value ) {
		if ( $value instanceof \WP_Post ) {
			return self::postFields( $value );
		}

		if ( $value instanceof \WP_User ) {
			return array(
				'ID'           => (int) $value->ID,
				'user_login'   => (string) $value->user_login,
				'user_email'   => (string) $value->user_email,
				'display_name' => (string) $value->display_name,
			);
		}

		if ( is_array( $value ) ) {
			$safe = array();

			foreach ( $value as $key => $item ) {
				$safe[ $key ] = self::jsonSafe( $item );
			}

			return $safe;
		}

		if ( is_object( $value ) ) {
			return self::jsonSafe( (array) $value );
		}

		return $value;
	}

	/**
	 * @param string $hook_name Hook name.
	 *
	 * @return bool
	 */
	private static function isPostHook( string $hook_name ): bool {
		static $hooks = array(
			'save_post'              => true,
			'wp_insert_post'         => true,
			'post_updated'           => true,
			'wp_after_insert_post'   => true,
			'transition_post_status' => true,
			'before_delete_post'     => true,
			'add_meta_boxes'         => true,
			'wp_trash_post'          => true,
			'untrash_post'           => true,
			'deleted_post'           => true,
			'delete_post'            => true,
			'wp_save_post_revision'  => true,
		);

		return isset( $hooks[ $hook_name ] );
	}
}
