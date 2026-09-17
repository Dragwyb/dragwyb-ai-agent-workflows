<?php
/**
 * OpenRouter Chat Completions action.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenRouterChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'openrouter_chat_action';
	}

	public function label(): string {
		return __( 'OpenRouter Chat', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Sends a prompt to OpenRouter and returns the reply.', 'dragwyb-ai-agent-workflows' );
	}

	protected function providerSlug(): string {
		return 'openrouter';
	}

	protected function defaultModel(): string {
		return 'openai/gpt-4o-mini';
	}
}
