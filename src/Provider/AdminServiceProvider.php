<?php
/**
 * Registers admin domain services against the container.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Provider;

use AIAWAB\Plugin\Core\Container;
use AIAWAB\Plugin\Persistence\ConnectionRepository;
use AIAWAB\Plugin\Service\ConnectionService;
use AIAWAB\Plugin\Service\GoogleOAuthService;
use AIAWAB\Plugin\Service\SettingsService;
use AIAWAB\Plugin\Service\ConnectionVerifier;


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
