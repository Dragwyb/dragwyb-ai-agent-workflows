<?php
/**
 * Plugin capability definitions and role wiring.
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
 * Custom capabilities for Workflow Automate (roadmap item 14), layered
 * over WordPress's `manage_options` so existing administrators keep full
 * access without any role-editor work, while site owners can grant
 * narrower access (workflows only, runs only, etc.) to non-admin roles.
 *
 * Two mechanisms work together:
 *
 * 1. Caps are added to the `administrator` role on activation (and
 *    defensively on load for already-active installs), so they show up
 *    in role editors and are stored on the role like any other cap.
 * 2. A `user_has_cap` filter grants every plugin cap to anyone who
 *    already has `manage_options`, so a custom role that has
 *    `manage_options` but not our explicit caps still works, and so
 *    administrators keep access even if a role editor strips the
 *    explicit `wfa_*` entries.
 *
 * Callers should check the granular caps (`MANAGE_WORKFLOWS`, etc.) via
 * `current_user_can()` — never `manage_options` directly — so a user
 * granted only `wfa_manage_runs` is correctly limited to the Runs
 * screens.
 */
class Capabilities {

	/**
	 * Can see the top-level plugin menu. Implied by any granular cap
	 * (and by `manage_options`) via filterUserHasCap().
	 */
	public const ACCESS = 'wfa_access';

	public const MANAGE_WORKFLOWS = 'wfa_manage_workflows';

	public const MANAGE_RUNS = 'wfa_manage_runs';

	public const MANAGE_CONNECTIONS = 'wfa_manage_connections';

	public const MANAGE_WEBHOOKS = 'wfa_manage_webhooks';

	public const MANAGE_SETTINGS = 'wfa_manage_settings';

	/**
	 * Every capability this plugin owns, including ACCESS.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_merge( array( self::ACCESS ), self::granular() );
	}

	/**
	 * Capabilities that gate a specific feature area (not menu visibility).
	 *
	 * @return string[]
	 */
	public static function granular(): array {
		return array(
			self::MANAGE_WORKFLOWS,
			self::MANAGE_RUNS,
			self::MANAGE_CONNECTIONS,
			self::MANAGE_WEBHOOKS,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Hooks the `user_has_cap` fallback. Safe to call more than once.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'user_has_cap', array( self::class, 'filterUserHasCap' ), 10, 4 );
	}

	/**
	 * Grants every plugin capability to the administrator role. Idempotent
	 * (`WP_Role::add_cap()` is a no-op when the cap is already present).
	 *
	 * @return void
	 */
	public static function grantToAdministrator(): void {
		$role = get_role( 'administrator' );

		if ( null === $role ) {
			return;
		}

		foreach ( self::all() as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Removes every plugin capability from every role. Used only by the
	 * opt-in uninstall data-removal path — deactivation deliberately
	 * leaves caps in place so reactivation does not require re-granting.
	 *
	 * @return void
	 */
	public static function revokeFromAllRoles(): void {
		$roles = wp_roles();

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( (string) $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Layers plugin capabilities on top of `manage_options`, and grants
	 * ACCESS whenever the user has any granular plugin cap (so a role
	 * given only `wfa_manage_runs` still sees the top-level menu).
	 *
	 * @param array<string, bool> $allcaps Caps the user already has.
	 * @param string[]            $caps    Primitive caps being checked (unused).
	 * @param array<int, mixed>   $args    Arguments to current_user_can() (unused).
	 * @param \WP_User            $user    User being checked (unused; $allcaps is authoritative).
	 *
	 * @return array<string, bool>
	 */
	public static function filterUserHasCap( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );

		if ( ! is_array( $allcaps ) ) {
			return array();
		}

		if ( ! empty( $allcaps['manage_options'] ) ) {
			foreach ( self::all() as $cap ) {
				$allcaps[ $cap ] = true;
			}

			return $allcaps;
		}

		foreach ( self::granular() as $cap ) {
			if ( ! empty( $allcaps[ $cap ] ) ) {
				$allcaps[ self::ACCESS ] = true;
				break;
			}
		}

		return $allcaps;
	}
}
