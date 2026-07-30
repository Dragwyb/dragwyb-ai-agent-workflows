<?php
/**
 * Main plugin bootstrap class.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Core;

use AIAWAB\Plugin\Admin\ConnectionActionsController;
use AIAWAB\Plugin\Admin\GoogleOAuthStartController;
use AIAWAB\Plugin\Admin\Menu;
use AIAWAB\Plugin\Admin\Pages\BuilderPage;
use AIAWAB\Plugin\Admin\Pages\ConnectionFormPage;
use AIAWAB\Plugin\Admin\Pages\ConnectionsPage;
use AIAWAB\Plugin\Admin\Pages\RunDetailPage;
use AIAWAB\Plugin\Admin\Pages\RunsPage;
use AIAWAB\Plugin\Admin\Pages\SettingsPage;
use AIAWAB\Plugin\Admin\Pages\WebhookFormPage;
use AIAWAB\Plugin\Admin\Pages\WebhooksPage;
use AIAWAB\Plugin\Admin\Pages\WorkflowsPage;
use AIAWAB\Plugin\Admin\RunActionsController;
use AIAWAB\Plugin\Admin\SettingsController;
use AIAWAB\Plugin\Admin\WebhookActionsController;
use AIAWAB\Plugin\Admin\WorkflowActionsController;
use AIAWAB\Plugin\Database\MigrationRunner;
use AIAWAB\Plugin\Database\SchemaMigrations;
use AIAWAB\Plugin\Integration\BuiltInNodeTypes;
use AIAWAB\Plugin\Integration\WorkflowTriggerBinder;
use AIAWAB\Plugin\Persistence\ConnectionRepository;
use AIAWAB\Plugin\Persistence\WebhookRepository;
use AIAWAB\Plugin\Persistence\WorkflowNodeRepository;
use AIAWAB\Plugin\Persistence\WorkflowRepository;
use AIAWAB\Plugin\Persistence\WorkflowRunLogRepository;
use AIAWAB\Plugin\Persistence\WorkflowRunRepository;
use AIAWAB\Plugin\Rest\RestApi;
use AIAWAB\Plugin\Service\Agent\AgentAiClient;
use AIAWAB\Plugin\Service\Agent\AgentService;
use AIAWAB\Plugin\Service\Agent\AgentToolExecutor;
use AIAWAB\Plugin\Service\Agent\AgentToolSchemaBuilder;
use AIAWAB\Plugin\Service\Ai\AiClientBootstrap;
use AIAWAB\Plugin\Service\AiModelsService;
use AIAWAB\Plugin\Service\BackgroundRunner;
use AIAWAB\Plugin\Service\ChatMessageService;
use AIAWAB\Plugin\Service\ConnectionService;
use AIAWAB\Plugin\Service\ConnectionVerifier;
use AIAWAB\Plugin\Service\ElementorFormsService;
use AIAWAB\Plugin\Service\GoogleOAuthService;
use AIAWAB\Plugin\Service\NodeExecutionService;
use AIAWAB\Plugin\Service\NodeTypeRegistry;
use AIAWAB\Plugin\Service\RunRetentionService;
use AIAWAB\Plugin\Service\SettingsService;
use AIAWAB\Plugin\Service\TriggerReentrancyGuard;
use AIAWAB\Plugin\Service\WebhookService;
use AIAWAB\Plugin\Service\WorkflowExecutionService;
use AIAWAB\Plugin\Service\WorkflowService;
use AIAWAB\Plugin\Service\WorkflowNodeTestService;
use AIAWAB\Plugin\Service\WorkflowTestListenerService;
use AIAWAB\Plugin\Provider\PersistenceServiceProvider;
use AIAWAB\Plugin\Provider\AdminServiceProvider;
use AIAWAB\Plugin\Provider\RestServiceProvider;
use AIAWAB\Plugin\Provider\ExecutionServiceProvider;

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

		Capabilities::register();

		AiClientBootstrap::register();

		// Defensive re-grant for already-active installs that predate
		// roadmap item 14 (or whose administrator role lost the caps).
		// Idempotent; cheap when caps are already present.
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			Capabilities::grantToAdministrator();
		}

		$this->registerServices();
		$this->registerNodeTypes();
		$this->registerExecutionEngine();
		$this->registerBackgroundProcessing();
		$this->registerRetentionPruning();
		( new RestApi( $this->container ) )->register();
		$this->registerAdmin();

		// Capability-gated so schema upkeep never runs on ordinary front-end
		// requests; this only protects against tables missing after a
		// manual file update that skipped the activation hook. Any plugin
		// capability (or manage_options, via the user_has_cap fallback)
		// is enough — settings-only users still need a current schema.
		if ( is_admin() && current_user_can( Capabilities::ACCESS ) ) {
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
		$providers = array(
			new PersistenceServiceProvider(),
			new AdminServiceProvider(),
			new RestServiceProvider(),
			new ExecutionServiceProvider(),
		);

		foreach ( $providers as $provider ) {
			$provider->register( $this->container );
		}
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

		$built_in_node_types = new BuiltInNodeTypes(
			$this->container->get( ConnectionService::class ),
			$this->container->get( GoogleOAuthService::class ),
			$this->container->get( AgentService::class ),
			$this->container->get( AgentAiClient::class )
		);

		add_action( 'wfa/nodes/register', array( $built_in_node_types, 'register' ) );

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
					$this->container->get( WorkflowExecutionService::class ),
					$this->container->get( SettingsService::class ),
					$this->container->get( WorkflowTestListenerService::class ),
					$this->container->get( TriggerReentrancyGuard::class )
				);

				$binder->bindActiveWorkflows();
			},
			20
		);
	}

	/**
	 * Wires the WP-Cron-driven background queue: registers the custom
	 * recurring schedule BackgroundRunner needs, and binds its
	 * processBatch() method to the cron hook that fires it.
	 *
	 * The recurring event itself is scheduled on activation (see
	 * Activator) and cleared on deactivation (see Deactivator), not here —
	 * this method runs on every `plugins_loaded`, so scheduling from here
	 * too would just mean redundantly checking wp_next_scheduled() on
	 * every request. The one exception is the capability-gated defensive
	 * re-check below, mirroring the migration re-check in load(): it only
	 * protects against the scheduled event having been lost (e.g. a manual
	 * file update that skipped the activation hook), so it is fine for it
	 * to only run for a logged-in administrator viewing wp-admin.
	 *
	 * @return void
	 */
	private function registerBackgroundProcessing(): void {
		add_filter( 'cron_schedules', array( BackgroundRunner::class, 'registerCronSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- interval is intentionally short; see BackgroundRunner::CRON_SCHEDULE docblock.

		add_action(
			BackgroundRunner::CRON_HOOK,
			function (): void {
				$this->container->get( BackgroundRunner::class )->processBatch();
			}
		);

		if ( is_admin() && current_user_can( Capabilities::ACCESS ) && ! wp_next_scheduled( BackgroundRunner::CRON_HOOK ) ) {
			wp_schedule_event( time(), BackgroundRunner::CRON_SCHEDULE, BackgroundRunner::CRON_HOOK );
		}
	}

	/**
	 * Wires the daily WP-Cron tick that implements the Settings screen's
	 * "Logging & Retention" setting (roadmap item 10) — see
	 * RunRetentionService. Uses WordPress's built-in `daily` schedule,
	 * unlike BackgroundRunner's custom per-minute one: pruning is a
	 * housekeeping task, not something that needs to happen more than
	 * once a day.
	 *
	 * Scheduled on activation (see Activator) and cleared on deactivation
	 * (see Deactivator), for the same reasons documented on
	 * registerBackgroundProcessing() above; the defensive re-check here
	 * mirrors that method's for the same "manual file update skipped
	 * activation" scenario.
	 *
	 * @return void
	 */
	private function registerRetentionPruning(): void {
		add_action(
			RunRetentionService::CRON_HOOK,
			function (): void {
				$this->container->get( RunRetentionService::class )->pruneAccordingToSettings();
			}
		);

		if ( is_admin() && current_user_can( Capabilities::ACCESS ) && ! wp_next_scheduled( RunRetentionService::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', RunRetentionService::CRON_HOOK );
		}
	}

	/**
	 * Registers the admin menu/screens and the admin-post action handlers
	 * that back their row/page actions.
	 *
	 * Page order matters here: Menu::registerMenu() treats the first entry
	 * as the top-level menu item, so WorkflowsPage must stay first. RunsPage,
	 * SettingsPage, ConnectionsPage, and WebhooksPage — the other
	 * menu-visible pages (see RunDetailPage::showInMenu()) — are added
	 * right after it, in the order they were introduced by the roadmap.
	 * The hidden pages (BuilderPage, RunDetailPage, ConnectionFormPage,
	 * WebhookFormPage) can go anywhere after that.
	 *
	 * @return void
	 */
	private function registerAdmin(): void {
		$workflows           = $this->container->get( WorkflowService::class );
		$runs                = $this->container->get( WorkflowRunRepository::class );
		$workflow_repository = $this->container->get( WorkflowRepository::class );
		$executor            = $this->container->get( WorkflowExecutionService::class );
		$settings            = $this->container->get( SettingsService::class );
		$retention           = $this->container->get( RunRetentionService::class );
		$connections         = $this->container->get( ConnectionService::class );
		$google_oauth        = $this->container->get( GoogleOAuthService::class );
		$webhooks            = $this->container->get( WebhookService::class );

		$workflow_actions     = new WorkflowActionsController( $workflows, WorkflowsPage::SLUG );
		$workflows_page       = new WorkflowsPage( $workflows, $settings, $workflow_actions );
		$run_actions          = new RunActionsController( $executor, $runs, $this->container->get( WorkflowRunLogRepository::class ) );
		$runs_page            = new RunsPage( $runs, $workflow_repository, $settings, $run_actions );
		$builder_page         = new BuilderPage();
		$run_detail_page      = new RunDetailPage( $runs, $workflow_repository, $executor, $settings );
		$settings_page        = new SettingsPage( $settings );
		$connection_actions   = new ConnectionActionsController( $connections );
		$connections_page     = new ConnectionsPage( $connections, $settings, $connection_actions );
		$connection_form_page = new ConnectionFormPage( $connections, $google_oauth );
		$webhook_actions      = new WebhookActionsController( $webhooks );
		$webhooks_page        = new WebhooksPage( $webhooks, $workflows, $settings, $webhook_actions );
		$webhook_form_page    = new WebhookFormPage( $webhooks, $workflows, $settings );

		( new Menu(
			array(
				$workflows_page,
				$runs_page,
				$settings_page,
				$connections_page,
				$webhooks_page,
				$builder_page,
				$run_detail_page,
				$connection_form_page,
				$webhook_form_page,
			)
		) )->register();
		( $workflow_actions )->register();
		$run_actions->register();
		( new SettingsController( $settings, $retention ) )->register();
		$connection_actions->register();
		( new GoogleOAuthStartController( $connections, $google_oauth ) )->register();
		$webhook_actions->register();
	}
}
