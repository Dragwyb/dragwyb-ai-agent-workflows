<?php

declare(strict_types=1);

namespace WorkflowAutomate\AiProviders\OpenRouter;

use WorkflowAutomate\AiProviders\Compatible\AbstractCompatibleApiProvider;

/**
 * OpenRouter AI provider (OpenAI-compatible).
 */
class OpenRouterProvider extends AbstractCompatibleApiProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://openrouter.ai/api/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'openrouter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'OpenRouter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function apiKeyUrl(): string {
		return 'https://openrouter.ai/keys';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerDescription(): string {
		return 'Unified access to many LLM providers via OpenRouter.';
	}
}
