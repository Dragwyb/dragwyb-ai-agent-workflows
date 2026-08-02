<?php
/**
 * DeepSeek Chat Completions action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeepSeekChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'deepseek_chat_action';
	}

	public function label(): string {
		return __( 'DeepSeek Chat', 'ai-agent-workflow-automation' );
	}

	public function description(): string {
		return __( 'Sends a prompt to DeepSeek and returns the reply.', 'ai-agent-workflow-automation' );
	}

	protected function providerSlug(): string {
		return 'deepseek';
	}

	protected function defaultModel(): string {
		return 'deepseek-chat';
	}
}
