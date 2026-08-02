<?php
/**
 * Shared AI chat action backed by WordPress AI Client.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;
use AIAWA\Plugin\Service\Agent\AgentAiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base for OpenAI / Claude / Gemini / OpenRouter / Groq / DeepSeek chat nodes.
 */
abstract class AbstractAiClientChatAction implements ActionInterface {

	private AgentAiClient $ai_client;

	public function __construct( AgentAiClient $ai_client ) {
		$this->ai_client = $ai_client;
	}

	abstract public function slug(): string;

	abstract public function label(): string;

	abstract public function description(): string;

	/**
	 * aiawa provider slug (openai|claude|gemini|openrouter|groq|deepseek).
	 */
	abstract protected function providerSlug(): string;

	abstract protected function defaultModel(): string;

	public function configSchema(): array {
		return array(
			'api_credentials' => array(
				'type'     => 'ai_credentials',
				'label'    => __( 'API key', 'ai-agent-workflow-automation' ),
				'provider' => $this->providerSlug(),
			),
			'model'           => array(
				'type'           => 'dynamic_select',
				'label'          => __( 'Model', 'ai-agent-workflow-automation' ),
				'default'        => $this->defaultModel(),
				'options_source' => 'ai_models',
				'provider_field' => 'provider',
				'provider'       => $this->providerSlug(),
			),
			'system_prompt'   => array(
				'type'    => 'string',
				'label'   => __( 'System prompt (optional)', 'ai-agent-workflow-automation' ),
				'default' => '',
			),
			'prompt'          => array(
				'type'     => 'string',
				'label'    => __( 'User prompt (supports {{trigger.fields.field_id}} tokens)', 'ai-agent-workflow-automation' ),
				'required' => true,
			),
		);
	}

	public function execute( array $config, array $context ): array {
		unset( $context );

		$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

		if ( '' === $prompt ) {
			return array(
				'success' => false,
				'error'   => __( 'No prompt configured.', 'ai-agent-workflow-automation' ),
			);
		}

		$model = isset( $config['model'] ) ? trim( (string) $config['model'] ) : $this->defaultModel();
		if ( '' === $model ) {
			$model = $this->defaultModel();
		}

		$system_prompt = isset( $config['system_prompt'] ) ? trim( (string) $config['system_prompt'] ) : '';

		$result = $this->ai_client->completeSimple(
			$this->providerSlug(),
			$model,
			$prompt,
			$system_prompt
		);

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'model'   => $model,
			'content' => (string) ( $result['content'] ?? '' ),
		);
	}
}
