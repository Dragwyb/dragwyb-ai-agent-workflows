<?php
/**
 * Built-in connection authentication type definitions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the credential-field shape for each supported `auth_type`.
 *
 * This is a small, fixed lookup table rather than a `NodeTypeRegistry`-style
 * hook-based registry (compare `wfa/nodes/register`). Nothing yet consumes
 * an auth type beyond what's built in here — no real third-party
 * integration exists until roadmap item 12 — so an extension point would
 * be speculative surface with nothing to exercise it, the same reasoning
 * that already deferred `wfa/integrations/register` (see
 * `docs/internal/architecture.md` §2.6). OAuth2 is deliberately not one of
 * the built-in types yet either: it needs a redirect/callback flow and
 * token refresh handling that this item's scope (storage + encryption)
 * does not cover.
 */
class ConnectionAuthTypes {

	public const API_KEY = 'api_key';

	public const BASIC = 'basic';

	public const BEARER_TOKEN = 'bearer_token';

	/**
	 * @var string[]
	 */
	public const VALID = array( self::API_KEY, self::BASIC, self::BEARER_TOKEN );

	/**
	 * Human-readable label for an auth type.
	 *
	 * @param string $auth_type One of self::VALID.
	 *
	 * @return string
	 */
	public static function label( string $auth_type ): string {
		switch ( $auth_type ) {
			case self::BASIC:
				return __( 'Username & Password', 'workflow-automate' );
			case self::BEARER_TOKEN:
				return __( 'Bearer Token', 'workflow-automate' );
			case self::API_KEY:
			default:
				return __( 'API Key', 'workflow-automate' );
		}
	}

	/**
	 * The credential fields an auth type requires.
	 *
	 * @param string $auth_type One of self::VALID.
	 *
	 * @return array<string, array{label: string, secret: bool}> Field name => field definition.
	 */
	public static function fields( string $auth_type ): array {
		switch ( $auth_type ) {
			case self::BASIC:
				return array(
					'username' => array(
						'label' => __( 'Username', 'workflow-automate' ),
						'secret' => false,
					),
					'password' => array(
						'label' => __( 'Password', 'workflow-automate' ),
						'secret' => true,
					),
				);
			case self::BEARER_TOKEN:
				return array(
					'token' => array(
						'label' => __( 'Bearer Token', 'workflow-automate' ),
						'secret' => true,
					),
				);
			case self::API_KEY:
			default:
				return array(
					'api_key' => array(
						'label' => __( 'API Key', 'workflow-automate' ),
						'secret' => true,
					),
				);
		}
	}
}
