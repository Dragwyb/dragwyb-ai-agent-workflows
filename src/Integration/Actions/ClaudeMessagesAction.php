<?php
/**
 * Anthropic Claude Messages API action.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a prompt via WordPress AI Client (Anthropic provider).
 */
class ClaudeMessagesAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'claude_messages_action';
	}

	public function label(): string {
		return __( 'Anthropic Claude', 'dragwyb-visual-automation' );
	}

	public function description(): string {
		return __( 'Sends a prompt to Anthropic Claude and returns the reply.', 'dragwyb-visual-automation' );
	}

	protected function providerSlug(): string {
		return 'claude';
	}

	protected function defaultModel(): string {
		return 'claude-sonnet-4-5';
	}
}
