<?php
/**
 * Catalog-defined WordPress hook trigger.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Triggers;

use WorkflowAutomate\Plugin\Domain\Contracts\TriggerGroupInterface;
use WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface;
use WorkflowAutomate\Plugin\Integration\WordPress\WordPressActionHelper;
use WorkflowAutomate\Plugin\Service\TriggerPayloadNormalizer;

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
		return __( 'Starts the workflow when this WordPress event fires.', 'workflow-automate' );
	}

	public function group(): string {
		return (string) $this->definition['group'];
	}

	public function groupLabel(): string {
		return (string) $this->definition['group_label'];
	}

	public function app(): string {
		return 'wordpress';
	}

	public function configSchema(): array {
		return array(
			'hook_name' => array(
				'type' => 'string',
				'default' => (string) $this->definition['hook_name'],
				'hidden' => true,
			),
			'priority' => array(
				'type' => 'integer',
				'default' => (int) ( $this->definition['priority'] ?? 10 ),
				'hidden' => true,
			),
			'accepted_args' => array(
				'type' => 'integer',
				'default' => (int) ( $this->definition['accepted_args'] ?? 1 ),
				'hidden' => true,
			),
		);
	}

	public function bind( array $config, callable $on_fire ): void {
		$hook_name = trim( (string) ( $config['hook_name'] ?? $this->definition['hook_name'] ) );

		if ( '' === $hook_name ) {
			return;
		}

		$priority      = (int) ( $config['priority'] ?? $this->definition['priority'] ?? 10 );
		$accepted_args = max( 0, (int) ( $config['accepted_args'] ?? $this->definition['accepted_args'] ?? 1 ) );

		add_action(
			$hook_name,
			static function ( ...$args ) use ( $on_fire, $config, $hook_name ) {
				if ( self::shouldIgnorePostEvent( $hook_name, $args ) ) {
					return;
				}

				$payload = TriggerPayloadNormalizer::normalize( $hook_name, $args );
				$on_fire( $payload, $config );
			},
			$priority,
			$accepted_args
		);
	}

	/**
	 * Skips autosaves, revisions, trash, auto-drafts, and posts this plugin
	 * already created — otherwise one editor "Update" (or trashing the
	 * translated post the agent just made) starts another run.
	 *
	 * @param string            $hook_name Hook name.
	 * @param array<int, mixed> $args      Hook arguments.
	 *
	 * @return bool
	 */
	private static function shouldIgnorePostEvent( string $hook_name, array $args ): bool {
		static $post_hooks = array(
			'save_post'            => true,
			'wp_insert_post'       => true,
			'wp_after_insert_post' => true,
			'post_updated'         => true,
		);

		if ( ! isset( $post_hooks[ $hook_name ] ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( $post_id <= 0 ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return true;
		}

		if ( WordPressActionHelper::isAutomatedPost( $post_id ) ) {
			return true;
		}

		$post = $args[1] ?? null;

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( 'revision' === $post->post_type ) {
			return true;
		}

		if ( in_array( (string) $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
			return true;
		}

		return false;
	}
}
