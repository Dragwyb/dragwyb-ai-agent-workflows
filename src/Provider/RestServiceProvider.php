<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Provider;

use DragwybVisualAutomation\Plugin\Core\Container;
use DragwybVisualAutomation\Plugin\Persistence\WorkflowRunLogRepository;
use DragwybVisualAutomation\Plugin\Service\AiModelsService;
use DragwybVisualAutomation\Plugin\Service\ChatMessageService;
use DragwybVisualAutomation\Plugin\Service\ElementorFormsService;
use DragwybVisualAutomation\Plugin\Service\WorkflowService;

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
