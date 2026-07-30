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

class DeepSeekChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'deepseek_chat_action';
	}

	public function label(): string {
		return __( 'DeepSeek Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to DeepSeek and returns the reply.', 'workflow-automate' );
	}

	protected function providerSlug(): string {
		return 'deepseek';
	}

	protected function defaultModel(): string {
		return 'deepseek-chat';
	}
}
