<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Provider;

use WorkflowAutomate\Plugin\Core\Container;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Service\AiModelsService;
use WorkflowAutomate\Plugin\Service\ChatMessageService;
use WorkflowAutomate\Plugin\Service\ElementorFormsService;
use WorkflowAutomate\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds REST support services.
 */
final class RestServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			AiModelsService::class,
			static function (): AiModelsService {
				return new AiModelsService();
			}
		);

		$container->singleton(
			ElementorFormsService::class,
			static function (): ElementorFormsService {
				return new ElementorFormsService();
			}
		);

		$container->singleton(
			ChatMessageService::class,
			static function ( Container $container ): ChatMessageService {
				return new ChatMessageService(
					$container->get( WorkflowService::class ),
					$container->get( WorkflowRunLogRepository::class )
				);
			}
		);
	}
}
