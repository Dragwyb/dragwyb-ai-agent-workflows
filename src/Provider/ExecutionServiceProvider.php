<?php
/**
 * Registers workflow execution services against the container.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Provider;

use DRAGAIW\Plugin\Core\Container;
use DRAGAIW\Plugin\Service\NodeTypeRegistry;
use DRAGAIW\Plugin\Persistence\WebhookRepository;
use DRAGAIW\Plugin\Persistence\WorkflowNodeRepository;
use DRAGAIW\Plugin\Persistence\WorkflowRepository;
use DRAGAIW\Plugin\Persistence\WorkflowRunLogRepository;
use DRAGAIW\Plugin\Persistence\WorkflowRunRepository;
use DRAGAIW\Plugin\Service\Agent\AgentAiClient;
use DRAGAIW\Plugin\Service\Agent\AgentService;
use DRAGAIW\Plugin\Service\Agent\AgentToolExecutor;
use DRAGAIW\Plugin\Service\Agent\AgentToolSchemaBuilder;
use DRAGAIW\Plugin\Service\BackgroundRunner;
use DRAGAIW\Plugin\Service\NodeExecutionService;
use DRAGAIW\Plugin\Service\RunRetentionService;
use DRAGAIW\Plugin\Service\SettingsService;
use DRAGAIW\Plugin\Service\TriggerReentrancyGuard;
use DRAGAIW\Plugin\Service\WebhookService;
use DRAGAIW\Plugin\Service\WorkflowExecutionService;
use DRAGAIW\Plugin\Service\WorkflowNodeTestService;
use DRAGAIW\Plugin\Service\WorkflowService;
use DRAGAIW\Plugin\Service\WorkflowTestListenerService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds execution, node, agent, background, and trigger services into the container.
 */
final class ExecutionServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			WorkflowService::class,
			static function ( Container $container ): WorkflowService {
				return new WorkflowService(
					$container->get( WorkflowRepository::class ),
					$container->get( WorkflowNodeRepository::class ),
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowRunLogRepository::class ),
					$container->get( WebhookRepository::class )
				);
			}
		);

		$container->singleton(
			NodeExecutionService::class,
			static function ( Container $container ): NodeExecutionService {
				return new NodeExecutionService( $container->get( NodeTypeRegistry::class ) );
			}
		);

		$container->singleton(
			AgentAiClient::class,
			static function (): AgentAiClient {
				return new AgentAiClient();
			}
		);

		$container->singleton(
			AgentToolSchemaBuilder::class,
			static function ( Container $container ): AgentToolSchemaBuilder {
				return new AgentToolSchemaBuilder( $container->get( NodeTypeRegistry::class ) );
			}
		);

		$container->singleton(
			AgentToolExecutor::class,
			static function ( Container $container ): AgentToolExecutor {
				return new AgentToolExecutor( $container->get( NodeExecutionService::class ) );
			}
		);

		$container->singleton(
			AgentService::class,
			static function ( Container $container ): AgentService {
				return new AgentService(
					$container->get( AgentToolSchemaBuilder::class ),
					$container->get( AgentToolExecutor::class ),
					$container->get( AgentAiClient::class )
				);
			}
		);

		$container->singleton(
			TriggerReentrancyGuard::class,
			static function (): TriggerReentrancyGuard {
				$guard = new TriggerReentrancyGuard();
				TriggerReentrancyGuard::bindInstance( $guard );

				return $guard;
			}
		);

		// Resolve immediately so WordPress action writes can suppress triggers
		// even before the execution engine is first pulled from the container.
		$container->get( TriggerReentrancyGuard::class );

		$container->singleton(
			WorkflowTestListenerService::class,
			static function ( Container $container ): WorkflowTestListenerService {
				return new WorkflowTestListenerService( $container->get( WorkflowService::class ) );
			}
		);

		$container->singleton(
			WorkflowNodeTestService::class,
			static function ( Container $container ): WorkflowNodeTestService {
				return new WorkflowNodeTestService(
					$container->get( WorkflowService::class ),
					$container->get( NodeExecutionService::class ),
					$container->get( NodeTypeRegistry::class ),
					$container->get( WorkflowTestListenerService::class )
				);
			}
		);

		$container->singleton(
			WorkflowExecutionService::class,
			static function ( Container $container ): WorkflowExecutionService {
				return new WorkflowExecutionService(
					$container->get( WorkflowService::class ),
					$container->get( NodeTypeRegistry::class ),
					$container->get( NodeExecutionService::class ),
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowRunLogRepository::class ),
					$container->get( SettingsService::class ),
					$container->get( TriggerReentrancyGuard::class )
				);
			}
		);

		$container->singleton(
			BackgroundRunner::class,
			static function ( Container $container ): BackgroundRunner {
				return new BackgroundRunner(
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowExecutionService::class )
				);
			}
		);

		$container->singleton(
			RunRetentionService::class,
			static function ( Container $container ): RunRetentionService {
				return new RunRetentionService(
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowRunLogRepository::class ),
					$container->get( SettingsService::class )
				);
			}
		);

		$container->singleton(
			WebhookService::class,
			static function ( Container $container ): WebhookService {
				return new WebhookService(
					$container->get( WebhookRepository::class ),
					$container->get( WorkflowService::class ),
					$container->get( WorkflowExecutionService::class ),
					$container->get( SettingsService::class )
				);
			}
		);
	}
}
