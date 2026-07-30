<?php
/**
 * Branch-aware execution order for workflow graphs.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves branch targets and main-path ordering for workflow execution.
 */
class GraphExecutionPlanner {

	public const BRANCHING_TYPES = array(
		'condition_action',
		'router_action',
	);

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph node entries.
	 * @param array<int, mixed> $connections Optional explicit flow connections.
	 * @param bool              $has_connections_key Whether the graph JSON includes a `connections` key.
	 *
	 * @return string[] Main-path client node ids in canvas order.
	 */
	public function getMainPathNodeIds( array $graph_nodes, array $connections = array(), bool $has_connections_key = false ): array {
		if ( $has_connections_key ) {
			if ( array() === $connections ) {
				return $this->getTriggerOnlyMainPath( $graph_nodes );
			}

			return $this->getMainPathNodeIdsByConnections( $graph_nodes, $connections );
		}

		return $this->getMainPathNodeIdsByPosition( $graph_nodes );
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph node entries.
	 *
	 * @return string[]
	 */
	private function getTriggerOnlyMainPath( array $graph_nodes ): array {
		foreach ( $this->mainCanvasNodes( $graph_nodes ) as $graph_node ) {
			if ( ( $graph_node['category'] ?? '' ) === 'trigger' ) {
				$id = (string) ( $graph_node['id'] ?? '' );

				if ( '' !== $id ) {
					return array( $id );
				}
			}
		}

		return array();
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph node entries.
	 *
	 * @return string[] Main-path client node ids in canvas order.
	 */
	private function getMainPathNodeIdsByPosition( array $graph_nodes ): array {
		$main_nodes     = $this->mainCanvasNodes( $graph_nodes );
		$sorted_main    = $this->sortByPosition( $main_nodes );
		$branch_targets = $this->collectBranchTargetIds( $graph_nodes );
		$main_path_ids  = array();

		foreach ( $sorted_main as $graph_node ) {
			$id = (string) ( $graph_node['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			if ( isset( $branch_targets[ $id ] ) ) {
				continue;
			}

			$main_path_ids[] = $id;
		}

		return $main_path_ids;
	}

	/**
	 * @param array<int, mixed> $graph_nodes   Raw graph node entries.
	 * @param array<int, mixed> $connections   Explicit `{ from, to }` connections.
	 *
	 * @return string[]
	 */
	private function getMainPathNodeIdsByConnections( array $graph_nodes, array $connections ): array {
		$outgoing   = $this->buildOutgoingMap( $connections );
		$trigger_id = $this->findTriggerId( $graph_nodes );

		if ( null === $trigger_id || '' === $trigger_id ) {
			return $this->getMainPathNodeIdsByPosition( $graph_nodes );
		}

		$branch_targets = $this->collectBranchTargetIds( $graph_nodes );
		$path           = array();
		$queue          = array( $trigger_id );
		$visited        = array();

		while ( array() !== $queue ) {
			$current = (string) array_shift( $queue );

			if ( '' === $current || isset( $visited[ $current ] ) ) {
				continue;
			}

			$visited[ $current ] = true;

			if ( ! isset( $branch_targets[ $current ] ) ) {
				$path[] = $current;
			}

			foreach ( $outgoing[ $current ] ?? array() as $next ) {
				if ( ! isset( $visited[ $next ] ) ) {
					$queue[] = $next;
				}
			}
		}

		return $path;
	}

	/**
	 * Builds from → [to, to, …] adjacency for fan-out execution.
	 *
	 * @param array<int, mixed> $connections Explicit connections.
	 *
	 * @return array<string, string[]>
	 */
	public function buildOutgoingMap( array $connections ): array {
		$outgoing = array();

		foreach ( $connections as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$from = isset( $connection['from'] ) ? trim( (string) $connection['from'] ) : '';
			$to   = isset( $connection['to'] ) ? trim( (string) $connection['to'] ) : '';

			if ( '' === $from || '' === $to ) {
				continue;
			}

			if ( ! isset( $outgoing[ $from ] ) ) {
				$outgoing[ $from ] = array();
			}

			if ( ! in_array( $to, $outgoing[ $from ], true ) ) {
				$outgoing[ $from ][] = $to;
			}
		}

		return $outgoing;
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph nodes.
	 *
	 * @return string|null
	 */
	public function findTriggerId( array $graph_nodes ): ?string {
		foreach ( $this->mainCanvasNodes( $graph_nodes ) as $graph_node ) {
			if ( ( $graph_node['category'] ?? '' ) === 'trigger' ) {
				$id = (string) ( $graph_node['id'] ?? '' );

				if ( '' !== $id ) {
					return $id;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, string[]> $outgoing Outgoing map.
	 * @param string                  $from_id  Source node id.
	 *
	 * @return string[]
	 */
	public function getOutgoingTargets( array $outgoing, string $from_id ): array {
		return $outgoing[ $from_id ] ?? array();
	}

	/**
	 * @param array<string, mixed> $result Execution output from a branching node.
	 *
	 * @return string[]
	 */
	public function resolveBranchTargets( array $result ): array {
		$branch_id = isset( $result['branch_node_id'] ) ? trim( (string) $result['branch_node_id'] ) : '';

		if ( '' === $branch_id ) {
			return array();
		}

		return array( $branch_id );
	}

	/**
	 * Removes main-path nodes that follow a branching node from the pending queue.
	 *
	 * @param string[] $pending_ids    Remaining queue.
	 * @param string   $branch_node_id Branching node client id.
	 * @param string[] $main_path_ids  Full main-path order.
	 *
	 * @return string[]
	 */
	public function stripMainPathAfterBranch( array $pending_ids, string $branch_node_id, array $main_path_ids ): array {
		$branch_index = array_search( $branch_node_id, $main_path_ids, true );

		if ( false === $branch_index ) {
			return $pending_ids;
		}

		$skip = array_flip( array_slice( $main_path_ids, (int) $branch_index + 1 ) );

		return array_values(
			array_filter(
				$pending_ids,
				static function ( string $id ) use ( $skip ): bool {
					return ! isset( $skip[ $id ] );
				}
			)
		);
	}

	/**
	 * @param string             $current_id     Current branch node id.
	 * @param array<int, mixed>  $graph_nodes    Raw graph nodes.
	 * @param array<string,true> $branch_targets Branch entry ids.
	 *
	 * @return string|null
	 */
	public function nextInBranchColumn( string $current_id, array $graph_nodes, array $branch_targets ): ?string {
		$current = $this->findGraphNode( $graph_nodes, $current_id );

		if ( null === $current ) {
			return null;
		}

		$current_x  = isset( $current['x'] ) ? (int) $current['x'] : 0;
		$current_y  = isset( $current['y'] ) ? (int) $current['y'] : 0;
		$candidates = array();

		foreach ( $this->mainCanvasNodes( $graph_nodes ) as $graph_node ) {
			$id = (string) ( $graph_node['id'] ?? '' );

			if ( '' === $id || $id === $current_id ) {
				continue;
			}

			$y = isset( $graph_node['y'] ) ? (int) $graph_node['y'] : 0;
			$x = isset( $graph_node['x'] ) ? (int) $graph_node['x'] : 0;

			if ( $y <= $current_y ) {
				continue;
			}

			if ( abs( $x - $current_x ) > 48 ) {
				continue;
			}

			if ( in_array( (string) ( $graph_node['type'] ?? '' ), self::BRANCHING_TYPES, true ) ) {
				continue;
			}

			if ( isset( $branch_targets[ $id ] ) ) {
				continue;
			}

			$candidates[] = array(
				'id' => $id,
				'y'  => $y,
			);
		}

		if ( array() === $candidates ) {
			return null;
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				return $a['y'] <=> $b['y'];
			}
		);

		return $candidates[0]['id'];
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph nodes.
	 *
	 * @return array<string, true>
	 */
	public function collectBranchTargetIds( array $graph_nodes ): array {
		$targets = array();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) ) {
				continue;
			}

			$type = (string) ( $graph_node['type'] ?? '' );

			if ( 'condition_action' === $type ) {
				$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
				$this->collectConditionTargets( $config, $targets );
			}

			if ( 'router_action' === $type ) {
				$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
				$this->collectRouterTargets( $config, $targets );
			}
		}

		return $targets;
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function mainCanvasNodes( array $graph_nodes ): array {
		$main = array();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) ) {
				continue;
			}

			if ( ! empty( $graph_node['parent_agent_id'] ) ) {
				continue;
			}

			$main[] = $graph_node;
		}

		return $main;
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes Graph nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sortByPosition( array $nodes ): array {
		usort(
			$nodes,
			static function ( array $a, array $b ): int {
				$y_a = isset( $a['y'] ) ? (int) $a['y'] : 0;
				$y_b = isset( $b['y'] ) ? (int) $b['y'] : 0;
				$x_a = isset( $a['x'] ) ? (int) $a['x'] : 0;
				$x_b = isset( $b['x'] ) ? (int) $b['x'] : 0;

				if ( $y_a !== $y_b ) {
					return $y_a <=> $y_b;
				}

				return $x_a <=> $x_b;
			}
		);

		return $nodes;
	}

	/**
	 * @param array<string, mixed> $config  Condition node config.
	 * @param array<string, true>  $targets Target id map.
	 *
	 * @return void
	 */
	private function collectConditionTargets( array $config, array &$targets ): void {
		$conditions = isset( $config['conditions'] ) && is_array( $config['conditions'] ) ? $config['conditions'] : array();

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			$node_id = isset( $condition['node_id'] ) ? trim( (string) $condition['node_id'] ) : '';

			if ( '' !== $node_id ) {
				$targets[ $node_id ] = true;
			}
		}

		$default_id = isset( $config['default_branch_node_id'] ) ? trim( (string) $config['default_branch_node_id'] ) : '';

		if ( '' !== $default_id ) {
			$targets[ $default_id ] = true;
		}

		foreach ( array( 'true_branch_node_id', 'false_branch_node_id' ) as $legacy_key ) {
			$legacy_id = isset( $config[ $legacy_key ] ) ? trim( (string) $config[ $legacy_key ] ) : '';

			if ( '' !== $legacy_id ) {
				$targets[ $legacy_id ] = true;
			}
		}
	}

	/**
	 * @param array<string, mixed> $config  Router node config.
	 * @param array<string, true>  $targets Target id map.
	 *
	 * @return void
	 */
	private function collectRouterTargets( array $config, array &$targets ): void {
		$routes = isset( $config['routes'] ) && is_array( $config['routes'] ) ? $config['routes'] : array();

		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$node_id = isset( $route['node_id'] ) ? trim( (string) $route['node_id'] ) : '';

			if ( '' !== $node_id ) {
				$targets[ $node_id ] = true;
			}
		}

		$default_id = isset( $config['default_branch_node_id'] ) ? trim( (string) $config['default_branch_node_id'] ) : '';

		if ( '' !== $default_id ) {
			$targets[ $default_id ] = true;
		}
	}

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph nodes.
	 * @param string            $client_id   Client node id.
	 *
	 * @return array<string, mixed>|null
	 */
	private function findGraphNode( array $graph_nodes, string $client_id ): ?array {
		foreach ( $graph_nodes as $graph_node ) {
			if ( is_array( $graph_node ) && (string) ( $graph_node['id'] ?? '' ) === $client_id ) {
				return $graph_node;
			}
		}

		return null;
	}
}
