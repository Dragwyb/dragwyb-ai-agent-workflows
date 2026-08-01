<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Provider;

use AIAWA\Plugin\Core\Container;
use AIAWA\Plugin\Persistence\WorkflowRunLogRepository;
use AIAWA\Plugin\Service\AiModelsService;
use AIAWA\Plugin\Service\ChatMessageService;
use AIAWA\Plugin\Service\ElementorFormsService;
use AIAWA\Plugin\Service\WorkflowService;

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
