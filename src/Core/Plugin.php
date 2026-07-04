<?php
/**
 * Main plugin bootstrap class.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Core;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin on `plugins_loaded`.
 *
 * Deliberately thin: this class only wires the requirements check and the
 * service container, then fires an extensibility hook. Feature providers
 * (admin UI, REST API, execution engine, etc.) are registered against the
 * container in their own roadmap increments rather than being added here,
 * so this class never grows into a "God" class.
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
}
