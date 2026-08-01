<?php
/**
 * Service provider contract.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Provider;

use AIAWA\Plugin\Core\Container;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for service registration modules.
 */
interface ServiceProviderInterface {

	/**
	 * Registers services into the container.
	 *
	 * @param Container $container Plugin container instance.
	 *
	 * @return void
	 */
	public function register( Container $container ): void;
}
