<?php
/**
 * Business logic for WordPress User, Role, and Capability actions.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\WordPress\Service;

use DragwybVisualAutomation\Plugin\Integration\WordPress\WordPressActionHelper;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User management domain service.
 */
final class UserWordPressService {

	private function fetchUsers( array $args = array() ): array {
		if ( ! isset( $args['number'] ) ) {
			$args['number'] = WordPressActionHelper::resolveListLimit(
				isset( $args['limit'] ) ? (int) $args['limit'] : null
			);
			unset( $args['limit'] );
		}

		return array_map(
			function( $user ): array {
				return WordPressActionHelper::serializeUser( $user );
			},
			get_users( $args )
		);
	}

	private function fetchUserInfo( int $userId ): array {
		$user = get_userdata( $userId );

		return $user ? WordPressActionHelper::serializeUser( $user ) : array();
	}

	private function fetchUserByField( string $field, $value ): array {
		$user = get_user_by( $field, $value );

		return $user ? WordPressActionHelper::serializeUser( $user ) : array();
	}

	private function fetchUserMeta( int $userId, string $metaKey = '', bool $single = false ) {
		return '' === $metaKey ? get_user_meta( $userId ) : get_user_meta( $userId, $metaKey, $single );
	}

	public function createUser( array $config ): array {
		if ( ! current_user_can( 'create_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Creating users requires the create_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$email    = WordPressActionHelper::str( $config, 'email' );
		$username = WordPressActionHelper::str( $config, 'username' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'Email is required.', 'dragwyb-visual-automation' ) );
		}

		if ( '' === $username ) {
			return WordPressActionHelper::fail( __( 'Username is required.', 'dragwyb-visual-automation' ) );
		}

		if ( get_user_by( 'email', $email ) ) {
			return WordPressActionHelper::fail( __( 'A user with this email already exists.', 'dragwyb-visual-automation' ) );
		}

		$autoPassword = WordPressActionHelper::bool( $config, 'auto_password' );
		$password     = $autoPassword ? wp_generate_password() : WordPressActionHelper::str( $config, 'password' );

		if ( '' === $password ) {
			return WordPressActionHelper::fail( __( 'Password is required.', 'dragwyb-visual-automation' ) );
		}

		$userRole = WordPressActionHelper::str( $config, 'user_role' );

		if ( '' === $userRole ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'dragwyb-visual-automation' ) );
		}

		$role_error = $this->validateAssignableRole( $userRole );
		if ( null !== $role_error ) {
			return WordPressActionHelper::fail( $role_error );
		}

		$userData = WordPressActionHelper::mapUserFields( $config );
		$userData['user_login'] = $username;
		$userData['user_email'] = $email;
		$userData['user_pass']  = $password;
		$userData['role']       = $userRole;

		$marker = static function ( int $id ): void {
			WordPressActionHelper::markAutomatedUser( $id );
		};
		add_action( 'user_register', $marker, 0, 1 );

		$userId = wp_insert_user( $userData );

		remove_action( 'user_register', $marker, 0 );

		if ( is_wp_error( $userId ) ) {
			return WordPressActionHelper::fail( $userId->get_error_message() );
		}

		WordPressActionHelper::markAutomatedUser( (int) $userId );

		$meta_error = $this->applySafeUserMeta( (int) $userId, WordPressActionHelper::keyValue( $config, 'metadata' ) );
		if ( null !== $meta_error ) {
			return WordPressActionHelper::fail( $meta_error );
		}

		$emailNotification = WordPressActionHelper::str( $config, 'email_notification', 'none' );

		if ( '' !== $emailNotification && 'none' !== $emailNotification ) {
			wp_new_user_notification( $userId, null, $emailNotification );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'user'    => $this->fetchUserInfo( $userId ),
			)
		);
	}

	public function updateUser( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		if ( ! current_user_can( 'edit_user', $userId ) ) {
			return WordPressActionHelper::fail(
				__( 'Updating users requires the edit_user capability for the target user. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		$userData       = WordPressActionHelper::mapUserFields( $config );
		$userData['ID'] = $userId;

		$userRole = WordPressActionHelper::str( $config, 'user_role' );

		if ( '' !== $userRole ) {
			if ( ! current_user_can( 'promote_users' ) ) {
				return WordPressActionHelper::fail( __( 'Changing user roles requires the promote_users capability.', 'dragwyb-visual-automation' ) );
			}

			$role_error = $this->validateAssignableRole( $userRole );
			if ( null !== $role_error ) {
				return WordPressActionHelper::fail( $role_error );
			}
			$userData['role'] = $userRole;
		}

		$password = WordPressActionHelper::str( $config, 'password' );

		if ( '' !== $password ) {
			$userData['user_pass'] = $password;
		}

		$result = wp_update_user( $userData );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		$meta_error = $this->applySafeUserMeta( $userId, WordPressActionHelper::keyValue( $config, 'metadata' ) );
		if ( null !== $meta_error ) {
			return WordPressActionHelper::fail( $meta_error );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'user'    => $this->fetchUserInfo( $userId ),
			)
		);
	}

	public function deleteUser( array $config ): array {
		if ( ! current_user_can( 'delete_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Deleting users requires the delete_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$useEmail       = WordPressActionHelper::bool( $config, 'use_email' );
		$userId         = WordPressActionHelper::int( $config, 'user_id' );
		$userEmail      = WordPressActionHelper::str( $config, 'user_email' );
		$reassignUserId = WordPressActionHelper::int( $config, 'reassign_user_id' );

		if ( $useEmail ) {
			if ( '' === $userEmail ) {
				return WordPressActionHelper::fail( __( 'User email is required.', 'dragwyb-visual-automation' ) );
			}

			$user = get_user_by( 'email', $userEmail );

			if ( ! $user ) {
				return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
			}

			$userId = (int) $user->ID;
		} elseif ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		if ( (int) get_current_user_id() === $userId ) {
			return WordPressActionHelper::fail( __( 'You cannot delete the currently authenticated user.', 'dragwyb-visual-automation' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		$result = wp_delete_user( $userId, $reassignUserId > 0 ? $reassignUserId : null );

		if ( ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to delete user.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( array( 'user_id' => $userId ) );
	}

	/**
	 * Only allow roles the current user can assign; never allow administrator
	 * unless promote_users is held (still blocked for safety on public paths).
	 *
	 * @param string $role Role slug.
	 *
	 * @return string|null Error message or null when valid.
	 */
	private function validateAssignableRole( string $role ): ?string {
		$editable = get_editable_roles();

		if ( ! isset( $editable[ $role ] ) ) {
			return __( 'Invalid or unauthorized user role.', 'dragwyb-visual-automation' );
		}

		if ( 'administrator' === $role ) {
			return __( 'Assigning the administrator role via workflows is not allowed.', 'dragwyb-visual-automation' );
		}

		return null;
	}

	/**
	 * Writes only non-sensitive user meta keys.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $metadata Key/value pairs.
	 *
	 * @return string|null Error message or null on success.
	 */
	private function applySafeUserMeta( int $user_id, array $metadata ): ?string {
		foreach ( $metadata as $meta_key => $meta_value ) {
			$key = sanitize_key( (string) $meta_key );

			if ( '' === $key || ! $this->isSafeUserMetaKey( $key ) ) {
				return sprintf(
					/* translators: %s: meta key */
					__( 'User meta key "%s" is not allowed.', 'dragwyb-visual-automation' ),
					(string) $meta_key
				);
			}

			update_user_meta( $user_id, $key, $meta_value );
		}

		return null;
	}

	/**
	 * @param string $key Sanitized meta key.
	 */
	private function isSafeUserMetaKey( string $key ): bool {
		$blocked = array(
			'wp_capabilities',
			'wp_user_level',
			'wp_dashboard_quick_press_last_post_id',
			'session_tokens',
			'default_password_nag',
			'rich_editing',
			'syntax_highlighting',
			'admin_color',
			'show_admin_bar_front',
			'locale',
			'use_ssl',
			'dismissed_wp_pointers',
		);

		if ( in_array( $key, $blocked, true ) ) {
			return false;
		}

		if ( false !== strpos( $key, 'capabilities' ) || false !== strpos( $key, 'user_level' ) ) {
			return false;
		}

		if ( 0 === strpos( $key, 'wp_' ) || 0 === strpos( $key, '_' ) ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $key );
	}

	/**
	 * Reject elevated capabilities that must never be granted via workflow actions.
	 *
	 * @param array<string, bool> $capabilities Capability map.
	 *
	 * @return string|null Error message or null when valid.
	 */
	private function validateAssignableCapabilities( array $capabilities ): ?string {
		$blocked = array(
			'manage_options',
			'edit_users',
			'create_users',
			'delete_users',
			'promote_users',
			'list_users',
			'remove_users',
			'add_users',
			'install_plugins',
			'activate_plugins',
			'edit_plugins',
			'delete_plugins',
			'install_themes',
			'edit_themes',
			'delete_themes',
			'switch_themes',
			'update_core',
			'update_plugins',
			'update_themes',
			'edit_files',
			'unfiltered_html',
			'unfiltered_upload',
		);

		foreach ( array_keys( $capabilities ) as $cap ) {
			$cap = sanitize_key( (string) $cap );
			if ( in_array( $cap, $blocked, true ) ) {
				return sprintf(
					/* translators: %s: capability name */
					__( 'Capability "%s" cannot be assigned via workflows.', 'dragwyb-visual-automation' ),
					$cap
				);
			}
		}

		return null;
	}

	public function getAllUsers( array $config ): array {
		$limit = WordPressActionHelper::int( $config, 'limit' );

		return WordPressActionHelper::ok(
			$this->fetchUsers(
				array(
					'limit' => $limit > 0 ? $limit : null,
				)
			)
		);
	}

	public function getAllUsersByRole( array $config ): array {
		$role = WordPressActionHelper::str( $config, 'user_role' );

		if ( '' === $role ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok(
			$this->fetchUsers(
				array(
					'role'    => $role,
					'orderby' => 'ID',
				)
			)
		);
	}

	public function getUserById( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		$user = $this->fetchUserInfo( $userId );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserByEmail( array $config ): array {
		$email = WordPressActionHelper::str( $config, 'user_email' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'User email is required.', 'dragwyb-visual-automation' ) );
		}

		$user = $this->fetchUserByField( 'email', $email );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserByField( array $config ): array {
		$fieldKey   = WordPressActionHelper::str( $config, 'field_key' );
		$fieldValue = WordPressActionHelper::str( $config, 'field_value' );

		if ( '' === $fieldKey ) {
			return WordPressActionHelper::fail( __( 'Field is required.', 'dragwyb-visual-automation' ) );
		}

		if ( '' === $fieldValue ) {
			return WordPressActionHelper::fail( __( 'Field value is required.', 'dragwyb-visual-automation' ) );
		}

		$user = $this->fetchUserByField( $fieldKey, $fieldValue );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserMetadata( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		$metadata = $this->fetchUserMeta( $userId );

		if ( empty( $metadata ) ) {
			return WordPressActionHelper::fail( __( 'User metadata not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( $metadata );
	}

	public function getUserMetadataByMetaKey( array $config ): array {
		$userId  = WordPressActionHelper::int( $config, 'user_id' );
		$metaKey = WordPressActionHelper::str( $config, 'meta_key' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'dragwyb-visual-automation' ) );
		}

		$metadata = $this->fetchUserMeta( $userId, $metaKey, true );

		if ( '' === $metadata ) {
			return WordPressActionHelper::fail( __( 'User metadata not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( array( $metaKey => $metadata ) );
	}

	public function updateUserMetadata( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		if ( ! current_user_can( 'edit_user', $userId ) ) {
			return WordPressActionHelper::fail(
				__( 'Updating user metadata requires the edit_user capability for the target user. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		$metadataMap = WordPressActionHelper::keyValue( $config, 'metadata' );

		if ( array() === $metadataMap ) {
			$metaKey = WordPressActionHelper::str( $config, 'meta_key' );

			if ( '' === $metaKey ) {
				return WordPressActionHelper::fail( __( 'Metadata is required.', 'dragwyb-visual-automation' ) );
			}

			$metadataMap[ $metaKey ] = $config['meta_value'] ?? '';
		}

		$meta_error = $this->applySafeUserMeta( $userId, $metadataMap );
		if ( null !== $meta_error ) {
			return WordPressActionHelper::fail( $meta_error );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id'  => $userId,
				'metadata' => $this->fetchUserMeta( $userId ),
			)
		);
	}

	public function createRole( array $config ): array {
		if ( ! current_user_can( 'promote_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Creating roles requires the promote_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$roleName        = WordPressActionHelper::str( $config, 'role_name' );
		$roleDisplayName = WordPressActionHelper::str( $config, 'role_display_name' );
		$capabilities    = WordPressActionHelper::parseCapabilities( $config['role_capabilities'] ?? array() );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'dragwyb-visual-automation' ) );
		}

		if ( 'administrator' === sanitize_key( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Creating an administrator role via workflows is not allowed.', 'dragwyb-visual-automation' ) );
		}

		if ( '' === $roleDisplayName ) {
			return WordPressActionHelper::fail( __( 'Role display name is required.', 'dragwyb-visual-automation' ) );
		}

		$cap_error = $this->validateAssignableCapabilities( $capabilities );
		if ( null !== $cap_error ) {
			return WordPressActionHelper::fail( $cap_error );
		}

		$role = add_role( $roleName, $roleDisplayName, $capabilities );

		if ( null === $role ) {
			return WordPressActionHelper::fail( __( 'Role already exists.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'role_name'         => $roleName,
				'role_display_name' => $roleDisplayName,
				'capabilities'      => $role->capabilities,
			)
		);
	}

	public function deleteRole( array $config ): array {
		if ( ! current_user_can( 'promote_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Deleting roles requires the promote_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$roleName = WordPressActionHelper::str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'dragwyb-visual-automation' ) );
		}

		if ( 'administrator' === sanitize_key( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Deleting the administrator role via workflows is not allowed.', 'dragwyb-visual-automation' ) );
		}

		if ( ! wp_roles()->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'dragwyb-visual-automation' ) );
		}

		remove_role( $roleName );

		return WordPressActionHelper::ok( array( 'role_name' => $roleName ) );
	}

	public function manageUserRole( array $config, bool $remove = false, bool $update = false ): array {
		if ( ! current_user_can( 'promote_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Changing user roles requires the promote_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		$roles = WordPressActionHelper::parseList( $config['user_role'] ?? array() );

		if ( array() === $roles ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'dragwyb-visual-automation' ) );
		}

		foreach ( $roles as $role ) {
			$role_error = $this->validateAssignableRole( sanitize_key( (string) $role ) );
			if ( null !== $role_error ) {
				return WordPressActionHelper::fail( $role_error );
			}
		}

		if ( $update ) {
			$user->set_role( $roles[0] );
		} else {
			foreach ( $roles as $role ) {
				if ( $remove ) {
					$user->remove_role( $role );
				} else {
					$user->add_role( $role );
				}
			}
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'roles'   => array_values( $user->roles ),
			)
		);
	}

	public function getAllRoles(): array {
		$wpRoles = wp_roles();

		return WordPressActionHelper::ok( $wpRoles ? $wpRoles->roles : array() );
	}

	public function getAllCapabilities(): array {
		$wpRoles      = wp_roles();
		$capabilities = array();

		if ( $wpRoles ) {
			foreach ( $wpRoles->roles as $role ) {
				if ( isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ) {
					$capabilities = array_merge( $capabilities, array_keys( $role['capabilities'] ) );
				}
			}
		}

		return WordPressActionHelper::ok( array_values( array_unique( $capabilities ) ) );
	}

	public function getRoleCapabilities( array $config ): array {
		$roleName = WordPressActionHelper::str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'dragwyb-visual-automation' ) );
		}

		$wpRoles = wp_roles();

		if ( ! $wpRoles || ! $wpRoles->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'dragwyb-visual-automation' ) );
		}

		$role = $wpRoles->get_role( $roleName );

		return WordPressActionHelper::ok( $role ? $role->capabilities : array() );
	}

	public function manageRoleCapabilities( array $config, bool $remove = false ): array {
		if ( ! current_user_can( 'promote_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Changing role capabilities requires the promote_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$roleName = WordPressActionHelper::str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'dragwyb-visual-automation' ) );
		}

		if ( 'administrator' === sanitize_key( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Modifying administrator capabilities via workflows is not allowed.', 'dragwyb-visual-automation' ) );
		}

		$wpRoles = wp_roles();

		if ( ! $wpRoles || ! $wpRoles->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'dragwyb-visual-automation' ) );
		}

		$role = $wpRoles->get_role( $roleName );

		if ( ! $role ) {
			return WordPressActionHelper::fail( __( 'Role object unavailable.', 'dragwyb-visual-automation' ) );
		}

		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'dragwyb-visual-automation' ) );
		}

		$cap_map = array();
		foreach ( $capabilities as $cap ) {
			$cap_map[ sanitize_key( (string) $cap ) ] = true;
		}

		$cap_error = $this->validateAssignableCapabilities( $cap_map );
		if ( null !== $cap_error ) {
			return WordPressActionHelper::fail( $cap_error );
		}

		foreach ( $capabilities as $cap ) {
			if ( $remove ) {
				$role->remove_cap( $cap );
			} else {
				$role->add_cap( $cap );
			}
		}

		return WordPressActionHelper::ok(
			array(
				'role_name'    => $roleName,
				'capabilities' => $role->capabilities,
			)
		);
	}

	public function getUserCapabilities( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		return WordPressActionHelper::ok( $user->allcaps );
	}

	public function manageUserCapabilities( array $config, bool $remove = false ): array {
		if ( ! current_user_can( 'promote_users' ) ) {
			return WordPressActionHelper::fail(
				__( 'Changing user capabilities requires the promote_users capability. This action cannot run from unauthenticated public triggers.', 'dragwyb-visual-automation' )
			);
		}

		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-visual-automation' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'dragwyb-visual-automation' ) );
		}

		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'dragwyb-visual-automation' ) );
		}

		$cap_map = array();
		foreach ( $capabilities as $cap ) {
			$cap_map[ sanitize_key( (string) $cap ) ] = true;
		}

		$cap_error = $this->validateAssignableCapabilities( $cap_map );
		if ( null !== $cap_error ) {
			return WordPressActionHelper::fail( $cap_error );
		}

		foreach ( $capabilities as $cap ) {
			if ( $remove ) {
				$user->remove_cap( $cap );
			} else {
				$user->add_cap( $cap );
			}
		}

		return WordPressActionHelper::ok(
			array(
				'user_id'      => $userId,
				'capabilities' => $user->allcaps,
			)
		);
	}
}
