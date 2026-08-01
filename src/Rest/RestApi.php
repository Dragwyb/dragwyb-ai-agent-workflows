<?php
/**
 * REST API bootstrap.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Rest;

use AIAWA\Plugin\Core\Container;
use AIAWA\Plugin\Service\AiModelsService;
use AIAWA\Plugin\Service\ChatMessageService;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\ElementorFormsService;
use AIAWA\Plugin\Service\GoogleOAuthService;
use AIAWA\Plugin\Service\NodeTypeRegistry;
use AIAWA\Plugin\Service\WebhookService;
use AIAWA\Plugin\Service\WorkflowExecutionService;
use AIAWA\Plugin\Service\WorkflowService;
use AIAWA\Plugin\Service\WorkflowNodeTestService;
use AIAWA\Plugin\Service\WorkflowTestListenerService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every REST controller the plugin exposes.
 *
 * Deliberately separate from Plugin::registerServices() even though both
 * touch the container: this class owns exactly one responsibility (wiring
 * REST controllers to `rest_api_init`), so it can grow to register more
 * controllers in later roadmap increments without Plugin.php changing.
 */
class RestApi {

	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Hooks route registration into `rest_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Instantiates and registers every REST controller.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		$workflows_controller = new WorkflowsController(
			$this->container->get( WorkflowService::class ),
			$this->container->get( WorkflowExecutionService::class ),
			$this->container->get( ChatMessageService::class )
		);
		$workflows_controller->register_routes();

		$node_types_controller = new NodeTypesController(
			$this->container->get( NodeTypeRegistry::class ),
			$this->container->get( ElementorFormsService::class )
		);
		$node_types_controller->register_routes();

		$connections_controller = new ConnectionsController(
			$this->container->get( ConnectionService::class ),
			$this->container->get( AiModelsService::class ),
			$this->container->get( GoogleOAuthService::class )
		);
		$connections_controller->register_routes();

		$ai_providers_controller = new AiProvidersController(
			$this->container->get( AiModelsService::class )
		);
		$ai_providers_controller->register_routes();

		$webhook_ingress_controller = new WebhookIngressController( $this->container->get( WebhookService::class ) );
		$webhook_ingress_controller->register_routes();

		$chat_ingress_controller = new ChatMessageIngressController(
			$this->container->get( ChatMessageService::class ),
			$this->container->get( WorkflowExecutionService::class ),
			$this->container->get( WorkflowTestListenerService::class )
		);
		$chat_ingress_controller->register_routes();

		$test_controller = new WorkflowTestController(
			$this->container->get( WorkflowService::class ),
			$this->container->get( WorkflowTestListenerService::class ),
			$this->container->get( WorkflowNodeTestService::class )
		);
		$test_controller->register_routes();

		$google_oauth_callback = new GoogleOAuthCallbackController(
			$this->container->get( ConnectionService::class ),
			$this->container->get( GoogleOAuthService::class )
		);
		$google_oauth_callback->register_routes();
	}
}
