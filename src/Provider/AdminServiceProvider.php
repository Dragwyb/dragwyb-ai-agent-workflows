<?php
/**
 * Registers admin domain services against the container.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Provider;

use DRAGAIW\Plugin\Core\Container;
use DRAGAIW\Plugin\Persistence\ConnectionRepository;
use DRAGAIW\Plugin\Service\ConnectionService;
use DRAGAIW\Plugin\Service\GoogleOAuthService;
use DRAGAIW\Plugin\Service\SettingsService;
use DRAGAIW\Plugin\Service\ConnectionVerifier;


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
