<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Provider;

use DragwybAgentFlow\Plugin\Core\Container;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRunLogRepository;
use DragwybAgentFlow\Plugin\Service\AiModelsService;
use DragwybAgentFlow\Plugin\Service\ChatMessageService;
use DragwybAgentFlow\Plugin\Service\ElementorFormsService;
use DragwybAgentFlow\Plugin\Service\WorkflowService;

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
