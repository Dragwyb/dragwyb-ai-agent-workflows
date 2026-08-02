<?php
/**
 * Router tool — branch workflow based on a field value.
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

class RouterAction implements ActionInterface {

	public function slug(): string {
		return 'router_action';
	}

	public function label(): string {
		return __( 'Router', 'dragwyb-agentflow' );
	}

	public function description(): string {
		return __( 'Routes to different steps based on a value.', 'dragwyb-agentflow' );
	}

	public function configSchema(): array {
		return array(
			'route_field'            => array(
				'type'               => 'string',
				'label'              => __( 'Value to check', 'dragwyb-agentflow' ),
				'supports_variables' => true,
				'required'           => true,
			),
			'routes'                 => array(
				'type'    => 'router_routes',
				'label'   => __( 'Matching rules', 'dragwyb-agentflow' ),
				'default' => array(),
			),
			'default_branch_node_id' => array(
				'type'    => 'node_select',
				'label'   => __( 'Otherwise, run this step', 'dragwyb-agentflow' ),
				'default' => '',
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$field     = isset( $config['route_field'] ) ? (string) $config['route_field'] : '';
		$value     = ContextPathResolver::resolveValue( $context, $field );
		$value_str = is_scalar( $value ) ? trim( (string) $value ) : '';

		$routes          = isset( $config['routes'] ) && is_array( $config['routes'] ) ? $config['routes'] : array();
		$matched_route   = 'default';
		$matched_node_id = isset( $config['default_branch_node_id'] ) ? (string) $config['default_branch_node_id'] : '';

		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$match_value = isset( $route['match'] ) ? trim( (string) $route['match'] ) : '';
			$node_id     = isset( $route['node_id'] ) ? (string) $route['node_id'] : '';

			if ( '' === $match_value || '' === $node_id ) {
				continue;
			}

			if ( strcasecmp( $value_str, $match_value ) === 0 ) {
				$matched_route   = $match_value;
				$matched_node_id = $node_id;
				break;
			}
		}

		return array(
			'success'        => true,
			'matched_route'  => $matched_route,
			'field_value'    => $value_str,
			'branch_node_id' => $matched_node_id,
		);
	}
}
