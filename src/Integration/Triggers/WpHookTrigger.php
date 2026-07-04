<?php
/**
 * Built-in "WordPress Hook" trigger.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Triggers;

use WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow whenever a specific WordPress action hook fires (e.g.
 * `user_register`, `save_post`, `woocommerce_order_status_completed`).
 *
 * This is the plugin's most general-purpose trigger: it lets a workflow
 * react to virtually anything WordPress core or another plugin already
 * announces via `do_action()`, without this plugin needing bespoke
 * integration code for every possible event source.
 */
class WpHookTrigger implements TriggerInterface {

	private const DEFAULT_PRIORITY = 10;

	private const DEFAULT_ACCEPTED_ARGS = 1;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'wp_hook_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'WordPress Hook', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow whenever a specific WordPress action hook fires.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'hook_name' => array(
				'type' => 'string',
				'label' => __( 'Hook name', 'workflow-automate' ),
				'required' => true,
			),
			'priority' => array(
				'type' => 'integer',
				'label' => __( 'Priority', 'workflow-automate' ),
				'default' => self::DEFAULT_PRIORITY,
			),
			'accepted_args' => array(
				'type' => 'integer',
				'label' => __( 'Accepted arguments', 'workflow-automate' ),
				'default' => self::DEFAULT_ACCEPTED_ARGS,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$hook_name = isset( $config['hook_name'] ) ? trim( (string) $config['hook_name'] ) : '';

		if ( '' === $hook_name ) {
			return;
		}

		$priority = isset( $config['priority'] ) ? (int) $config['priority'] : self::DEFAULT_PRIORITY;
		$accepted_args = isset( $config['accepted_args'] ) ? max( 0, (int) $config['accepted_args'] ) : self::DEFAULT_ACCEPTED_ARGS;

		add_action(
			$hook_name,
			static function ( ...$args ) use ( $on_fire, $config ) {
				$on_fire( $args, $config );
			},
			$priority,
			$accepted_args
		);
	}
}
