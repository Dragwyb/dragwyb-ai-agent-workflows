<?php

declare(strict_types=1);

namespace AIAWAB\AiProviders\Compatible;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Lists models for an OpenAI-compatible provider and marks them as text-generation capable.
 *
 * @phpstan-param class-string<AbstractCompatibleApiProvider> $provider_class
 */
class CompatibleModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * @var class-string<AbstractCompatibleApiProvider>
	 */
	private string $provider_class;

	/**
	 * @param class-string<AbstractCompatibleApiProvider> $provider_class Provider class.
	 */
	public function __construct( string $provider_class ) {
		$this->provider_class = $provider_class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$provider_class = $this->provider_class;

		return new Request(
			$method,
			$provider_class::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$response_data = $response->getData();
		if ( ! isset( $response_data['data'] ) || ! $response_data['data'] ) {
			$provider_class = $this->provider_class;
			throw ResponseException::fromMissingData( $provider_class::metadata()->getName(), 'data' );
		}

		$capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models = array();
		foreach ( (array) $response_data['data'] as $model_data ) {
			if ( ! is_array( $model_data ) || empty( $model_data['id'] ) || ! is_string( $model_data['id'] ) ) {
				continue;
			}

			$model_id = $model_data['id'];
			$models[] = new ModelMetadata( $model_id, $model_id, $capabilities, $options );
		}

		return $models;
	}
}
