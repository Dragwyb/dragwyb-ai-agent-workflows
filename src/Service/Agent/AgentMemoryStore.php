<?php
/**
 * Transient-backed conversation memory for AI Agent nodes.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service\Agent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and retrieves normalized chat messages for an agent memory attachment.
 */
class AgentMemoryStore {

	private const MAX_MESSAGES = 5;

	private const EXPIRATION = DAY_IN_SECONDS;

	private string $storage_key;

	private int $max_messages;

	/**
	 * @param string               $agent_id    Agent client node id.
	 * @param string               $memory_id   Memory attachment client node id.
	 * @param array<string, mixed> $memory_config Memory node config.
	 * @param string               $session_key Optional user/session discriminator.
	 */
	public function __construct(
		string $agent_id,
		string $memory_id,
		array $memory_config = array(),
		string $session_key = ''
	) {
		$this->max_messages = self::MAX_MESSAGES;

		if ( isset( $memory_config['context_length'] ) && is_numeric( $memory_config['context_length'] ) ) {
			$this->max_messages = max( 1, (int) $memory_config['context_length'] );
		}

		$key_suffix = '' !== $session_key ? $session_key . '_' : '';

		$this->storage_key = 'wfa_agent_mem_' . md5( $agent_id . '_' . $memory_id . '_' . $key_suffix );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function retrieve(): array {
		$messages = get_transient( $this->storage_key );

		return is_array( $messages ) ? $messages : array();
	}

	/**
	 * @return bool
	 */
	public function exists(): bool {
		return false !== get_transient( $this->storage_key );
	}

	/**
	 * @param array<int, array<string, mixed>> $messages Normalized messages.
	 *
	 * @return bool
	 */
	public function store( array $messages ): bool {
		if ( count( $messages ) > $this->max_messages ) {
			$system_message = null;
			$other_messages = array();

			foreach ( $messages as $message ) {
				if ( is_array( $message ) && 'system' === ( $message['role'] ?? '' ) ) {
					$system_message = $message;
				} else {
					$other_messages[] = $message;
				}
			}

			$keep           = $this->max_messages - ( null !== $system_message ? 1 : 0 );
			$other_messages = array_slice( $other_messages, -1 * max( 1, $keep ) );
			$messages       = null !== $system_message ? array_merge( array( $system_message ), $other_messages ) : $other_messages;
		}

		return set_transient( $this->storage_key, $messages, self::EXPIRATION );
	}
}
