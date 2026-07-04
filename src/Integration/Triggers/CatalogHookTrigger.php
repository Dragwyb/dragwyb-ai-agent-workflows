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
				$payload = TriggerPayloadNormalizer::normalize( $hook_name, $args );
				$on_fire( $payload, $config );
			},
			$priority,
			$accepted_args
		);
	}
}
