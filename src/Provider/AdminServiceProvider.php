<?php
/**
 * Registers admin domain services against the container.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Provider;

use WorkflowAutomate\Plugin\Core\Container;
use WorkflowAutomate\Plugin\Persistence\ConnectionRepository;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\GoogleOAuthService;
use WorkflowAutomate\Plugin\Service\SettingsService;
use WorkflowAutomate\Plugin\Service\ConnectionVerifier;


// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds admin, connection, and settings services into the container.
 */
final class AdminServiceProvider implements ServiceProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function register( Container $container ): void {
		$container->singleton(
			SettingsService::class,
			static function (): SettingsService {
				return new SettingsService();
			}
		);

		$container->singleton(
			ConnectionVerifier::class,
			static function (): ConnectionVerifier {
				return new ConnectionVerifier();
			}
		);

		$container->singleton(
			ConnectionService::class,
			static function ( Container $container ): ConnectionService {
				return new ConnectionService(
					$container->get( ConnectionRepository::class ),
					$container->get( ConnectionVerifier::class )
				);
			}
		);

		$container->singleton(
			GoogleOAuthService::class,
			static function ( Container $container ): GoogleOAuthService {
				return new GoogleOAuthService( $container->get( ConnectionService::class ) );
			}
		);
	}
}
