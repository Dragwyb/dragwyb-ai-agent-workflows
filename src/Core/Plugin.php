<?php
/**
 * Main plugin bootstrap class.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

use WorkflowAutomate\Plugin\Database\MigrationRunner;
use WorkflowAutomate\Plugin\Database\SchemaMigrations;
use WorkflowAutomate\Plugin\Persistence\WorkflowNodeRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;
use WorkflowAutomate\Plugin\Rest\RestApi;
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
		( new RestApi( $this->container ) )->register();

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
			WorkflowService::class,
			static function ( Container $container ): WorkflowService {
				return new WorkflowService(
					$container->get( WorkflowRepository::class ),
					$container->get( WorkflowNodeRepository::class )
				);
			}
		);
	}
}
