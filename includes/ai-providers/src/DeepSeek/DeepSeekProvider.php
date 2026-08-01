<?php

declare(strict_types=1);

namespace AIAWA\AiProviders\DeepSeek;

use AIAWA\AiProviders\Compatible\AbstractCompatibleApiProvider;

/**
 * DeepSeek AI provider (OpenAI-compatible).
 */
class DeepSeekProvider extends AbstractCompatibleApiProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://api.deepseek.com';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'deepseek';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'DeepSeek';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function apiKeyUrl(): string {
		return 'https://platform.deepseek.com/api_keys';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerDescription(): string {
		return 'Text generation with DeepSeek models.';
	}
}
