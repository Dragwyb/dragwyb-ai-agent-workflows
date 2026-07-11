<?php
/**
 * OpenRouter Chat Completions action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenRouterChatAction extends AbstractOpenAiCompatibleChatAction {

	public function slug(): string {
		return 'openrouter_chat_action';
	}

	public function label(): string {
		return __( 'OpenRouter Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to OpenRouter (OpenAI-compatible API) and returns the reply.', 'workflow-automate' );
	}

	protected function apiUrl(): string {
		return 'https://openrouter.ai/api/v1/chat/completions';
	}

	protected function defaultModel(): string {
		return 'openai/gpt-4o-mini';
	}

	protected function providerName(): string {
		return 'OpenRouter';
	}

	protected function connectionLabel(): string {
		return __( 'OpenRouter API connection', 'workflow-automate' );
	}

	protected function extraHeaders(): array {
		return array(
			'HTTP-Referer' => home_url( '/' ),
			'X-Title' => get_bloginfo( 'name' ) ?: 'Workflow Automate',
		);
	}
}
