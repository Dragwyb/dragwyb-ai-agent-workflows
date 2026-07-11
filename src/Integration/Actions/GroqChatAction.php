<?php
/**
 * Groq Chat Completions action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GroqChatAction extends AbstractOpenAiCompatibleChatAction {

	public function slug(): string {
		return 'groq_chat_action';
	}

	public function label(): string {
		return __( 'Groq Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to Groq (OpenAI-compatible API) and returns the reply.', 'workflow-automate' );
	}

	protected function apiUrl(): string {
		return 'https://api.groq.com/openai/v1/chat/completions';
	}

	protected function defaultModel(): string {
		return 'llama-3.3-70b-versatile';
	}

	protected function providerName(): string {
		return 'Groq';
	}

	protected function connectionLabel(): string {
		return __( 'Groq API connection', 'workflow-automate' );
	}
}
