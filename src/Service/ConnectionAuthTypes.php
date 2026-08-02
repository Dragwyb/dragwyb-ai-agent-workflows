<?php
/**
 * Built-in connection authentication type definitions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the credential-field shape for each supported `auth_type`.
 *
 * This is a small, fixed lookup table rather than a `NodeTypeRegistry`-style
 * hook-based registry (compare `aiawa/nodes/register`). Nothing yet consumes
 * an auth type beyond what's built in here — no real third-party
 * integration exists until roadmap item 12 — so an extension point would
 * be speculative surface with nothing to exercise it, the same reasoning
 * that already deferred `aiawa/integrations/register` (see
 * `docs/internal/architecture.md` §2.6). OAuth2 is deliberately not one of
 * the built-in types yet either: it needs a redirect/callback flow and
 * token refresh handling that this item's scope (storage + encryption)
 * does not cover.
 */
class ConnectionAuthTypes {

	public const API_KEY = 'api_key';

	public const BASIC = 'basic';

	public const BEARER_TOKEN = 'bearer_token';

	public const OAUTH2 = 'oauth2';

	/**
	 * @var string[]
	 */
	public const VALID = array( self::API_KEY, self::BASIC, self::BEARER_TOKEN, self::OAUTH2 );

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
				return __( 'Username & Password', 'ai-agent-workflow-automation' );
			case self::BEARER_TOKEN:
				return __( 'Bearer Token', 'ai-agent-workflow-automation' );
			case self::OAUTH2:
				return __( 'OAuth 2', 'ai-agent-workflow-automation' );
			case self::API_KEY:
			default:
				return __( 'API Key', 'ai-agent-workflow-automation' );
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
						'label'  => __( 'Username', 'ai-agent-workflow-automation' ),
						'secret' => false,
					),
					'password' => array(
						'label'  => __( 'Password', 'ai-agent-workflow-automation' ),
						'secret' => true,
					),
				);
			case self::BEARER_TOKEN:
				return array(
					'token' => array(
						'label'  => __( 'Bearer Token', 'ai-agent-workflow-automation' ),
						'secret' => true,
					),
				);
			case self::OAUTH2:
				return array(
					'client_id'     => array(
						'label'  => __( 'Client ID', 'ai-agent-workflow-automation' ),
						'secret' => false,
					),
					'client_secret' => array(
						'label'  => __( 'Client Secret', 'ai-agent-workflow-automation' ),
						'secret' => true,
					),
					'access_token'  => array(
						'label'              => __( 'Access Token', 'ai-agent-workflow-automation' ),
						'secret'             => true,
						'required_on_create' => false,
					),
					'refresh_token' => array(
						'label'              => __( 'Refresh Token', 'ai-agent-workflow-automation' ),
						'secret'             => true,
						'required_on_create' => false,
					),
					'expires_at'    => array(
						'label'              => __( 'Token Expires At', 'ai-agent-workflow-automation' ),
						'secret'             => false,
						'required_on_create' => false,
					),
				);
			case self::API_KEY:
			default:
				return array(
					'api_key' => array(
						'label'  => __( 'API Key', 'ai-agent-workflow-automation' ),
						'secret' => true,
					),
				);
		}
	}

	/**
	 * Whether a credential field must be present when creating a connection.
	 *
	 * @param string $auth_type One of self::VALID.
	 * @param string $field     Field name from self::fields().
	 */
	public static function isRequiredOnCreate( string $auth_type, string $field ): bool {
		$fields = self::fields( $auth_type );

		if ( ! isset( $fields[ $field ] ) ) {
			return false;
		}

		$meta = $fields[ $field ];

		return ! isset( $meta['required_on_create'] ) || $meta['required_on_create'];
	}

	/**
	 * Credential fields a user can edit in the admin form (OAuth tokens are
	 * managed by the authorization flow instead).
	 *
	 * @param string $auth_type One of self::VALID.
	 *
	 * @return array<string, array{label: string, secret: bool, required_on_create?: bool}>
	 */
	public static function editableFields( string $auth_type ): array {
		$fields = self::fields( $auth_type );

		if ( self::OAUTH2 !== $auth_type ) {
			return $fields;
		}

		unset( $fields['access_token'], $fields['refresh_token'], $fields['expires_at'] );

		return $fields;
	}
}
