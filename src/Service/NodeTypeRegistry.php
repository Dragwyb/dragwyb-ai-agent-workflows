<?php
/**
 * Node type registry.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;
use AIAWA\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds every registered trigger and action node type, keyed by slug.
 *
 * Deliberately a plain PHP collection with no WordPress hook knowledge of
 * its own: something else (Plugin::registerNodeTypes()) is responsible for
 * firing the `aiawa/nodes/register` action that populates it, so this class
 * stays trivially unit-testable.
 */
class NodeTypeRegistry {

	/**
	 * @var array<string, TriggerInterface>
	 */
	private array $triggers = array();

	/**
	 * @var array<string, ActionInterface>
	 */
	private array $actions = array();

	/**
	 * @param TriggerInterface $trigger Trigger to register.
	 *
	 * @return void
	 */
	public function registerTrigger( TriggerInterface $trigger ): void {
		if ( isset( $this->triggers[ $trigger->slug() ] ) ) {
			$this->warnOnDuplicate( $trigger->slug(), 'trigger' );
		}

		$this->triggers[ $trigger->slug() ] = $trigger;
	}

	/**
	 * @param ActionInterface $action Action to register.
	 *
	 * @return void
	 */
	public function registerAction( ActionInterface $action ): void {
		if ( isset( $this->actions[ $action->slug() ] ) ) {
			$this->warnOnDuplicate( $action->slug(), 'action' );
		}

		$this->actions[ $action->slug() ] = $action;
	}

	/**
	 * @param string $slug Trigger slug.
	 *
	 * @return TriggerInterface|null
	 */
	public function trigger( string $slug ): ?TriggerInterface {
		return $this->triggers[ $slug ] ?? null;
	}

	/**
	 * @param string $slug Action slug.
	 *
	 * @return ActionInterface|null
	 */
	public function action( string $slug ): ?ActionInterface {
		return $this->actions[ $slug ] ?? null;
	}

	/**
	 * @return TriggerInterface[]
	 */
	public function triggers(): array {
		return array_values( $this->triggers );
	}

	/**
	 * @return ActionInterface[]
	 */
	public function actions(): array {
		return array_values( $this->actions );
	}

	/**
	 * @param string $slug Colliding slug.
	 * @param string $kind Either 'trigger' or 'action', for the message only.
	 *
	 * @return void
	 */
	private function warnOnDuplicate( string $slug, string $kind ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- _doing_it_wrong is for developer notices only.
		_doing_it_wrong(
			self::class . '::register' . esc_html( ucfirst( $kind ) ),
			sprintf(
				/* translators: 1: node type kind (trigger/action), 2: slug. */
				esc_html__( 'A %1$s with the slug "%2$s" is already registered. The previous registration has been replaced.', 'ai-agent-workflow-automation' ),
				esc_html( $kind ),
				esc_html( $slug )
			),
			esc_html( AIAWA_VERSION )
		);
	}
}
