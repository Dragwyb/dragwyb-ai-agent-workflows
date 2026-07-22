<?php
/**
 * Generic action node type driven by a `WordPressActionCatalog` entry.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\WordPress;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionGroupInterface;
use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;

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
		$this->services = $services;
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
		return 'wordpress';
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
		unset( $context );

		$method = $this->definition['method'];
		$args = $this->definition['method_args'] ?? array();

		return $this->services->$method( $config, ...$args );
	}
}
