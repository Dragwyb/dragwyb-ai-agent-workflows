<?php
/**
 * Registers persistence repositories against the container.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Provider;

use AIAWAB\Plugin\Core\Container;
use AIAWAB\Plugin\Persistence\ConnectionRepository;
use AIAWAB\Plugin\Persistence\WebhookRepository;
use AIAWAB\Plugin\Persistence\WorkflowNodeRepository;
use AIAWAB\Plugin\Persistence\WorkflowRepository;
use AIAWAB\Plugin\Persistence\WorkflowRunLogRepository;
use AIAWAB\Plugin\Persistence\WorkflowRunRepository;

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
