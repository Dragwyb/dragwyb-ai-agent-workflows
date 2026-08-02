<?php
/**
 * Lists AI models via WordPress AI Client provider registry.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

use DragwybAgentFlow\Plugin\Service\Ai\AiClientBootstrap;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads chat-model options from registered AI providers.
 */
class AiModelsService {

	private const CACHE_TTL = 900;

	/**
	 * Node type → dragwyb_af provider slug.
	 *
	 * @var array<string, string>
	 */
	private const NODE_TYPE_PROVIDERS = array(
		'openai_chat_action'             => 'openai',
		'claude_messages_action'         => 'claude',
		'gemini_generate_content_action' => 'gemini',
		'openrouter_chat_action'         => 'openrouter',
		'groq_chat_action'               => 'groq',
		'deepseek_chat_action'           => 'deepseek',
		'ai_agent_action'                => 'openai',
	);

	/**
	 * @param string $provider_or_node dragwyb_af provider slug or node type slug.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null, configured?: bool}
	 */
	public function listForProvider( string $provider_or_node ): array {
		$provider = $this->resolveProvider( $provider_or_node );

		if ( '' === $provider ) {
			return array(
				'options' => array(),
				'error'   => __( 'Unknown AI provider.', 'dragwyb-agentflow' ),
			);
		}

		if ( ! AiClientBootstrap::isAvailable() ) {
			return array(
				'options'    => array(),
				'error'      => __( 'WordPress AI Client is not available.', 'dragwyb-agentflow' ),
				'configured' => false,
			);
		}

		$auth = AiClientBootstrap::ensureProviderAuthentication( $provider );
		if ( is_wp_error( $auth ) ) {
			return array(
				'options'    => array(),
				'error'      => $auth->get_error_message(),
				'configured' => false,
			);
		}

		if ( ! AiClientBootstrap::isProviderConfigured( $provider ) ) {
			return array(
				'options'    => array(),
				'error'      => __( 'No API key configured for this provider. Add one in this node.', 'dragwyb-agentflow' ),
				'configured' => false,
			);
		}

		$provider_id = AiClientBootstrap::resolveProviderId( $provider );
		$cache_key   = 'dragwyb_af_ai_models_' . $provider_id;
		$cached      = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['options'] ) ) {
			return array(
				'options'    => $cached['options'],
				'error'      => $cached['error'] ?? null,
				'configured' => true,
			);
		}

		try {
			$registry   = AiClient::defaultRegistry();
			$class_name = $registry->getProviderClassName( $provider_id );
			$models     = $class_name::modelMetadataDirectory()->listModelMetadata();
			$options    = array();

			foreach ( $models as $model ) {
				$caps    = $model->getSupportedCapabilities();
				$is_text = false;
				foreach ( $caps as $cap ) {
					if ( $cap->isTextGeneration() ) {
						$is_text = true;
						break;
					}
				}
				if ( ! $is_text ) {
					continue;
				}

				$id        = $model->getId();
				$options[] = array(
					'value' => $id,
					'label' => $model->getName() !== '' ? $model->getName() : $id,
				);
			}

			usort(
				$options,
				static function ( array $a, array $b ): int {
					return strcasecmp( $a['label'], $b['label'] );
				}
			);

			$result = array(
				'options' => $options,
				'error'   => empty( $options ) ? __( 'No text-generation models returned by the provider.', 'dragwyb-agentflow' ) : null,
			);

			set_transient( $cache_key, $result, self::CACHE_TTL );

			return array_merge(
				$result,
				array(
					'configured' => true,
				)
			);
		} catch ( \Throwable $e ) {
			return array(
				'options'    => array(),
				'error'      => $e->getMessage(),
				'configured' => true,
			);
		}
	}

	/**
	 * Backward-compatible wrapper used by ConnectionsController.
	 *
	 * @param int    $connection_id Unused (kept for route signature).
	 * @param string $node_type     Action node type slug.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null, configured?: bool}
	 */
	public function listForConnection( int $connection_id, string $node_type ): array {
		unset( $connection_id );

		return $this->listForProvider( $node_type );
	}

	/**
	 * @param string $provider_or_node Provider or node slug.
	 */
	private function resolveProvider( string $provider_or_node ): string {
		$key = strtolower( trim( $provider_or_node ) );

		if ( isset( self::NODE_TYPE_PROVIDERS[ $key ] ) ) {
			return self::NODE_TYPE_PROVIDERS[ $key ];
		}

		if ( isset( AiClientBootstrap::PROVIDER_IDS[ $key ] ) ) {
			return $key;
		}

		return '';
	}
}
