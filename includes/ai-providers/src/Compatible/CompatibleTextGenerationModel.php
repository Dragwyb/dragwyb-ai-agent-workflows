<?php

declare(strict_types=1);

namespace WorkflowAutomate\AiProviders\Compatible;

use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * OpenAI-compatible chat completions model bound to a concrete provider class.
 *
 * @phpstan-param class-string<AbstractCompatibleApiProvider> $provider_class
 */
class CompatibleTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * @var class-string<AbstractCompatibleApiProvider>
	 */
	private string $provider_class;

	/**
	 * @param ModelMetadata                               $metadata          Model metadata.
	 * @param ProviderMetadata                            $provider_metadata Provider metadata.
	 * @param class-string<AbstractCompatibleApiProvider> $provider_class    Provider class.
	 */
	public function __construct( ModelMetadata $metadata, ProviderMetadata $provider_metadata, string $provider_class ) {
		parent::__construct( $metadata, $provider_metadata );
		$this->provider_class = $provider_class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$provider_class = $this->provider_class;

		if ( 'openrouter' === $provider_class::metadata()->getId() ) {
			$headers['HTTP-Referer'] = function_exists( 'home_url' ) ? home_url( '/' ) : 'https://wordpress.org';
			$headers['X-Title']      = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'WordPress';
		}

		return new Request(
			$method,
			$provider_class::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
