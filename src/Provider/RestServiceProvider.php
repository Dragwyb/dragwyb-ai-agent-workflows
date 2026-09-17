<?php
/**
 * Registers REST endpoints & feature integrations against the container.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Provider;

use DRAGAIW\Plugin\Core\Container;
use DRAGAIW\Plugin\Persistence\WorkflowRunLogRepository;
use DRAGAIW\Plugin\Service\AiModelsService;
use DRAGAIW\Plugin\Service\ChatMessageService;
use DRAGAIW\Plugin\Service\ElementorFormsService;
use DRAGAIW\Plugin\Service\WorkflowService;

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
