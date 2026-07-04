<?php
/**
 * Main plugin bootstrap class.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

use WorkflowAutomate\Plugin\Admin\Menu;
use WorkflowAutomate\Plugin\Admin\Pages\BuilderPage;
use WorkflowAutomate\Plugin\Admin\Pages\WorkflowsPage;
use WorkflowAutomate\Plugin\Admin\WorkflowActionsController;
use WorkflowAutomate\Plugin\Database\MigrationRunner;
use WorkflowAutomate\Plugin\Database\SchemaMigrations;
use WorkflowAutomate\Plugin\Integration\BuiltInNodeTypes;
use WorkflowAutomate\Plugin\Integration\WorkflowTriggerBinder;
use WorkflowAutomate\Plugin\Persistence\WorkflowNodeRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Rest\RestApi;
use WorkflowAutomate\Plugin\Service\NodeExecutionService;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin on `plugins_loaded`.
 *
 * Deliberately thin: this class wires the requirements check, registers
 * service bindings against the container, defensively keeps the database
 * schema up to date for already-active installs, and fires an extensibility
 * hook. Feature providers (REST API, admin UI, execution engine, etc.) each
 * own their own registration logic in their own class; this class only
 * delegates a single call to each, so it never grows into a "God" class.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Private constructor; use instance() instead.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Retrieves the singleton instance, creating it if necessary.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the `plugins_loaded` callback that performs the real boot.
	 *
	 * Safe to call multiple times; only the first call has any effect.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Runs on `plugins_loaded`. Verifies requirements and fires the loaded hook.
	 *
	 * @return void
	 */
	public function load(): void {
		$requirements = Requirements::check();

		if ( is_wp_error( $requirements ) ) {
			add_action(
				'admin_notices',
				static function () use ( $requirements ) {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( implode( ' ', $requirements->get_error_messages() ) )
					);
				}
			);

			return;
		}

		$this->registerServices();
		$this->registerNodeTypes();
		$this->registerExecutionEngine();
		( new RestApi( $this->container ) )->register();
		$this->registerAdmin();

		// Capability-gated so schema upkeep never runs on ordinary front-end
		// requests; this only protects against tables missing after a
		// manual file update that skipped the activation hook.
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			( new MigrationRunner( SchemaMigrations::all() ) )->run();
		}

		/**
		 * Fires once the plugin has finished loading and confirmed its
		 * environment requirements are met.
		 *
		 * @since 0.1.0
		 *
		 * @param Container $container The plugin's service container.
		 */
		do_action( 'wfa/loaded', $this->container );
	}

	/**
	 * Retrieves the plugin's service container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Registers repository and service bindings against the container.
	 *
	 * @return void
	 */
	private function registerServices(): void {
		$this->container->singleton(
			WorkflowRepository::class,
			static function (): WorkflowRepository {
				return new WorkflowRepository();
			}
		);

		$this->container->singleton(
			WorkflowNodeRepository::class,
			static function (): WorkflowNodeRepository {
				return new WorkflowNodeRepository();
			}
		);

		$this->container->singleton(
			WorkflowRunRepository::class,
			static function (): WorkflowRunRepository {
				return new WorkflowRunRepository();
			}
		);

		$this->container->singleton(
			WorkflowRunLogRepository::class,
			static function (): WorkflowRunLogRepository {
				return new WorkflowRunLogRepository();
			}
		);

		$this->container->singleton(
			WorkflowService::class,
			static function ( Container $container ): WorkflowService {
				return new WorkflowService(
					$container->get( WorkflowRepository::class ),
					$container->get( WorkflowNodeRepository::class ),
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowRunLogRepository::class )
				);
			}
		);

		$this->container->singleton(
			NodeExecutionService::class,
			static function ( Container $container ): NodeExecutionService {
				return new NodeExecutionService( $container->get( NodeTypeRegistry::class ) );
			}
		);

		$this->container->singleton(
			WorkflowExecutionService::class,
			static function ( Container $container ): WorkflowExecutionService {
				return new WorkflowExecutionService(
					$container->get( WorkflowService::class ),
					$container->get( NodeTypeRegistry::class ),
					$container->get( NodeExecutionService::class ),
					$container->get( WorkflowRunRepository::class ),
					$container->get( WorkflowRunLogRepository::class )
				);
			}
		);
	}

	/**
	 * Registers the node type registry and, on `init`, fires the extension
	 * point that populates it.
	 *
	 * The `wfa/nodes/register` action is deliberately fired on `init` rather
	 * than directly from here (this method itself runs during our own
	 * `plugins_loaded` callback): by `init`, every other plugin's
	 * `plugins_loaded` callback has already run, so third-party code hooking
	 * `wfa/nodes/register` from inside its own `plugins_loaded` handler is
	 * guaranteed to have registered before this fires. Firing immediately
	 * here would make that depend on plugin load order.
	 *
	 * @return void
	 */
	private function registerNodeTypes(): void {
		$this->container->singleton(
			NodeTypeRegistry::class,
			static function (): NodeTypeRegistry {
				return new NodeTypeRegistry();
			}
		);

		add_action( 'wfa/nodes/register', array( BuiltInNodeTypes::class, 'register' ) );

		add_action(
			'init',
			function (): void {
				/**
				 * Fires once, letting core and third-party code register
				 * trigger and action node types into the registry.
				 *
				 * @since 0.1.0
				 *
				 * @param NodeTypeRegistry $registry The plugin's node type registry.
				 */
				do_action( 'wfa/nodes/register', $this->container->get( NodeTypeRegistry::class ) );
			}
		);
	}

	/**
	 * Binds every active workflow's trigger(s) to their real event source,
	 * so live events actually start a run (see WorkflowTriggerBinder).
	 *
	 * Deliberately runs on `init` at priority 20, after registerNodeTypes()'s
	 * own `init` callback (default priority 10, see its docblock) has
	 * populated the node type registry — binding needs to look up each
	 * node's type in that registry, so it must run strictly after.
	 *
	 * @return void
	 */
	private function registerExecutionEngine(): void {
		add_action(
			'init',
			function (): void {
				$binder = new WorkflowTriggerBinder(
					$this->container->get( WorkflowService::class ),
					$this->container->get( NodeTypeRegistry::class ),
					$this->container->get( WorkflowExecutionService::class )
				);

				$binder->bindActiveWorkflows();
			},
			20
		);
	}

	/**
	 * Registers the admin menu/screens and the admin-post action handler
	 * that backs their row actions.
	 *
	 * @return void
	 */
	private function registerAdmin(): void {
		$workflows = $this->container->get( WorkflowService::class );
		$workflows_page = new WorkflowsPage( $workflows );
		$builder_page = new BuilderPage();

		( new Menu( array( $workflows_page, $builder_page ) ) )->register();
		( new WorkflowActionsController( $workflows, $workflows_page->slug() ) )->register();
	}
}
