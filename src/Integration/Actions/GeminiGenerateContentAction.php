<?php
/**
 * Google Gemini generateContent action.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a prompt via WordPress AI Client (Google provider).
 */
class GeminiGenerateContentAction extends AbstractAiClientChatAction {

	public function slug(): string {
		return 'gemini_generate_content_action';
	}

	public function label(): string {
		return __( 'Google Gemini', 'dragwyb-visual-automation' );
	}

	public function description(): string {
		return __( 'Sends a prompt to Google Gemini and returns the reply.', 'dragwyb-visual-automation' );
	}

	protected function providerSlug(): string {
		return 'gemini';
	}

	protected function defaultModel(): string {
		return 'gemini-2.5-flash';
	}
}
