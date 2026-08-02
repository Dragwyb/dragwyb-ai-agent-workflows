<?php
/**
 * Registers persistence repositories against the container.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Provider;

use DragwybAgentFlow\Plugin\Core\Container;
use DragwybAgentFlow\Plugin\Persistence\ConnectionRepository;
use DragwybAgentFlow\Plugin\Persistence\WebhookRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowNodeRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRunLogRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRunRepository;

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
