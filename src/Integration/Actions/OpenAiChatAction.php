<?php
/**
 * OpenAI Chat Completions action.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a chat completion via WordPress AI Client (OpenAI provider).
 */
class OpenAiChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'openai_chat_action';
	}

	public function label(): string {
		return __( 'OpenAI Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to OpenAI and returns the reply.', 'workflow-automate' );
	}

	protected function providerSlug(): string {
		return 'openai';
	}

	protected function defaultModel(): string {
		return 'gpt-4o-mini';
	}
}
