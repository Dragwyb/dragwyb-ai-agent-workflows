<?php
/**
 * Action node type contract.
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
 * An action is a single unit of work a workflow performs (send an email,
 * call an API, update a post, etc.). Unlike a trigger, it runs synchronously
 * on demand and reports back what happened.
 *
 * Public extension point: third-party code implements this interface and
 * registers an instance via the `wfa/nodes/register` action (see
 * `docs/hooks-reference.md`).
 */
interface ActionInterface extends NodeTypeInterface {

	/**
	 * Executes this action synchronously and returns its outcome.
	 *
	 * Implementations must not throw for expected/handleable failures (e.g.
	 * an HTTP request timing out) — report `'success' => false` with an
	 * `'error'` message instead, so a future execution engine can log a
	 * clean per-node failure rather than an uncaught exception. Throwing is
	 * reserved for genuine programmer error (e.g. missing required config
	 * that should have been caught at save time).
	 *
	 * @param array<string, mixed> $config  This action's configured field values (see configSchema()).
	 * @param array<string, mixed> $context Runtime data available to this node (trigger payload, prior node outputs).
	 *
	 * @return array{success: bool, error?: string} Additional keys are action-specific.
	 */
	public function execute( array $config, array $context ): array;
}
