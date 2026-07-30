<?php
/**
 * Generic action node type driven by a `WordPressActionCatalog` entry.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress;

use AIAWAB\Plugin\Domain\Contracts\ActionGroupInterface;
use AIAWAB\Plugin\Domain\Contracts\ActionInterface;
use AIAWAB\Plugin\Service\TriggerReentrancyGuard;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapts a single `WordPressActionCatalog::definitions()` entry into a
 * concrete `ActionInterface`, dispatching `execute()` to the configured
 * `WordPressServices` method (plus any fixed `method_args`, e.g. a
 * taxonomy slug for the product tag/category term actions).
 */
final class WordPressCatalogAction implements ActionInterface, ActionGroupInterface {

	/**
	 * @var array{slug: string, label: string, description: string, group: string, group_label: string, method: string, method_args: array<int, mixed>, config_schema: array<string, mixed>}
	 */
	private array $definition;

	private WordPressServices $services;

	/**
	 * @param array{slug: string, label: string, description: string, group: string, group_label: string, method: string, method_args: array<int, mixed>, config_schema: array<string, mixed>} $definition Catalog entry.
	 */
	public function __construct( array $definition, WordPressServices $services ) {
		$this->definition = $definition;
		$this->services   = $services;
	}

	public function slug(): string {
		return $this->definition['slug'];
	}

	public function label(): string {
		return $this->definition['label'];
	}

	public function description(): string {
		return $this->definition['description'];
	}

	public function configSchema(): array {
		return $this->definition['config_schema'];
	}

	public function app(): string {
		return 'WordPress';
	}

	/**
	 * Node palette group id (e.g. `user`, `post`, `product_tag`).
	 */
	public function group(): string {
		return $this->definition['group'];
	}

	/**
	 * Human-readable node palette group label.
	 */
	public function groupLabel(): string {
		return $this->definition['group_label'];
	}

	public function execute( array $config, array $context ): array {
		$config = $this->applyDynamicPostTypeFromTrigger( $config, $context );

		$method = $this->definition['method'];
		$args   = $this->definition['method_args'] ?? array();

		$guard = TriggerReentrancyGuard::instance();

		if ( null !== $guard ) {
			$guard->beginWrite();
		}

		try {
			return $this->services->$method( $config, ...$args );
		} finally {
			if ( null !== $guard ) {
				$guard->endWrite();
			}
		}
	}

	/**
	 * When Create/Update Post still has the dynamic token (or empty), fill
	 * post_type from the trigger payload. A manual value always wins.
	 *
	 * @param array<string, mixed> $config  Interpolated node config.
	 * @param array<string, mixed> $context Runtime context.
	 *
	 * @return array<string, mixed>
	 */
	private function applyDynamicPostTypeFromTrigger( array $config, array $context ): array {
		$slug = $this->definition['slug'] ?? '';

		if ( ! in_array( $slug, array( 'wp_create_post_action', 'wp_update_post_action' ), true ) ) {
			return $config;
		}

		$configured   = isset( $config['post_type'] ) ? trim( (string) $config['post_type'] ) : '';
		$trigger_type = '';

		if ( isset( $context['trigger'] ) && is_array( $context['trigger'] ) ) {
			$trigger_type = isset( $context['trigger']['post_type'] )
				? trim( (string) $context['trigger']['post_type'] )
				: '';
		}

		$uses_dynamic = (
			'' === $configured
			|| '{{trigger.post_type}}' === $configured
			|| 'trigger.post_type' === $configured
		);

		if ( $uses_dynamic ) {
			$config['post_type'] = '' !== $trigger_type ? $trigger_type : 'post';
		}

		return $config;
	}
}
