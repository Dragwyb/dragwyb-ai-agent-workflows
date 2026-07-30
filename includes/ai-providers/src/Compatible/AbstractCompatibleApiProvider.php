<?php

declare(strict_types=1);

namespace AIAWAB\AiProviders\Compatible;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Shared base for OpenAI-compatible chat providers (OpenRouter, Groq, DeepSeek).
 */
abstract class AbstractCompatibleApiProvider extends AbstractApiProvider {

	/**
	 * Provider id used by AiClient / Connectors (e.g. openrouter).
	 */
	abstract protected static function providerId(): string;

	/**
	 * Human-readable provider name.
	 */
	abstract protected static function providerName(): string;

	/**
	 * URL where users create API keys.
	 */
	abstract protected static function apiKeyUrl(): string;

	/**
	 * Short description for Connectors UI.
	 */
	abstract protected static function providerDescription(): string;

	/**
	 * {@inheritDoc}
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new CompatibleTextGenerationModel( $modelMetadata, $providerMetadata, static::class );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$args = array(
			static::providerId(),
			static::providerName(),
			ProviderTypeEnum::cloud(),
			static::apiKeyUrl(),
			RequestAuthenticationMethod::apiKey(),
		);

		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$args[] = static::providerDescription();
		}

		return new ProviderMetadata( ...$args );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new CompatibleModelMetadataDirectory( static::class );
	}
}
