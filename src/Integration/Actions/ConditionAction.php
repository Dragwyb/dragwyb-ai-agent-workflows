<?php
/**
 * Condition tool — yes/no branching.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ContextPathResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConditionAction implements ActionInterface {

	public function slug(): string {
		return 'condition_action';
	}

	public function label(): string {
		return __( 'Condition', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Checks a value and splits yes/no (attach to AI Agent).', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'field' => array(
				'type' => 'string',
				'label' => __( 'Value to check', 'workflow-automate' ),
				'supports_variables' => true,
				'required' => true,
			),
			'operator' => array(
				'type' => 'select',
				'label' => __( 'Comparison', 'workflow-automate' ),
				'default' => 'equals',
				'options' => array(
					array( 'value' => 'equals', 'label' => __( 'equals', 'workflow-automate' ) ),
					array( 'value' => 'contains', 'label' => __( 'contains', 'workflow-automate' ) ),
					array( 'value' => 'is_empty', 'label' => __( 'is empty', 'workflow-automate' ) ),
				),
			),
			'value' => array(
				'type' => 'string',
				'label' => __( 'Compare to', 'workflow-automate' ),
				'default' => '',
			),
			'true_branch_node_id' => array(
				'type' => 'node_select',
				'label' => __( 'If yes, run this step', 'workflow-automate' ),
				'default' => '',
			),
			'false_branch_node_id' => array(
				'type' => 'node_select',
				'label' => __( 'If no, run this step', 'workflow-automate' ),
				'default' => '',
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$field    = isset( $config['field'] ) ? (string) $config['field'] : '';
		$operator = isset( $config['operator'] ) ? (string) $config['operator'] : 'equals';
		$compare  = isset( $config['value'] ) ? (string) $config['value'] : '';
		$resolved = ContextPathResolver::resolveValue( $context, $field );
		$left     = is_scalar( $resolved ) ? trim( (string) $resolved ) : '';
		$passed   = $this->evaluate( $left, $operator, trim( $compare ) );

		return array(
			'success' => true,
			'passed' => $passed,
			'branch' => $passed ? 'true' : 'false',
			'evaluated_value' => $left,
			'true_branch_node_id' => isset( $config['true_branch_node_id'] ) ? (string) $config['true_branch_node_id'] : '',
			'false_branch_node_id' => isset( $config['false_branch_node_id'] ) ? (string) $config['false_branch_node_id'] : '',
		);
	}

	private function evaluate( string $left, string $operator, string $right ): bool {
		switch ( $operator ) {
			case 'is_empty':
				return '' === $left;
			case 'contains':
				return '' !== $left && false !== stripos( $left, $right );
			case 'equals':
			default:
				return $left === $right;
		}
	}
}
