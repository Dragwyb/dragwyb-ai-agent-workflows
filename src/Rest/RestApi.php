<?php
/**
 * REST API bootstrap.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use WorkflowAutomate\Plugin\Core\Container;
use WorkflowAutomate\Plugin\Service\AiModelsService;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;
use WorkflowAutomate\Plugin\Service\WebhookService;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowService;
use WorkflowAutomate\Plugin\Service\WorkflowTestListenerService;

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
			$this->container->get( WorkflowExecutionService::class )
		);
		$workflows_controller->register_routes();

		$node_types_controller = new NodeTypesController( $this->container->get( NodeTypeRegistry::class ) );
		$node_types_controller->register_routes();

		$connections_controller = new ConnectionsController(
			$this->container->get( ConnectionService::class ),
			$this->container->get( AiModelsService::class )
		);
		$connections_controller->register_routes();

		$webhook_ingress_controller = new WebhookIngressController( $this->container->get( WebhookService::class ) );
		$webhook_ingress_controller->register_routes();

		$test_controller = new WorkflowTestController(
			$this->container->get( WorkflowService::class ),
			$this->container->get( WorkflowTestListenerService::class )
		);
		$test_controller->register_routes();
	}
}
