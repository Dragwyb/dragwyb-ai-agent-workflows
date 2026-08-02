<?php

/**

 * ConditionAIAWAanch filtering.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);



namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;

use AIAWA\Plugin\Service\ContextPathResolver;



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



class ConditionAction implements ActionInterface {



	public function slug(): string {

		return 'condition_action';
	}



	public function label(): string {

		return __( 'Condition', 'dragwyb-agentflow' );
	}



	public function description(): string {

		return __( 'Route the workflow down different branches when conditions match.', 'dragwyb-agentflow' );
	}



	public function configSchema(): array {

		return array(

			'conditions'             => array(

				'type'    => 'condition_routes',

				'label'   => __( 'Conditions', 'dragwyb-agentflow' ),

				'default' => array(),

			),

			'default_branch_node_id' => array(

				'type'    => 'node_select',

				'label'   => __( 'No Condition Matched', 'dragwyb-agentflow' ),

				'default' => '',

			),

		);
	}



	public function execute( array $config, array $context ): array {

		$conditions = $this->normalizeConditions( $config );

		foreach ( $conditions as $condition ) {

			$field = (string) ( $condition['field'] ?? '' );

			$operator = (string) ( $condition['operator'] ?? 'equals' );

			$compare = (string) ( $condition['value'] ?? '' );

			$resolved = ContextPathResolver::resolveValue( $context, $field );

			if ( ! $this->evaluate( $resolved, $operator, trim( $compare ) ) ) {

				continue;

			}

			$left = is_scalar( $resolved ) ? trim( (string) $resolved ) : '';

			$node_id = isset( $condition['node_id'] ) ? trim( (string) $condition['node_id'] ) : '';

			return array(

				'success'                 => true,

				'passed'                  => true,

				'branch'                  => (string) ( $condition['id'] ?? 'matched' ),

				'matched_condition_id'    => (string) ( $condition['id'] ?? '' ),

				'matched_condition_label' => (string) ( $condition['label'] ?? '' ),

				'evaluated_value'         => $left,

				'branch_node_id'          => $node_id,

			);

		}

		$default_id = $this->resolveDefaultBranch( $config );

		return array(

			'success'                 => true,

			'passed'                  => false,

			'branch'                  => 'default',

			'matched_condition_id'    => 'default',

			'matched_condition_label' => __( 'No Condition Matched', 'dragwyb-agentflow' ),

			'evaluated_value'         => '',

			'branch_node_id'          => $default_id,

		);
	}



	/**

	 * @param array<string, mixed> $config Saved node config.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function normalizeConditions( array $config ): array {

		$conditions = isset( $config['conditions'] ) && is_array( $config['conditions'] ) ? $config['conditions'] : array();

		if ( array() !== $conditions ) {

			return $conditions;

		}

		// Legacy yes/no config.

		$field = isset( $config['field'] ) ? (string) $config['field'] : '';

		$operator = isset( $config['operator'] ) ? (string) $config['operator'] : 'equals';

		$value = isset( $config['value'] ) ? (string) $config['value'] : '';

		if ( '' === $field ) {

			return array();

		}

		return array(

			array(

				'id'       => 'legacy-true',

				'label'    => __( 'If yes', 'dragwyb-agentflow' ),

				'field'    => $field,

				'operator' => $operator,

				'value'    => $value,

				'node_id'  => isset( $config['true_branch_node_id'] ) ? (string) $config['true_branch_node_id'] : '',

			),

		);
	}



	/**

	 * @param array<string, mixed> $config Saved node config.
	 *
	 * @return string
	 */
	private function resolveDefaultBranch( array $config ): string {

		$default = isset( $config['default_branch_node_id'] ) ? trim( (string) $config['default_branch_node_id'] ) : '';

		if ( '' !== $default ) {

			return $default;

		}

		return isset( $config['false_branch_node_id'] ) ? trim( (string) $config['false_branch_node_id'] ) : '';
	}



	/**

	 * @param mixed  $resolved Resolved field value.

	 * @param string $operator Comparison operator.

	 * @param string $right    Compare-to value.
	 *
	 * @return bool
	 */
	private function evaluate( $resolved, string $operator, string $right ): bool {

		$left = is_scalar( $resolved ) ? trim( (string) $resolved ) : '';

		$has_value = null !== $resolved && false !== $resolved && (

			! is_scalar( $resolved ) || '' !== $left

		);

		switch ( $operator ) {

			case 'exists':
				return $has_value;

			case 'not_exists':
				return ! $has_value;

			case 'is_empty':
				return ! $has_value || '' === $left;

			case 'is_not_empty':
				return $has_value && '' !== $left;

			case 'equals':
				return $left === $right;

			case 'equals_i':
				return 0 === strcasecmp( $left, $right );

			case 'not_equals':
				return $left !== $right;

			case 'not_equals_i':
				return 0 !== strcasecmp( $left, $right );

			case 'contains':
				return '' !== $left && '' !== $right && false !== strpos( $left, $right );

			case 'contains_i':
				return '' !== $left && '' !== $right && false !== stripos( $left, $right );

			case 'not_contains':
				return '' === $left || '' === $right || false === strpos( $left, $right );

			case 'not_contains_i':
				return '' === $left || '' === $right || false === stripos( $left, $right );

			case 'starts_with':
				return '' !== $left && '' !== $right && 0 === strpos( $left, $right );

			case 'starts_with_i':
				return '' !== $left && '' !== $right && 0 === stripos( $left, $right );

			case 'ends_with':
				return '' !== $left && '' !== $right && substr( $left, -strlen( $right ) ) === $right;

			case 'ends_with_i':
				return '' !== $left && '' !== $right && 0 === strcasecmp( substr( $left, -strlen( $right ) ), $right );

			case 'regex':
				return $this->matches_regex( $left, $right, false );

			case 'regex_i':
				return $this->matches_regex( $left, $right, true );

			case 'not_regex':
				return ! $this->matches_regex( $left, $right, false );

			case 'not_regex_i':
				return ! $this->matches_regex( $left, $right, true );

			case 'num_equals':
				return $this->compare_numbers( $resolved, $right, '==' );

			case 'num_not_equals':
				return $this->compare_numbers( $resolved, $right, '!=' );

			case 'gt':
				return $this->compare_numbers( $resolved, $right, '>' );

			case 'lt':
				return $this->compare_numbers( $resolved, $right, '<' );

			case 'gte':
				return $this->compare_numbers( $resolved, $right, '>=' );

			case 'lte':
				return $this->compare_numbers( $resolved, $right, '<=' );

			case 'date_equals':
				return $this->compare_dates( $left, $right, '==' );

			case 'date_not_equals':
				return $this->compare_dates( $left, $right, '!=' );

			case 'after':
				return $this->compare_dates( $left, $right, '>' );

			case 'before':
				return $this->compare_dates( $left, $right, '<' );

			case 'after_equals':
				return $this->compare_dates( $left, $right, '>=' );

			case 'before_equals':
				return $this->compare_dates( $left, $right, '<=' );

			case 'is_true':
				return true === $this->as_bool( $resolved );

			case 'is_false':
				return false === $this->as_bool( $resolved );

			case 'bool_equals':
				$bool = $this->as_bool( $resolved );

				$compare_bool = $this->as_bool( $right );

				return null !== $bool && null !== $compare_bool && $bool === $compare_bool;

			case 'bool_not_equals':
				$bool = $this->as_bool( $resolved );

				$compare_bool = $this->as_bool( $right );

				return null !== $bool && null !== $compare_bool && $bool !== $compare_bool;

			default:
				return $left === $right;

		}
	}



	/**

	 * @param string $left    Subject string.

	 * @param string $pattern Regex pattern.

	 * @param bool   $ignore_case Case-insensitive match.
	 *
	 * @return bool
	 */
	private function matches_regex( string $left, string $pattern, bool $ignore_case ): bool {

		if ( '' === $pattern ) {

			return false;

		}

		$delimiter = '/';

		$escaped = str_replace( $delimiter, '\\' . $delimiter, $pattern );

		$flags = $ignore_case ? 'i' : '';

		$regex = @preg_match( $delimiter . $escaped . $delimiter . $flags, $left );

		return 1 === $regex;
	}



	/**

	 * @param mixed  $left  Resolved value.

	 * @param string $right Compare-to value.

	 * @param string $op    Comparison operator.
	 *
	 * @return bool
	 */
	private function compare_numbers( $left, string $right, string $op ): bool {

		$left_num = $this->as_number( $left );

		$right_num = $this->as_number( $right );

		if ( null === $left_num || null === $right_num ) {

			return false;

		}

		switch ( $op ) {

			case '>':
				return $left_num > $right_num;

			case '<':
				return $left_num < $right_num;

			case '>=':
				return $left_num >= $right_num;

			case '<=':
				return $left_num <= $right_num;

			case '!=':
				return $left_num !== $right_num;

			case '==':
			default:
				return $left_num === $right_num;

		}
	}



	/**

	 * @param string $left  Date string.

	 * @param string $right Compare-to date string.

	 * @param string $op    Comparison operator.
	 *
	 * @return bool
	 */
	private function compare_dates( string $left, string $right, string $op ): bool {

		$left_ts = $this->as_timestamp( $left );

		$right_ts = $this->as_timestamp( $right );

		if ( null === $left_ts || null === $right_ts ) {

			return false;

		}

		switch ( $op ) {

			case '>':
				return $left_ts > $right_ts;

			case '<':
				return $left_ts < $right_ts;

			case '>=':
				return $left_ts >= $right_ts;

			case '<=':
				return $left_ts <= $right_ts;

			case '!=':
				return $left_ts !== $right_ts;

			case '==':
			default:
				return $left_ts === $right_ts;

		}
	}



	/**

	 * @param mixed $value Raw value.
	 *
	 * @return float|null
	 */
	private function as_number( $value ): ?float {

		if ( is_int( $value ) || is_float( $value ) ) {

			return (float) $value;

		}

		if ( is_string( $value ) && is_numeric( trim( $value ) ) ) {

			return (float) trim( $value );

		}

		return null;
	}



	/**

	 * @param string $value Date string.
	 *
	 * @return int|null Unix timestamp.
	 */
	private function as_timestamp( string $value ): ?int {

		if ( '' === trim( $value ) ) {

			return null;

		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : $timestamp;
	}



	/**

	 * @param mixed $value Raw value.
	 *
	 * @return bool|null
	 */
	private function as_bool( $value ): ?bool {

		if ( is_bool( $value ) ) {

			return $value;

		}

		if ( is_int( $value ) || is_float( $value ) ) {

			return 0 !== (int) $value;

		}

		if ( ! is_scalar( $value ) ) {

			return null;

		}

		$normalized = strtolower( trim( (string) $value ) );

		if ( in_array( $normalized, array( 'true', '1', 'yes', 'on' ), true ) ) {

			return true;

		}

		if ( in_array( $normalized, array( 'false', '0', 'no', 'off', '' ), true ) ) {

			return false;

		}

		return null;
	}
}
