<?php

declare(strict_types=1);

namespace AIAWA\AiProviders\Groq;

use AIAWA\AiProviders\Compatible\AbstractCompatibleApiProvider;

/**
 * Groq AI provider (OpenAI-compatible).
 */
class GroqProvider extends AbstractCompatibleApiProvider {

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return 'https://api.groq.com/openai/v1';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerId(): string {
		return 'groq';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerName(): string {
		return 'Groq';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function apiKeyUrl(): string {
		return 'https://console.groq.com/keys';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function providerDescription(): string {
		return 'Fast inference with Groq LPU cloud models.';
	}
}
