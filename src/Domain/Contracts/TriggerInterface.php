<?php
/**
 * Trigger node type contract.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Domain\Contracts;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A trigger is what starts a workflow run. Unlike an action, it does not
 * "execute" on demand — it is passive/event-driven, so its contract is
 * "bind yourself to whatever real-world event fires you, then call me back
 * when that happens" rather than "run now and return a result."
 *
 * Public extension point: third-party code implements this interface and
 * registers an instance via the `wfa/nodes/register` action (see
 * `docs/hooks-reference.md`).
 */
interface TriggerInterface extends NodeTypeInterface {

	/**
	 * Binds this trigger to its underlying event source (a WordPress hook,
	 * a cron schedule, a webhook, etc.), invoking `$on_fire` every time that
	 * event occurs for a workflow configured with this trigger.
	 *
	 * Implementations must not call `$on_fire` synchronously from within
	 * `bind()` itself — only when the underlying event later fires.
	 *
	 * @param array<string, mixed> $config  This trigger's configured field values (see configSchema()).
	 * @param callable             $on_fire function( array $payload, array $config ): void.
	 *
	 * @return void
	 */
	public function bind( array $config, callable $on_fire ): void;
}
