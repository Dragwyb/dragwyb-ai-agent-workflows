<?php
/**
 * DeepSeek Chat Completions action.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeepSeekChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'deepseek_chat_action';
	}

	public function label(): string {
		return __( 'DeepSeek Chat', 'dragwyb-agentflow' );
	}

	public function description(): string {
		return __( 'Sends a prompt to DeepSeek and returns the reply.', 'dragwyb-agentflow' );
	}

	protected function providerSlug(): string {
		return 'deepseek';
	}

	protected function defaultModel(): string {
		return 'deepseek-chat';
	}
}
