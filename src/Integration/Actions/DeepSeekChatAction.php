<?php
/**
 * DeepSeek Chat Completions action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeepSeekChatAction extends AbstractOpenAiCompatibleChatAction {

	public function slug(): string {
		return 'deepseek_chat_action';
	}

	public function label(): string {
		return __( 'DeepSeek Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to DeepSeek (OpenAI-compatible API) and returns the reply.', 'workflow-automate' );
	}

	protected function apiUrl(): string {
		return 'https://api.deepseek.com/chat/completions';
	}

	protected function defaultModel(): string {
		return 'deepseek-chat';
	}

	protected function providerName(): string {
		return 'DeepSeek';
	}

	protected function connectionLabel(): string {
		return __( 'DeepSeek API connection', 'workflow-automate' );
	}
}
