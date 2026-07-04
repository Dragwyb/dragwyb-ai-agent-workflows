<?php
/**
 * Shared node type metadata contract.
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
 * Common metadata every node type (trigger or action) must provide.
 *
 * Not part of the public extensibility surface by itself — third-party code
 * implements `TriggerInterface` or `ActionInterface` (both extend this),
 * never this interface directly. It exists purely so `NodeTypeRegistry`
 * doesn't duplicate identical metadata-handling code for both kinds.
 */
interface NodeTypeInterface {

	/**
	 * A stable, unique identifier for this node type (e.g. `wp_hook_trigger`).
	 * Stored in `wfa_workflow_nodes.node_type`; must never change once
	 * workflows may reference it.
	 *
	 * @return string
	 */
	public function slug(): string;

	/**
	 * Human-readable name shown in the builder's node palette.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Short, human-readable explanation shown alongside the label.
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Describes the configurable fields this node type accepts, so the
	 * builder's node configuration panel can render them generically
	 * without hardcoding knowledge of any specific node type.
	 *
	 * @return array<string, array{type: string, label: string, required?: bool, default?: mixed}>
	 */
	public function configSchema(): array;
}
