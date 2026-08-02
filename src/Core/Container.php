<?php
/**
 * Minimal service container.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Core;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small, dependency-free service locator.
 *
 * Deliberately not a full IoC container: it exists to avoid a "God" Plugin
 * class and to give services a single, testable place to be resolved from,
 * without pulling in a third-party DI library for a WordPress plugin.
 */
class Container {

	/**
	 * Registered factory closures, keyed by service id.
	 *
	 * @var array<string, callable>
	 */
	private $bindings = array();

	/**
	 * Resolved singleton instances, keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private $instances = array();

	/**
	 * Registers a factory for a service.
	 *
	 * @param string   $id      Service identifier (typically a class or interface name).
	 * @param callable $factory Factory that receives this container and returns the service instance.
	 *
	 * @return void
	 */
	public function bind( string $id, callable $factory ): void {
		$this->bindings[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Registers a factory whose result is cached after the first resolution.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory that receives this container and returns the service instance.
	 *
	 * @return void
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->bind( $id, $factory );
	}

	/**
	 * Resolves a service by id.
	 *
	 * @param string $id Service identifier.
	 *
	 * @throws \RuntimeException When no binding exists for the given id.
	 *
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			throw new \RuntimeException(
				esc_html(
					sprintf( 'No binding registered for "%s".', $id )
				)
			);
		}

		$instance = ( $this->bindings[ $id ] )( $this );

		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Whether a binding is registered for the given id.
	 *
	 * @param string $id Service identifier.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] );
	}
}
