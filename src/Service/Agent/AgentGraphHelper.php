<?php
/**
 * Resolves AI Agent attachments from the workflow builder graph.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps flat builder nodes (parent_agent_id) to agent sub-node structure.
 */
class AgentGraphHelper {

	public const CHAT_MODEL_OPENAI = 'openai_chat_action';

	public const CHAT_MODEL_CLAUDE = 'claude_messages_action';

	public const CHAT_MODEL_GEMINI = 'gemini_generate_content_action';

	public const MEMORY_TYPE = 'simple_memory';

	/**
	 * @param array<int, mixed> $graph_nodes Raw graph node list.
	 *
	 * @return bool
	 */
	public static function isAgentAttachment( array $graph_nodes, string $client_node_id ): bool {
		$node = self::findNode( $graph_nodes, $client_node_id );

		if ( null === $node ) {
			return false;
		}

		return self::nodeIsAttachment( $node );
	}

	/**
	 * @param array<string, mixed> $node Graph node entry.
	 *
	 * @return bool
	 */
	public static function nodeIsAttachment( array $node ): bool {
		return ! empty( $node['parent_agent_id'] );
	}

	/**
	 * @param array<int, mixed> $graph_nodes Graph nodes.
	 * @param string            $agent_id    Agent client node id.
	 *
	 * @return array{
	 *     chat_model: array<string, mixed>|null,
	 *     memory: array<string, mixed>|null,
	 *     tools: array<int, array<string, mixed>>
	 * }
	 */
	public static function resolveAttachments( array $graph_nodes, string $agent_id ): array {
		$chat_model = null;
		$memory     = null;
		$tools      = array();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) ) {
				continue;
			}

			if ( (string) ( $graph_node['parent_agent_id'] ?? '' ) !== $agent_id ) {
				continue;
			}

			$attachment_type = (string) ( $graph_node['attachment_type'] ?? '' );

			if ( 'chat_model' === $attachment_type || self::isChatModelType( (string) ( $graph_node['type'] ?? '' ) ) ) {
				$chat_model = $graph_node;
				continue;
			}

			if ( 'memory' === $attachment_type || self::MEMORY_TYPE === ( $graph_node['type'] ?? '' ) ) {
				$memory = $graph_node;
				continue;
			}

			$tools[] = $graph_node;
		}

		return array(
			'chat_model' => $chat_model,
			'memory'     => $memory,
			'tools'      => $tools,
		);
	}

	/**
	 * @param array<string, mixed>|null $chat_model Chat model attachment.
	 * @param array<string, mixed>      $agent_config Agent inline config fallback.
	 *
	 * @return array{provider: string, connection_id: int, model: string, node_type: string}
	 */
	public static function resolveChatModelConfig( ?array $chat_model, array $agent_config ): array {
		if ( is_array( $chat_model ) ) {
			$node_type = (string) ( $chat_model['type'] ?? '' );
			$config    = isset( $chat_model['config'] ) && is_array( $chat_model['config'] ) ? $chat_model['config'] : array();

			return array(
				'provider'      => self::providerFromNodeType( $node_type ),
				'connection_id' => (int) ( $config['connection_id'] ?? 0 ),
				'model'         => (string) ( $config['model'] ?? '' ),
				'node_type'     => $node_type,
			);
		}

		$provider = isset( $agent_config['provider'] ) ? strtolower( (string) $agent_config['provider'] ) : 'openai';

		return array(
			'provider'      => $provider,
			'connection_id' => (int) ( $agent_config['connection_id'] ?? 0 ),
			'model'         => (string) ( $agent_config['model'] ?? '' ),
			'node_type'     => self::nodeTypeFromProvider( $provider ),
		);
	}

	/**
	 * @param string $node_type Action node type slug.
	 *
	 * @return bool
	 */
	public static function isChatModelType( string $node_type ): bool {
		return in_array( $node_type, array( self::CHAT_MODEL_OPENAI, self::CHAT_MODEL_CLAUDE, self::CHAT_MODEL_GEMINI ), true );
	}

	/**
	 * @param string $node_type Chat model node type.
	 *
	 * @return string openai|claude|gemini
	 */
	public static function providerFromNodeType( string $node_type ): string {
		if ( self::CHAT_MODEL_CLAUDE === $node_type ) {
			return 'claude';
		}

		if ( self::CHAT_MODEL_GEMINI === $node_type ) {
			return 'gemini';
		}

		return 'openai';
	}

	/**
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	public static function nodeTypeFromProvider( string $provider ): string {
		if ( 'claude' === $provider ) {
			return self::CHAT_MODEL_CLAUDE;
		}

		if ( 'gemini' === $provider ) {
			return self::CHAT_MODEL_GEMINI;
		}

		return self::CHAT_MODEL_OPENAI;
	}

	/**
	 * @param array<int, mixed> $graph_nodes Graph nodes.
	 * @param string            $client_id   Node id to find.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function findNode( array $graph_nodes, string $client_id ): ?array {
		foreach ( $graph_nodes as $graph_node ) {
			if ( is_array( $graph_node ) && isset( $graph_node['id'] ) && (string) $graph_node['id'] === $client_id ) {
				return $graph_node;
			}
		}

		return null;
	}
}
