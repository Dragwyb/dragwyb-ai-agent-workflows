<?php
/**
 * Catalog-defined WordPress hook trigger.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Triggers;

use AIAWA\Plugin\Domain\Contracts\TriggerGroupInterface;
use AIAWA\Plugin\Domain\Contracts\TriggerInterface;
use AIAWA\Plugin\Integration\WordPress\WordPressActionHelper;
use AIAWA\Plugin\Service\TriggerPayloadNormalizer;
use AIAWA\Plugin\Service\TriggerReentrancyGuard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CatalogHookTrigger implements TriggerInterface, TriggerGroupInterface {

	/** @var array<string, mixed> */
	private array $definition;

	/** @param array<string, mixed> $definition */
	public function __construct( array $definition ) {
		$this->definition = $definition;
	}

	public function slug(): string {
		return (string) $this->definition['slug'];
	}

	public function label(): string {
		return (string) $this->definition['label'];
	}

	public function description(): string {
		$hook = (string) ( $this->definition['hook_name'] ?? '' );

		if ( self::isPostContentHook( $hook ) ) {
			return __( 'Starts the workflow when this WordPress post event fires for posts, pages, or custom post types (filterable via Post Types).', 'dragwyb-agentflow' );
		}

		return __( 'Starts the workflow when this WordPress event fires.', 'dragwyb-agentflow' );
	}

	public function group(): string {
		return (string) $this->definition['group'];
	}

	public function groupLabel(): string {
		return (string) $this->definition['group_label'];
	}

	public function app(): string {
		return 'WordPress';
	}

	public function configSchema(): array {
		$schema = array(
			'hook_name'     => array(
				'type'    => 'string',
				'default' => (string) $this->definition['hook_name'],
				'hidden'  => true,
			),
			'priority'      => array(
				'type'    => 'integer',
				'default' => (int) ( $this->definition['priority'] ?? 10 ),
				'hidden'  => true,
			),
			'accepted_args' => array(
				'type'    => 'integer',
				'default' => (int) ( $this->definition['accepted_args'] ?? 1 ),
				'hidden'  => true,
			),
		);

		if ( self::isPostContentHook( (string) $this->definition['hook_name'] ) ) {
			$schema['post_types'] = array(
				'type'        => 'string',
				'label'       => __( 'Post Types', 'dragwyb-agentflow' ),
				'default'     => '',
				'description' => __( 'Leave empty to run for posts, pages, and custom post types. Or comma-separated slugs, e.g. post,page.', 'dragwyb-agentflow' ),
				'help'        => __( 'Empty = all content types (including pages). Example: page — only pages. Internal types (attachments, revisions, templates) are always skipped.', 'dragwyb-agentflow' ),
			);
		}

		return $schema;
	}

	public function bind( array $config, callable $on_fire ): void {
		$hook_name = trim( (string) ( $config['hook_name'] ?? $this->definition['hook_name'] ) );

		if ( '' === $hook_name ) {
			return;
		}

		$priority      = (int) ( $config['priority'] ?? $this->definition['priority'] ?? 10 );
		$accepted_args = max( 0, (int) ( $config['accepted_args'] ?? $this->definition['accepted_args'] ?? 1 ) );

		$hooks = array( $hook_name );

		// Also listen on save_post_{type} so page (and filtered CPT) editor
		// saves are never missed if something interferes with generic save_post.
		if ( 'save_post' === $hook_name ) {
			foreach ( self::extraSavePostTypeHooks( $config ) as $type_hook ) {
				$hooks[] = $type_hook;
			}
		}

		$hooks = array_values( array_unique( $hooks ) );

		foreach ( $hooks as $hook ) {
			add_action(
				$hook,
				static function ( ...$args ) use ( $on_fire, $config, $hook_name ) {
					// Normalize against the catalog hook (save_post), not save_post_page.
					if ( self::shouldIgnoreEvent( $hook_name, $args, $config ) ) {
						return;
					}

					$payload = TriggerPayloadNormalizer::normalize( $hook_name, $args );
					$on_fire( $payload, $config );
				},
				$priority,
				$accepted_args
			);
		}
	}

	/**
	 * Type-specific save_post_* hooks to bind alongside generic save_post.
	 *
	 * @param array<string, mixed> $config Trigger config.
	 *
	 * @return array<int, string>
	 */
	private static function extraSavePostTypeHooks( array $config ): array {
		$raw   = $config['post_types'] ?? '';
		$types = is_array( $raw )
			? array_values(
				array_filter(
					array_map(
						static fn( $item ): string => sanitize_key( (string) $item ),
						$raw
					)
				)
			)
			: array_map( 'sanitize_key', WordPressActionHelper::parseList( $raw ) );

		// Empty filter = all content types — at minimum ensure page + post
		// type-specific hooks are bound (Gutenberg page editor path).
		if ( array() === $types ) {
			$types = array( 'post', 'page' );
		}

		$hooks = array();
		foreach ( $types as $type ) {
			if ( '' === $type || self::isIgnoredInternalPostType( $type ) ) {
				continue;
			}
			$hooks[] = 'save_post_' . $type;
		}

		return $hooks;
	}

	/**
	 * @param string               $hook_name Hook name.
	 * @param array<int, mixed>    $args      Hook arguments.
	 * @param array<string, mixed> $config    Trigger node config.
	 */
	private static function shouldIgnoreEvent( string $hook_name, array $args, array $config = array() ): bool {
		$guard = TriggerReentrancyGuard::instance();

		if ( null !== $guard && $guard->isWriting() ) {
			return true;
		}

		if ( self::shouldIgnorePostEvent( $hook_name, $args, $config ) ) {
			return true;
		}

		if ( self::shouldIgnoreUserEvent( $hook_name, $args ) ) {
			return true;
		}

		if ( self::shouldIgnoreCommentEvent( $hook_name, $args ) ) {
			return true;
		}

		if ( self::shouldIgnoreTermEvent( $hook_name, $args ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param string               $hook_name Hook name.
	 * @param array<int, mixed>    $args      Hook arguments.
	 * @param array<string, mixed> $config    Trigger node config.
	 */
	private static function shouldIgnorePostEvent( string $hook_name, array $args, array $config = array() ): bool {
		static $post_hooks = array(
			'save_post'              => true,
			'wp_insert_post'         => true,
			'wp_after_insert_post'   => true,
			'post_updated'           => true,
			'transition_post_status' => true,
			'wp_trash_post'          => true,
			'untrash_post'           => true,
			'before_delete_post'     => true,
			'delete_post'            => true,
			'deleted_post'           => true,
		);

		if ( ! isset( $post_hooks[ $hook_name ] ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		$post_id = 0;

		if ( 'transition_post_status' === $hook_name ) {
			$post = $args[2] ?? null;
			if ( $post instanceof \WP_Post ) {
				$post_id = (int) $post->ID;
			}
		} else {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		}

		if ( $post_id <= 0 ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return true;
		}

		if ( WordPressActionHelper::isAutomatedPost( $post_id ) ) {
			return true;
		}

		static $status_noise_hooks = array(
			'save_post'            => true,
			'wp_insert_post'       => true,
			'wp_after_insert_post' => true,
			'post_updated'         => true,
		);

		$post = $args[1] ?? null;

		if ( 'transition_post_status' === $hook_name ) {
			$post = $args[2] ?? null;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$post_type = (string) $post->post_type;

		if ( self::isIgnoredInternalPostType( $post_type ) ) {
			return true;
		}

		if ( ! self::postTypeAllowed( $post_type, $config ) ) {
			return true;
		}

		if ( ! isset( $status_noise_hooks[ $hook_name ] ) ) {
			return false;
		}

		if ( in_array( (string) $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether this hook can carry a configurable post-type filter.
	 */
	private static function isPostContentHook( string $hook_name ): bool {
		static $hooks = array(
			'save_post'              => true,
			'wp_insert_post'         => true,
			'wp_after_insert_post'   => true,
			'post_updated'           => true,
			'transition_post_status' => true,
			'wp_trash_post'          => true,
			'untrash_post'           => true,
			'before_delete_post'     => true,
			'delete_post'            => true,
			'deleted_post'           => true,
		);

		return isset( $hooks[ $hook_name ] );
	}

	/**
	 * System / internal types that should never start content workflows.
	 * Pages and public CPTs are intentionally not listed.
	 */
	private static function isIgnoredInternalPostType( string $post_type ): bool {
		static $ignored = array(
			'revision'            => true,
			'attachment'          => true,
			'nav_menu_item'       => true,
			'customize_changeset' => true,
			'custom_css'          => true,
			'oembed_cache'        => true,
			'user_request'        => true,
			'wp_block'            => true,
			'wp_template'         => true,
			'wp_template_part'    => true,
			'wp_global_styles'    => true,
			'wp_navigation'       => true,
			'wp_font_family'      => true,
			'wp_font_face'        => true,
		);

		return isset( $ignored[ $post_type ] );
	}

	/**
	 * @param array<string, mixed> $config Trigger config.
	 */
	private static function postTypeAllowed( string $post_type, array $config ): bool {
		$raw = $config['post_types'] ?? '';

		if ( is_array( $raw ) ) {
			$allowed = array_values(
				array_filter(
					array_map(
						static fn( $item ): string => strtolower( trim( (string) $item ) ),
						$raw
					),
					static fn( string $item ): bool => '' !== $item
				)
			);
		} else {
			$allowed = WordPressActionHelper::parseList( $raw );
			$allowed = array_map(
				static fn( string $item ): string => strtolower( $item ),
				$allowed
			);
		}

		// Empty = all content types (posts, pages, public CPTs).
		if ( array() === $allowed ) {
			return true;
		}

		return in_array( strtolower( $post_type ), $allowed, true );
	}

	/**
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return bool
	 */
	private static function shouldIgnoreUserEvent( string $hook_name, array $args ): bool {
		static $user_hooks = array(
			'user_register'  => true,
			'profile_update' => true,
			'delete_user'    => true,
			'deleted_user'   => true,
			'set_user_role'  => true,
			'add_user_role'  => true,
		);

		if ( ! isset( $user_hooks[ $hook_name ] ) ) {
			return false;
		}

		$user_id = isset( $args[0] ) ? (int) $args[0] : 0;

		return $user_id > 0 && WordPressActionHelper::isAutomatedUser( $user_id );
	}

	/**
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return bool
	 */
	private static function shouldIgnoreCommentEvent( string $hook_name, array $args ): bool {
		static $comment_hooks = array(
			'wp_insert_comment'     => true,
			'wp_set_comment_status' => true,
			'edit_comment'          => true,
			'deleted_comment'       => true,
			'trashed_comment'       => true,
		);

		if ( ! isset( $comment_hooks[ $hook_name ] ) ) {
			return false;
		}

		$comment_id = isset( $args[0] ) ? (int) $args[0] : 0;

		return $comment_id > 0 && WordPressActionHelper::isAutomatedComment( $comment_id );
	}

	/**
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return bool
	 */
	private static function shouldIgnoreTermEvent( string $hook_name, array $args ): bool {
		static $term_hooks = array(
			'created_term'         => true,
			'edited_term'          => true,
			'delete_term'          => true,
			'create_term'          => true,
			'edit_term'            => true,
			'edit_terms'           => true,
			'edited_terms'         => true,
			'delete_term_taxonomy' => true,
		);

		if ( ! isset( $term_hooks[ $hook_name ] ) ) {
			return false;
		}

		$term_id = isset( $args[0] ) ? (int) $args[0] : 0;

		return $term_id > 0 && WordPressActionHelper::isAutomatedTerm( $term_id );
	}
}
