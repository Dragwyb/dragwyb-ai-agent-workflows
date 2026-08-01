<?php
/**
 * Registers persistence repositories against the container.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Provider;

use AIAWA\Plugin\Core\Container;
use AIAWA\Plugin\Persistence\ConnectionRepository;
use AIAWA\Plugin\Persistence\WebhookRepository;
use AIAWA\Plugin\Persistence\WorkflowNodeRepository;
use AIAWA\Plugin\Persistence\WorkflowRepository;
use AIAWA\Plugin\Persistence\WorkflowRunLogRepository;
use AIAWA\Plugin\Persistence\WorkflowRunRepository;

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
