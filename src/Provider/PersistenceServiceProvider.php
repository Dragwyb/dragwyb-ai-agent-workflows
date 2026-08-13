<?php
/**
 * Registers persistence repositories against the container.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Provider;

use DragwybVisualAutomation\Plugin\Core\Container;
use DragwybVisualAutomation\Plugin\Persistence\ConnectionRepository;
use DragwybVisualAutomation\Plugin\Persistence\WebhookRepository;
use DragwybVisualAutomation\Plugin\Persistence\WorkflowNodeRepository;
use DragwybVisualAutomation\Plugin\Persistence\WorkflowRepository;
use DragwybVisualAutomation\Plugin\Persistence\WorkflowRunLogRepository;
use DragwybVisualAutomation\Plugin\Persistence\WorkflowRunRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds database repositories as container singletons.
 */
final class PersistenceServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			WorkflowRepository::class,
			static function (): WorkflowRepository {
				return new WorkflowRepository();
			}
		);

		$container->singleton(
			WorkflowNodeRepository::class,
			static function (): WorkflowNodeRepository {
				return new WorkflowNodeRepository();
			}
		);

		$container->singleton(
			WorkflowRunRepository::class,
			static function (): WorkflowRunRepository {
				return new WorkflowRunRepository();
			}
		);

		$container->singleton(
			WorkflowRunLogRepository::class,
			static function (): WorkflowRunLogRepository {
				return new WorkflowRunLogRepository();
			}
		);

		$container->singleton(
			WebhookRepository::class,
			static function (): WebhookRepository {
				return new WebhookRepository();
			}
		);

		$container->singleton(
			ConnectionRepository::class,
			static function (): ConnectionRepository {
				return new ConnectionRepository();
			}
		);
	}
}
