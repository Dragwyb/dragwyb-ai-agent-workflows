<?php
/**
 * Groq Chat Completions action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GroqChatAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'groq_chat_action';
	}

	public function label(): string {
		return __( 'Groq Chat', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Sends a prompt to Groq and returns the reply.', 'workflow-automate' );
	}

	protected function providerSlug(): string {
		return 'groq';
	}

	protected function defaultModel(): string {
		return 'llama-3.3-70b-versatile';
	}
}
