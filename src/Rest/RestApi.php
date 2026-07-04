<?php
/**
 * REST API bootstrap.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use WorkflowAutomate\Plugin\Core\Container;
use WorkflowAutomate\Plugin\Service\WorkflowService;

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
		$workflows_controller = new WorkflowsController( $this->container->get( WorkflowService::class ) );
		$workflows_controller->register_routes();
	}
}
