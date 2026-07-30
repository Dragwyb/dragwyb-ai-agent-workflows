<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Provider;

use AIAWAB\Plugin\Core\Container;
use AIAWAB\Plugin\Persistence\WorkflowRunLogRepository;
use AIAWAB\Plugin\Service\AiModelsService;
use AIAWAB\Plugin\Service\ChatMessageService;
use AIAWAB\Plugin\Service\ElementorFormsService;
use AIAWAB\Plugin\Service\WorkflowService;

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
