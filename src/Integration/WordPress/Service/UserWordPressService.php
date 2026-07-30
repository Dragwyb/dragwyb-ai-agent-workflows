<?php
/**
 * Business logic for WordPress User, Role, and Capability actions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress\Service;

use AIAWAB\Plugin\Integration\WordPress\WordPressActionHelper;

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
			static fn( $user ): array => WordPressActionHelper::serializeUser( $user ),
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
		$email    = WordPressActionHelper::str( $config, 'email' );
		$username = WordPressActionHelper::str( $config, 'username' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'Email is required.', 'workflow-automate' ) );
		}

		if ( '' === $username ) {
			return WordPressActionHelper::fail( __( 'Username is required.', 'workflow-automate' ) );
		}

		if ( get_user_by( 'email', $email ) ) {
			return WordPressActionHelper::fail( __( 'A user with this email already exists.', 'workflow-automate' ) );
		}

		$autoPassword = WordPressActionHelper::bool( $config, 'auto_password' );
		$password     = $autoPassword ? wp_generate_password() : WordPressActionHelper::str( $config, 'password' );

		if ( '' === $password ) {
			return WordPressActionHelper::fail( __( 'Password is required.', 'workflow-automate' ) );
		}

		$userRole = WordPressActionHelper::str( $config, 'user_role' );

		if ( '' === $userRole ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
		}

		$userData               = WordPressActionHelper::mapUserFields( $config );
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

		foreach ( WordPressActionHelper::keyValue( $config, 'metadata' ) as $metaKey => $metaValue ) {
			update_user_meta( $userId, $metaKey, $metaValue );
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
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$userData       = WordPressActionHelper::mapUserFields( $config );
		$userData['ID'] = $userId;

		$userRole = WordPressActionHelper::str( $config, 'user_role' );

		if ( '' !== $userRole ) {
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

		foreach ( WordPressActionHelper::keyValue( $config, 'metadata' ) as $metaKey => $metaValue ) {
			update_user_meta( $userId, $metaKey, $metaValue );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'user'    => $this->fetchUserInfo( $userId ),
			)
		);
	}

	public function deleteUser( array $config ): array {
		$useEmail       = WordPressActionHelper::bool( $config, 'use_email' );
		$userId         = WordPressActionHelper::int( $config, 'user_id' );
		$userEmail      = WordPressActionHelper::str( $config, 'user_email' );
		$reassignUserId = WordPressActionHelper::int( $config, 'reassign_user_id' );

		if ( $useEmail ) {
			if ( '' === $userEmail ) {
				return WordPressActionHelper::fail( __( 'User email is required.', 'workflow-automate' ) );
			}

			$user = get_user_by( 'email', $userEmail );

			if ( ! $user ) {
				return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
			}

			$userId = (int) $user->ID;
		} elseif ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		$result = wp_delete_user( $userId, $reassignUserId > 0 ? $reassignUserId : null );

		if ( ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to delete user.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'user_id' => $userId ) );
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
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
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
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$user = $this->fetchUserInfo( $userId );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserByEmail( array $config ): array {
		$email = WordPressActionHelper::str( $config, 'user_email' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'User email is required.', 'workflow-automate' ) );
		}

		$user = $this->fetchUserByField( 'email', $email );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserByField( array $config ): array {
		$fieldKey   = WordPressActionHelper::str( $config, 'field_key' );
		$fieldValue = WordPressActionHelper::str( $config, 'field_value' );

		if ( '' === $fieldKey ) {
			return WordPressActionHelper::fail( __( 'Field is required.', 'workflow-automate' ) );
		}

		if ( '' === $fieldValue ) {
			return WordPressActionHelper::fail( __( 'Field value is required.', 'workflow-automate' ) );
		}

		$user = $this->fetchUserByField( $fieldKey, $fieldValue );

		if ( array() === $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( $user );
	}

	public function getUserMetadata( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$metadata = $this->fetchUserMeta( $userId );

		if ( empty( $metadata ) ) {
			return WordPressActionHelper::fail( __( 'User metadata not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( $metadata );
	}

	public function getUserMetadataByMetaKey( array $config ): array {
		$userId  = WordPressActionHelper::int( $config, 'user_id' );
		$metaKey = WordPressActionHelper::str( $config, 'meta_key' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'workflow-automate' ) );
		}

		$metadata = $this->fetchUserMeta( $userId, $metaKey, true );

		if ( '' === $metadata ) {
			return WordPressActionHelper::fail( __( 'User metadata not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( $metaKey => $metadata ) );
	}

	public function updateUserMetadata( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$metadataMap = WordPressActionHelper::keyValue( $config, 'metadata' );

		if ( array() === $metadataMap ) {
			$metaKey = WordPressActionHelper::str( $config, 'meta_key' );

			if ( '' === $metaKey ) {
				return WordPressActionHelper::fail( __( 'Metadata is required.', 'workflow-automate' ) );
			}

			$metadataMap[ $metaKey ] = $config['meta_value'] ?? '';
		}

		foreach ( $metadataMap as $key => $val ) {
			update_user_meta( $userId, $key, $val );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id'  => $userId,
				'metadata' => $this->fetchUserMeta( $userId ),
			)
		);
	}

	public function createRole( array $config ): array {
		$roleName        = WordPressActionHelper::str( $config, 'role_name' );
		$roleDisplayName = WordPressActionHelper::str( $config, 'role_display_name' );
		$capabilities    = WordPressActionHelper::parseCapabilities( $config['role_capabilities'] ?? array() );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		if ( '' === $roleDisplayName ) {
			return WordPressActionHelper::fail( __( 'Role display name is required.', 'workflow-automate' ) );
		}

		$role = add_role( $roleName, $roleDisplayName, $capabilities );

		if ( null === $role ) {
			return WordPressActionHelper::fail( __( 'Role already exists.', 'workflow-automate' ) );
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
		$roleName = WordPressActionHelper::str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		if ( ! wp_roles()->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		remove_role( $roleName );

		return WordPressActionHelper::ok( array( 'role_name' => $roleName ) );
	}

	public function manageUserRole( array $config, bool $remove = false, bool $update = false ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$roles = WordPressActionHelper::parseList( $config['user_role'] ?? array() );

		if ( array() === $roles ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
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
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		$wpRoles = wp_roles();

		if ( ! $wpRoles || ! $wpRoles->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		$role = $wpRoles->get_role( $roleName );

		return WordPressActionHelper::ok( $role ? $role->capabilities : array() );
	}

	public function manageRoleCapabilities( array $config, bool $remove = false ): array {
		$roleName = WordPressActionHelper::str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		$wpRoles = wp_roles();

		if ( ! $wpRoles || ! $wpRoles->is_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		$role = $wpRoles->get_role( $roleName );

		if ( ! $role ) {
			return WordPressActionHelper::fail( __( 'Role object unavailable.', 'workflow-automate' ) );
		}

		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'workflow-automate' ) );
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
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( $user->allcaps );
	}

	public function manageUserCapabilities( array $config, bool $remove = false ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'workflow-automate' ) );
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
