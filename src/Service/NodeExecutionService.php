<?php
/**
 * Node execution service.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use Throwable;
use AIAWA\Plugin\Domain\WorkflowNode;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes a single workflow node (an action) via the Integration layer.
 *
 * Deliberately the only place that calls ActionInterface::execute()
 * directly: this keeps WorkflowExecutionService focused on orchestrating a
 * whole run (looping, logging, status roll-up) rather than also knowing how
 * an individual node is invoked.
 */
class NodeExecutionService {

	private NodeTypeRegistry $registry;

	public function __construct( NodeTypeRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Runs a single node and returns its outcome. Never throws: an
	 * unregistered node type, or a third-party action that misbehaves
	 * (throws instead of honoring its "report don't throw" contract — see
	 * ActionInterface::execute()), is reported back as a normal failure
	 * result instead of aborting the whole workflow run.
	 *
	 * @param WorkflowNode         $node    The node to execute.
	 * @param array<string, mixed> $context Runtime data available to this node (trigger payload, prior node outputs).
	 *
	 * @return array{success: bool, error?: string} Additional keys are action-specific.
	 */
	public function execute( WorkflowNode $node, array $context ): array {
		/**
		 * Fires immediately before a single node executes.
		 *
		 * @since 0.1.0
		 *
		 * @param WorkflowNode         $node    The node about to execute.
		 * @param array<string, mixed> $context Runtime data available to this node.
		 */
		do_action( 'aiawa/node/before_execute', $node, $context );

		$result = $this->executeAction( $node, $context );

		/**
		 * Fires immediately after a single node executes, whether it
		 * succeeded or failed.
		 *
		 * @since 0.1.0
		 *
		 * @param WorkflowNode         $node    The node that just executed.
		 * @param array                $result  Its outcome (see return value of execute()).
		 * @param array<string, mixed> $context Runtime data that was available to this node.
		 */
		do_action( 'aiawa/node/after_execute', $node, $result, $context );

		return $result;
	}

	/**
	 * @param WorkflowNode         $node    The node to execute.
	 * @param array<string, mixed> $context Runtime data available to this node.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function executeAction( WorkflowNode $node, array $context ): array {
		$action = $this->registry->action( $node->nodeType() );

		if ( null === $action ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: node type slug. */
					__( 'No action node type is registered for "%s". It may belong to a deactivated plugin.', 'ai-agent-workflow-automation' ),
					$node->nodeType()
				),
			);
		}

		$config = $node->config() ?? array();
		// Substitute {{trigger.fields.email}} (and similar) tokens so actions
		// can use form/order data without each integration reimplementing it.
		$config = ( new ConfigInterpolator() )->interpolateConfig( $config, $context );

		try {
			$result = $action->execute( $config, $context );
		} catch ( Throwable $exception ) {
			return array(
				'success' => false,
				'error'   => $exception->getMessage(),
			);
		}

		if ( ! is_array( $result ) || ! array_key_exists( 'success', $result ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The action returned an invalid result.', 'ai-agent-workflow-automation' ),
			);
		}

		$result['success'] = (bool) $result['success'];

		return $result;
	}
}
