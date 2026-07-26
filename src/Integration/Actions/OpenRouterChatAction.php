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

class OpenRouterChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'openrouter_chat_action';
	}

	public function label(): string {
		return __( 'OpenRouter Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to OpenRouter and returns the reply.', 'workflow-automate' );
	}

	protected function providerSlug(): string {
		return 'openrouter';
	}

	protected function defaultModel(): string {
		return 'openai/gpt-4o-mini';
	}
}
