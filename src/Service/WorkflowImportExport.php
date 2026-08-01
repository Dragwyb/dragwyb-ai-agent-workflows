<?php
/**
 * Workflow JSON import/export (n8n-style portable definition).
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use InvalidArgumentException;
use AIAWA\Plugin\Domain\Workflow;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and parses portable workflow JSON files.
 *
 * Shape is intentionally close to n8n (name, nodes, connections, active,
 * settings, meta) while using this plugin's native node/connection objects.
 */
class WorkflowImportExport {

	public const FORMAT = 'workflow-automate';

	public const FORMAT_VERSION = 1;

	/**
	 * Settings keys that are site/runtime-specific and should not travel
	 * with a portable workflow definition.
	 *
	 * @var string[]
	 */
	private const TRANSIENT_SETTING_KEYS = array(
		'sample_payload',
		'sample_payload_trigger_type',
		'sample_payload_captured_at',
		'test_listen_active',
		'test_listen_started_at',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function exportWorkflow( Workflow $workflow ): array {
		$graph       = $workflow->graph();
		$nodes       = isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ? array_values( $graph['nodes'] ) : array();
		$connections = isset( $graph['connections'] ) && is_array( $graph['connections'] ) ? array_values( $graph['connections'] ) : array();
		$settings    = self::portableSettings( $workflow->settings() );

		return array(
			'name'        => $workflow->title(),
			'nodes'       => $nodes,
			'connections' => $connections,
			'active'      => Workflow::STATUS_ACTIVE === $workflow->status(),
			'settings'    => (object) $settings,
			'meta'        => array(
				'format'     => self::FORMAT,
				'version'    => self::FORMAT_VERSION,
				'exportedAt' => gmdate( 'c' ),
			),
			'id'          => $workflow->id(),
		);
	}

	/**
	 * Suggested download filename for an exported workflow.
	 */
	public static function exportFilename( Workflow $workflow ): string {
		$slug = sanitize_title( $workflow->title() );

		if ( '' === $slug ) {
			$slug = 'workflow';
		}

		return $slug . '.json';
	}

	/**
	 * Decodes a JSON string into an associative array.
	 *
	 * @throws InvalidArgumentException When the payload is not a valid JSON object.
	 *
	 * @return array<string, mixed>
	 */
	public static function decodeJson( string $json ): array {
		$json = trim( $json );

		if ( '' === $json ) {
			throw new InvalidArgumentException(
				esc_html__( 'The import file is empty.', 'workflow-automate' )
			);
		}

		$decoded = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'The import file is not valid JSON.', 'workflow-automate' )
			);
		}

		if ( self::isList( $decoded ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'The import file must be a workflow JSON object, not an array.', 'workflow-automate' )
			);
		}

		return $decoded;
	}

	/**
	 * Parses a decoded workflow JSON object into create/update attributes.
	 *
	 * @param array<string, mixed> $payload Decoded JSON object.
	 *
	 * @throws InvalidArgumentException When the payload is not a supported workflow definition.
	 *
	 * @return array{title: string, graph: array{nodes: array<int, mixed>, connections: array<int, mixed>}, settings: array<string, mixed>|null, status: int}
	 */
	public static function parseImportPayload( array $payload ): array {
		if ( self::looksLikeN8n( $payload ) ) {
			throw new InvalidArgumentException(
				esc_html__( 'This file looks like an n8n workflow. Import a Workflow Automate JSON export instead.', 'workflow-automate' )
			);
		}

		$title = '';

		if ( isset( $payload['name'] ) && is_string( $payload['name'] ) ) {
			$title = sanitize_text_field( $payload['name'] );
		} elseif ( isset( $payload['title'] ) && is_string( $payload['title'] ) ) {
			$title = sanitize_text_field( $payload['title'] );
		}

		if ( '' === $title ) {
			$title = __( 'Imported workflow', 'workflow-automate' );
		}

		$graph = self::extractGraph( $payload );

		$has_graph_keys = isset( $payload['graph'] ) || isset( $payload['nodes'] ) || self::isOurFormat( $payload );

		if ( array() === $graph['nodes'] && ! $has_graph_keys ) {
			throw new InvalidArgumentException(
				esc_html__( 'This JSON does not look like a workflow definition.', 'workflow-automate' )
			);
		}

		$settings = null;

		if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$settings = self::portableSettings( $payload['settings'] );
		}

		$status = Workflow::STATUS_DRAFT;

		if ( array_key_exists( 'active', $payload ) ) {
			$status = ! empty( $payload['active'] ) ? Workflow::STATUS_ACTIVE : Workflow::STATUS_DRAFT;
		} elseif ( isset( $payload['status'] ) && is_numeric( $payload['status'] ) ) {
			$status_int = (int) $payload['status'];

			if ( in_array( $status_int, Workflow::VALID_STATUSES, true ) ) {
				$status = $status_int;
			}
		}

		return array(
			'title'    => $title,
			'graph'    => $graph,
			'settings' => $settings,
			'status'   => $status,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 *
	 * @return array{nodes: array<int, mixed>, connections: array<int, mixed>}
	 */
	private static function extractGraph( array $payload ): array {
		$nodes       = array();
		$connections = array();

		if ( isset( $payload['graph'] ) && is_array( $payload['graph'] ) ) {
			$graph_nodes = $payload['graph']['nodes'] ?? array();
			$graph_conns = $payload['graph']['connections'] ?? array();
			$nodes       = is_array( $graph_nodes ) ? $graph_nodes : array();
			$connections = is_array( $graph_conns ) ? $graph_conns : array();
		} else {
			if ( isset( $payload['nodes'] ) && is_array( $payload['nodes'] ) ) {
				$nodes = $payload['nodes'];
			}

			if ( isset( $payload['connections'] ) && is_array( $payload['connections'] ) ) {
				$connections = $payload['connections'];
			}
		}

		// n8n-style connection maps are objects keyed by node name — reject those.
		if ( ! self::isList( $connections ) && array() !== $connections ) {
			throw new InvalidArgumentException(
				esc_html__( 'Unsupported connections format. Expected a Workflow Automate connections array.', 'workflow-automate' )
			);
		}

		$normalized_nodes = array();

		foreach ( array_values( $nodes ) as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$id   = isset( $node['id'] ) ? (string) $node['id'] : '';
			$type = isset( $node['type'] ) ? (string) $node['type'] : '';

			if ( '' === $id || '' === $type ) {
				continue;
			}

			$normalized_nodes[] = $node;
		}

		$normalized_connections = array();

		foreach ( array_values( $connections ) as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$from = isset( $connection['from'] ) ? (string) $connection['from'] : '';
			$to   = isset( $connection['to'] ) ? (string) $connection['to'] : '';

			if ( '' === $from || '' === $to ) {
				continue;
			}

			if ( empty( $connection['id'] ) ) {
				$connection['id'] = self::generateId( 'conn' );
			}

			$normalized_connections[] = $connection;
		}

		return array(
			'nodes'       => $normalized_nodes,
			'connections' => $normalized_connections,
		);
	}

	/**
	 * @param array<string, mixed>|null $settings
	 *
	 * @return array<string, mixed>
	 */
	private static function portableSettings( ?array $settings ): array {
		if ( null === $settings || array() === $settings ) {
			return array();
		}

		$portable = $settings;

		foreach ( self::TRANSIENT_SETTING_KEYS as $key ) {
			unset( $portable[ $key ] );
		}

		return $portable;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function isOurFormat( array $payload ): bool {
		$meta = $payload['meta'] ?? null;

		return is_array( $meta )
			&& isset( $meta['format'] )
			&& self::FORMAT === (string) $meta['format'];
	}

	/**
	 * Heuristic: n8n exports use typeVersion on nodes and an object map for connections.
	 *
	 * @param array<string, mixed> $payload
	 */
	private static function looksLikeN8n( array $payload ): bool {
		if ( self::isOurFormat( $payload ) ) {
			return false;
		}

		$nodes = $payload['nodes'] ?? null;

		if ( ! is_array( $nodes ) || array() === $nodes ) {
			return false;
		}

		$first = reset( $nodes );

		if ( ! is_array( $first ) ) {
			return false;
		}

		$has_type_version = array_key_exists( 'typeVersion', $first );
		$type             = isset( $first['type'] ) ? (string) $first['type'] : '';
		$is_n8n_type      = 0 === strpos( $type, 'n8n-nodes-' ) || 0 === strpos( $type, '@n8n/' );

		$connections = $payload['connections'] ?? null;
		$n8n_conns   = is_array( $connections ) && ! self::isList( $connections ) && array() !== $connections;

		return ( $has_type_version && $is_n8n_type ) || ( $has_type_version && $n8n_conns );
	}

	/**
	 * @param mixed $value
	 */
	private static function isList( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}

		$expected = 0;

		foreach ( $value as $key => $_unused ) {
			if ( $key !== $expected ) {
				return false;
			}

			++$expected;
		}

		return true;
	}

	private static function generateId( string $prefix ): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return $prefix . '-' . wp_generate_uuid4();
		}

		return $prefix . '-' . uniqid( '', true );
	}
}
