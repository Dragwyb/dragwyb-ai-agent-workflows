<?php
/**
 * Implements every WordPress workflow action's business logic.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\WordPress;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One method per WordPress action, each taking the action's config array
 * and returning `array{success: bool, error?: string, ...}`. Never throws
 * for expected failures (missing id, not found, WP_Error, ...).
 */
final class WordPressServices {

	// -----------------------------------------------------------------
	// Config readers.
	// -----------------------------------------------------------------

	private function str( array $config, string $key, string $default = '' ): string {
		return isset( $config[ $key ] ) ? trim( (string) $config[ $key ] ) : $default;
	}

	private function int( array $config, string $key, int $default = 0 ): int {
		return isset( $config[ $key ] ) && '' !== $config[ $key ] ? (int) $config[ $key ] : $default;
	}

	private function bool( array $config, string $key, bool $default = false ): bool {
		if ( ! isset( $config[ $key ] ) ) {
			return $default;
		}

		return filter_var( $config[ $key ], FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Reads a `key_value` field (rows of `{key, value}`) into an assoc array.
	 *
	 * @return array<string, mixed>
	 */
	private function keyValue( array $config, string $key ): array {
		$rows = isset( $config[ $key ] ) && is_array( $config[ $key ] ) ? $config[ $key ] : array();
		$map = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rowKey = isset( $row['key'] ) ? trim( (string) $row['key'] ) : '';

			if ( '' === $rowKey ) {
				continue;
			}

			$map[ $rowKey ] = $row['value'] ?? '';
		}

		return $map;
	}

	// -----------------------------------------------------------------
	// Inline utility helpers (no bit-pi dependency).
	// -----------------------------------------------------------------

	/**
	 * @param array<string, mixed> $args get_users() query args.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fetchUsers( array $args = array() ): array {
		return array_map(
			static fn( $user ): array => WordPressActionHelper::serializeUser( $user ),
			get_users( $args )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fetchUserInfo( int $userId ): array {
		$user = get_userdata( $userId );

		return $user ? WordPressActionHelper::serializeUser( $user ) : array();
	}

	/**
	 * @param mixed $value Field value to match.
	 *
	 * @return array<string, mixed>
	 */
	private function fetchUserByField( string $field, $value ): array {
		$user = get_user_by( $field, $value );

		return $user ? WordPressActionHelper::serializeUser( $user ) : array();
	}

	/**
	 * @return mixed
	 */
	private function fetchUserMeta( int $userId, string $metaKey = '', bool $single = false ) {
		return '' === $metaKey ? get_user_meta( $userId ) : get_user_meta( $userId, $metaKey, $single );
	}

	/**
	 * Applies taxonomy terms, tags, and categories from config onto a post
	 * after create/update, shared by `createNewPost()` and `updateExistingPost()`.
	 *
	 * @param array<string, mixed> $config Action config.
	 *
	 * @return void
	 */
	private function applyPostTaxonomies( int $postId, array $config, bool $append ): void {
		$taxonomy = $this->str( $config, 'taxonomy' );
		$terms = WordPressActionHelper::parseList( $config['terms'] ?? array() );

		if ( '' !== $taxonomy && array() !== $terms ) {
			wp_set_object_terms( $postId, $terms, $taxonomy, $append );
		}

		$tags = WordPressActionHelper::parseList( $config['tags'] ?? array() );

		if ( array() !== $tags ) {
			wp_set_post_tags( $postId, $tags, $append );
		}

		$categories = WordPressActionHelper::parseList( $config['categories'] ?? array() );

		if ( array() !== $categories ) {
			wp_set_post_categories( $postId, array_map( 'intval', $categories ), $append );
		}
	}

	// -----------------------------------------------------------------
	// Users.
	// -----------------------------------------------------------------

	public function createUser( array $config ): array {
		$email = $this->str( $config, 'email' );
		$username = $this->str( $config, 'username' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'Email is required.', 'workflow-automate' ) );
		}

		if ( '' === $username ) {
			return WordPressActionHelper::fail( __( 'Username is required.', 'workflow-automate' ) );
		}

		if ( get_user_by( 'email', $email ) ) {
			return WordPressActionHelper::fail( __( 'A user with this email already exists.', 'workflow-automate' ) );
		}

		$autoPassword = $this->bool( $config, 'auto_password' );
		$password = $autoPassword ? wp_generate_password() : $this->str( $config, 'password' );

		if ( '' === $password ) {
			return WordPressActionHelper::fail( __( 'Password is required.', 'workflow-automate' ) );
		}

		$userRole = $this->str( $config, 'user_role' );

		if ( '' === $userRole ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
		}

		$userData = WordPressActionHelper::mapUserFields( $config );
		$userData['user_login'] = $username;
		$userData['user_email'] = $email;
		$userData['user_pass'] = $password;
		$userData['role'] = $userRole;

		$userId = wp_insert_user( $userData );

		if ( is_wp_error( $userId ) ) {
			return WordPressActionHelper::fail( $userId->get_error_message() );
		}

		foreach ( $this->keyValue( $config, 'metadata' ) as $metaKey => $metaValue ) {
			update_user_meta( $userId, $metaKey, $metaValue );
		}

		$emailNotification = $this->str( $config, 'email_notification', 'none' );

		if ( '' !== $emailNotification && 'none' !== $emailNotification ) {
			wp_new_user_notification( $userId, null, $emailNotification );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'user' => $this->fetchUserInfo( $userId ),
			)
		);
	}

	public function updateUser( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$userData = WordPressActionHelper::mapUserFields( $config );
		$userData['ID'] = $userId;

		$userRole = $this->str( $config, 'user_role' );

		if ( '' !== $userRole ) {
			$userData['role'] = $userRole;
		}

		$password = $this->str( $config, 'password' );

		if ( '' !== $password ) {
			$userData['user_pass'] = $password;
		}

		$result = wp_update_user( $userData );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		foreach ( $this->keyValue( $config, 'metadata' ) as $metaKey => $metaValue ) {
			update_user_meta( $userId, $metaKey, $metaValue );
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'user' => $this->fetchUserInfo( $userId ),
			)
		);
	}

	public function deleteUser( array $config ): array {
		$useEmail = $this->bool( $config, 'use_email' );
		$userId = $this->int( $config, 'user_id' );
		$userEmail = $this->str( $config, 'user_email' );
		$reassignUserId = $this->int( $config, 'reassign_user_id' );

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
		unset( $config );

		return WordPressActionHelper::ok( $this->fetchUsers() );
	}

	public function getAllUsersByRole( array $config ): array {
		$role = $this->str( $config, 'user_role' );

		if ( '' === $role ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			$this->fetchUsers(
				array(
					'role' => $role,
					'orderby' => 'ID',
				)
			)
		);
	}

	public function getUserById( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

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
		$email = $this->str( $config, 'user_email' );

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
		$fieldKey = $this->str( $config, 'field_key' );
		$fieldValue = $this->str( $config, 'field_value' );

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

	// -----------------------------------------------------------------
	// User metadata.
	// -----------------------------------------------------------------

	public function getUserMetadata( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

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
		$userId = $this->int( $config, 'user_id' );
		$metaKey = $this->str( $config, 'meta_key' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'workflow-automate' ) );
		}

		$metadata = $this->fetchUserMeta( $userId, $metaKey, true );

		if ( empty( $metadata ) ) {
			return WordPressActionHelper::fail( __( 'User metadata not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'meta_key' => $metaKey,
				'meta_value' => $metadata,
			)
		);
	}

	public function updateUserMetadata( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( ! get_user_by( 'ID', $userId ) ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$metadata = $this->keyValue( $config, 'metadata' );

		if ( array() === $metadata ) {
			$metaKey = $this->str( $config, 'meta_key' );

			if ( '' !== $metaKey ) {
				$metadata = array( $metaKey => $config['meta_value'] ?? '' );
			}
		}

		if ( array() === $metadata ) {
			return WordPressActionHelper::fail( __( 'Metadata is required.', 'workflow-automate' ) );
		}

		foreach ( $metadata as $metaKey => $metaValue ) {
			update_user_meta( $userId, $metaKey, $metaValue );
		}

		return WordPressActionHelper::ok( $this->fetchUserMeta( $userId ) );
	}

	// -----------------------------------------------------------------
	// Roles.
	// -----------------------------------------------------------------

	public function createRole( array $config ): array {
		$roleName = $this->str( $config, 'role_name' );
		$displayName = $this->str( $config, 'role_display_name' );
		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		if ( '' === $displayName ) {
			return WordPressActionHelper::fail( __( 'Role display name is required.', 'workflow-automate' ) );
		}

		if ( get_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role already exists.', 'workflow-automate' ) );
		}

		$role = add_role( $roleName, $displayName, array_fill_keys( $capabilities, true ) );

		if ( ! $role ) {
			return WordPressActionHelper::fail( __( 'Failed to create role.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'role_name' => $roleName ) );
	}

	public function deleteRole( array $config ): array {
		$roleName = $this->str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		if ( ! get_role( $roleName ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		remove_role( $roleName );

		return WordPressActionHelper::ok( array( 'role_name' => $roleName ) );
	}

	public function getAllRoles( array $config ): array {
		unset( $config );

		return WordPressActionHelper::ok( WordPressActionHelper::getWpRoles() );
	}

	public function manageUserRole( array $config, bool $remove = false, bool $update = false ): array {
		$userId = $this->int( $config, 'user_id' );
		$roles = WordPressActionHelper::parseList( $config['user_role'] ?? array() );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( array() === $roles ) {
			return WordPressActionHelper::fail( __( 'User role is required.', 'workflow-automate' ) );
		}

		$user = new \WP_User( $userId );

		if ( ! $user->exists() ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		if ( $update ) {
			$user->set_role( (string) array_shift( $roles ) );
		} else {
			foreach ( $roles as $role ) {
				if ( $remove && in_array( $role, $user->roles, true ) ) {
					$user->remove_role( $role );
				} elseif ( ! $remove && ! in_array( $role, $user->roles, true ) ) {
					$user->add_role( $role );
				}
			}
		}

		return WordPressActionHelper::ok(
			array(
				'user_id' => $userId,
				'roles' => $user->roles,
			)
		);
	}

	// -----------------------------------------------------------------
	// Capabilities.
	// -----------------------------------------------------------------

	public function getAllCapabilities( array $config ): array {
		unset( $config );

		$capabilityKeys = array();

		foreach ( WordPressActionHelper::getWpRoles() as $role ) {
			if ( ! empty( $role['capabilities'] ) ) {
				$capabilityKeys[] = array_keys( $role['capabilities'] );
			}
		}

		$all = array() === $capabilityKeys ? array() : array_unique( array_merge( ...$capabilityKeys ) );

		sort( $all, SORT_STRING | SORT_FLAG_CASE );

		return WordPressActionHelper::ok( array_values( $all ) );
	}

	public function getRoleCapabilities( array $config ): array {
		$roleName = $this->str( $config, 'role_name' );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		$roles = WordPressActionHelper::getWpRoles();

		if ( empty( $roles[ $roleName ] ) ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		$capabilities = array_keys( $roles[ $roleName ]['capabilities'] ?? array() );

		sort( $capabilities, SORT_STRING | SORT_FLAG_CASE );

		return WordPressActionHelper::ok( array_values( array_unique( $capabilities ) ) );
	}

	public function manageRoleCapabilities( array $config, bool $remove = false ): array {
		$roleName = $this->str( $config, 'role_name' );
		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( '' === $roleName ) {
			return WordPressActionHelper::fail( __( 'Role name is required.', 'workflow-automate' ) );
		}

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'workflow-automate' ) );
		}

		$role = get_role( $roleName );

		if ( ! $role ) {
			return WordPressActionHelper::fail( __( 'Role not found.', 'workflow-automate' ) );
		}

		foreach ( $capabilities as $capability ) {
			if ( $remove && $role->has_cap( $capability ) ) {
				$role->remove_cap( $capability );
			} elseif ( ! $remove && ! $role->has_cap( $capability ) ) {
				$role->add_cap( $capability );
			}
		}

		return WordPressActionHelper::ok( array( 'role_name' => $roleName ) );
	}

	public function getUserCapabilities( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		$user = get_userdata( $userId );

		if ( ! $user ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		$capabilityKeys = array();

		foreach ( $user->roles as $roleName ) {
			$role = get_role( $roleName );

			if ( $role && ! empty( $role->capabilities ) ) {
				$capabilityKeys[] = array_keys( $role->capabilities );
			}
		}

		$all = array() === $capabilityKeys ? array() : array_unique( array_merge( ...$capabilityKeys ) );

		sort( $all, SORT_STRING | SORT_FLAG_CASE );

		return WordPressActionHelper::ok( array_values( $all ) );
	}

	public function manageUserCapabilities( array $config, bool $remove = false ): array {
		$userId = $this->int( $config, 'user_id' );
		$capabilities = WordPressActionHelper::parseList( $config['role_capabilities'] ?? array() );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		if ( array() === $capabilities ) {
			return WordPressActionHelper::fail( __( 'Capabilities are required.', 'workflow-automate' ) );
		}

		$user = new \WP_User( $userId );

		if ( ! $user->exists() ) {
			return WordPressActionHelper::fail( __( 'User not found.', 'workflow-automate' ) );
		}

		foreach ( $capabilities as $capability ) {
			if ( $remove && $user->has_cap( $capability ) ) {
				$user->remove_cap( $capability );
			} elseif ( ! $remove && ! $user->has_cap( $capability ) ) {
				$user->add_cap( $capability );
			}
		}

		return WordPressActionHelper::ok( array( 'user_id' => $userId ) );
	}

	// -----------------------------------------------------------------
	// Posts.
	// -----------------------------------------------------------------

	public function getAllPosts( array $config ): array {
		$postType = $this->str( $config, 'post_type' );
		$status = $this->str( $config, 'post_status', 'any' );

		$posts = WordPressActionHelper::getPosts(
			'' === $postType ? null : $postType,
			null,
			null,
			'' === $status ? 'any' : $status
		);

		return WordPressActionHelper::ok( array_map( array( WordPressActionHelper::class, 'serializePost' ), $posts ) );
	}

	public function getPostById( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializePost( $post ) );
	}

	public function getPostsByPostType( array $config ): array {
		$postType = $this->str( $config, 'post_type' );

		if ( '' === $postType ) {
			return WordPressActionHelper::fail( __( 'Post type is required.', 'workflow-automate' ) );
		}

		$posts = WordPressActionHelper::getPosts( $postType );

		return WordPressActionHelper::ok( array_map( array( WordPressActionHelper::class, 'serializePost' ), $posts ) );
	}

	public function getPostsByMetadata( array $config ): array {
		$postType = $this->str( $config, 'post_type' );
		$metaKey = $this->str( $config, 'meta_key' );
		$metaValue = $this->str( $config, 'meta_value' );

		if ( '' === $postType ) {
			return WordPressActionHelper::fail( __( 'Post type is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaValue ) {
			return WordPressActionHelper::fail( __( 'Meta value is required.', 'workflow-automate' ) );
		}

		$posts = WordPressActionHelper::getPosts(
			$postType,
			array(
				array(
					'key' => $metaKey,
					'value' => $metaValue,
				),
			)
		);

		return WordPressActionHelper::ok( array_map( array( WordPressActionHelper::class, 'serializePost' ), $posts ) );
	}

	public function getPostMetadata( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( get_post_meta( $postId ) );
	}

	public function getPostMetadataByMetaKey( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$metaKey = $this->str( $config, 'meta_key' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'workflow-automate' ) );
		}

		$metadata = get_post_meta( $postId, $metaKey, true );

		if ( empty( $metadata ) ) {
			return WordPressActionHelper::fail( __( 'Post metadata not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'meta_key' => $metaKey,
				'meta_value' => $metadata,
			)
		);
	}

	public function getPostPermalink( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$permalink = get_permalink( $postId );

		if ( ! $permalink ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'permalink' => $permalink ) );
	}

	public function getPostContent( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'content' => $post->post_content ) );
	}

	public function getPostExcerpt( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'excerpt' => get_the_excerpt( $post ) ) );
	}

	public function getPostStatus( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$status = get_post_status( $postId );

		if ( false === $status ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'status' => $status ) );
	}

	public function createNewPost( array $config ): array {
		$title = $this->str( $config, 'title' );
		$postType = $this->str( $config, 'post_type', 'post' );
		$postStatus = $this->str( $config, 'post_status', 'draft' );

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'Post title is required.', 'workflow-automate' ) );
		}

		if ( '' === $postType ) {
			return WordPressActionHelper::fail( __( 'Post type is required.', 'workflow-automate' ) );
		}

		if ( '' === $postStatus ) {
			return WordPressActionHelper::fail( __( 'Post status is required.', 'workflow-automate' ) );
		}

		$postData = WordPressActionHelper::mapPostFields( $config );
		$postData['post_title'] = $title;
		$postData['post_type'] = $postType;
		$postData['post_status'] = $postStatus;

		$customFields = $this->keyValue( $config, 'custom_fields' );

		if ( array() !== $customFields ) {
			$postData['meta_input'] = $customFields;
		}

		$existing_meta = isset( $postData['meta_input'] ) && is_array( $postData['meta_input'] )
			? $postData['meta_input']
			: array();
		$postData['meta_input'] = array_merge(
			$existing_meta,
			array( WordPressActionHelper::AUTOMATED_META_KEY => '1' )
		);

		// Stamp meta before CatalogHookTrigger (priority 10) sees this save_post,
		// so the newly created post never queues another run of the same workflow.
		$marker = static function ( int $id ): void {
			WordPressActionHelper::markAutomatedPost( $id );
		};
		add_action( 'save_post', $marker, 0, 1 );

		$postId = wp_insert_post( $postData, true );

		remove_action( 'save_post', $marker, 0 );

		if ( is_wp_error( $postId ) ) {
			return WordPressActionHelper::fail( $postId->get_error_message() );
		}

		WordPressActionHelper::markAutomatedPost( (int) $postId );

		$this->applyPostTaxonomies( $postId, $config, false );

		$imageError = WordPressActionHelper::setPostFeaturedImage( $postId, $config );

		if ( null !== $imageError ) {
			return $imageError;
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	public function updateExistingPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$postData = WordPressActionHelper::mapPostFields( $config );
		$postData['ID'] = $postId;

		$customFields = $this->keyValue( $config, 'custom_fields' );

		if ( array() !== $customFields ) {
			$postData['meta_input'] = $customFields;
		}

		$result = wp_update_post( $postData, true );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		$this->applyPostTaxonomies( $postId, $config, true );

		$imageError = WordPressActionHelper::setPostFeaturedImage( $postId, $config );

		if ( null !== $imageError ) {
			return $imageError;
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	public function updatePostStatus( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$postStatus = $this->str( $config, 'post_status' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $postStatus ) {
			return WordPressActionHelper::fail( __( 'Post status is required.', 'workflow-automate' ) );
		}

		$result = wp_update_post(
			array(
				'ID' => $postId,
				'post_status' => $postStatus,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'post_status' => $postStatus,
			)
		);
	}

	public function deleteExistingPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$forceDelete = $this->bool( $config, 'force_delete' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$result = wp_delete_post( $postId, $forceDelete );

		if ( ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to delete post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	// -----------------------------------------------------------------
	// Comments.
	// -----------------------------------------------------------------

	public function getAllPostComments( array $config ): array {
		unset( $config );

		return WordPressActionHelper::ok( WordPressActionHelper::getComments() );
	}

	public function getPostComments( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::getComments( $postId ) );
	}

	public function getUserComments( array $config ): array {
		$userId = $this->int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::getComments( null, $userId ) );
	}

	public function getUserCommentsByEmail( array $config ): array {
		$email = $this->str( $config, 'user_email' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'User email is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::getComments( null, null, $email ) );
	}

	public function getCommentMetadata( array $config ): array {
		$commentId = $this->int( $config, 'comment_id' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( get_comment_meta( $commentId ) );
	}

	public function getCommentMetadataByMetaKey( array $config ): array {
		$commentId = $this->int( $config, 'comment_id' );
		$metaKey = $this->str( $config, 'meta_key' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'workflow-automate' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'workflow-automate' ) );
		}

		$metadata = get_comment_meta( $commentId, $metaKey, true );

		if ( empty( $metadata ) ) {
			return WordPressActionHelper::fail( __( 'Comment metadata not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'meta_key' => $metaKey,
				'meta_value' => $metadata,
			)
		);
	}

	public function createNewComment( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$comment = $this->str( $config, 'comment' );
		$authorName = $this->str( $config, 'author_name' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $comment ) {
			return WordPressActionHelper::fail( __( 'Comment is required.', 'workflow-automate' ) );
		}

		if ( '' === $authorName ) {
			return WordPressActionHelper::fail( __( 'Author name is required.', 'workflow-automate' ) );
		}

		$commentId = wp_new_comment( WordPressActionHelper::mapCommentFields( $config ), true );

		if ( is_wp_error( $commentId ) ) {
			return WordPressActionHelper::fail( $commentId->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'comment_id' => $commentId ) );
	}

	public function replyToComment( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$parentId = $this->int( $config, 'parent_id' );
		$comment = $this->str( $config, 'comment' );
		$authorName = $this->str( $config, 'author_name' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( $parentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Parent comment id is required.', 'workflow-automate' ) );
		}

		if ( '' === $comment ) {
			return WordPressActionHelper::fail( __( 'Comment is required.', 'workflow-automate' ) );
		}

		if ( '' === $authorName ) {
			return WordPressActionHelper::fail( __( 'Author name is required.', 'workflow-automate' ) );
		}

		$commentId = wp_new_comment( WordPressActionHelper::mapCommentFields( $config ), true );

		if ( is_wp_error( $commentId ) ) {
			return WordPressActionHelper::fail( $commentId->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'comment_id' => $commentId ) );
	}

	public function deleteExistingComment( array $config ): array {
		$commentId = $this->int( $config, 'comment_id' );
		$forceDelete = $this->bool( $config, 'force_delete' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'workflow-automate' ) );
		}

		$result = wp_delete_comment( $commentId, $forceDelete );

		if ( ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to delete comment.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'comment_id' => $commentId ) );
	}

	// -----------------------------------------------------------------
	// Post types.
	// -----------------------------------------------------------------

	public function getAllPostTypes( array $config ): array {
		unset( $config );

		$types = array_values( get_post_types( array(), 'objects' ) );

		return WordPressActionHelper::ok( array_map( static fn( $type ) => (array) $type, $types ) );
	}

	public function getPostType( array $config ): array {
		$postId = $this->int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		$type = get_post_type( $postId );

		if ( false === $type ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'post_type' => $type ) );
	}

	public function registerPostType( array $config ): array {
		$key = $this->str( $config, 'key' );
		$label = $this->str( $config, 'label' );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'workflow-automate' ) );
		}

		if ( '' === $label ) {
			return WordPressActionHelper::fail( __( 'Post type label is required.', 'workflow-automate' ) );
		}

		if ( post_type_exists( $key ) ) {
			return WordPressActionHelper::fail( __( 'Post type already exists.', 'workflow-automate' ) );
		}

		$supports = WordPressActionHelper::parseList( $config['supports'] ?? array( 'title', 'editor' ) );

		$result = register_post_type(
			$key,
			array(
				'label' => $label,
				'public' => $this->bool( $config, 'public', true ),
				'hierarchical' => $this->bool( $config, 'hierarchy' ),
				'show_ui' => $this->bool( $config, 'show_ui', true ),
				'show_in_menu' => $this->bool( $config, 'show_in_menu', true ),
				'show_in_nav_menus' => $this->bool( $config, 'show_in_nav_menu', true ),
				'show_in_admin_bar' => $this->bool( $config, 'show_in_admin_bar', true ),
				'menu_position' => $this->int( $config, 'menu_position', 20 ),
				'supports' => array() === $supports ? array( 'title', 'editor' ) : $supports,
				'description' => $this->str( $config, 'description' ),
				'rewrite' => array( 'slug' => $this->str( $config, 'rewrite_slug', $key ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'key' => $key ) );
	}

	public function unregisterPostType( array $config ): array {
		$key = $this->str( $config, 'key' );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'workflow-automate' ) );
		}

		if ( ! post_type_exists( $key ) ) {
			return WordPressActionHelper::fail( __( 'Post type not found.', 'workflow-automate' ) );
		}

		$result = unregister_post_type( $key );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'key' => $key ) );
	}

	public function addPostTypeFeatures( array $config ): array {
		$key = $this->str( $config, 'key' );
		$supports = WordPressActionHelper::parseList( $config['supports'] ?? array() );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'workflow-automate' ) );
		}

		if ( array() === $supports ) {
			return WordPressActionHelper::fail( __( 'At least one feature is required.', 'workflow-automate' ) );
		}

		if ( ! post_type_exists( $key ) ) {
			return WordPressActionHelper::fail( __( 'Post type not found.', 'workflow-automate' ) );
		}

		add_post_type_support( $key, $supports );

		return WordPressActionHelper::ok(
			array(
				'key' => $key,
				'supports' => $supports,
			)
		);
	}

	// -----------------------------------------------------------------
	// Post tags & generic taxonomy assignment.
	// -----------------------------------------------------------------

	public function addTagsToPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$tags = WordPressActionHelper::parseList( $config['tags'] ?? array() );
		$append = $this->bool( $config, 'append' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $tags ) {
			return WordPressActionHelper::fail( __( 'Tags are required.', 'workflow-automate' ) );
		}

		$result = wp_set_post_terms( $postId, $tags, 'post_tag', $append );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'tags' => $tags,
			)
		);
	}

	public function removeTagsFromPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$tags = WordPressActionHelper::parseList( $config['tags'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $tags ) {
			return WordPressActionHelper::fail( __( 'Tags are required.', 'workflow-automate' ) );
		}

		$result = wp_remove_object_terms( $postId, $tags, 'post_tag' );

		if ( is_wp_error( $result ) || ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to remove tags from post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	public function addTaxonomyToPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$taxonomy = $this->str( $config, 'taxonomy' );
		$terms = WordPressActionHelper::parseList( $config['terms'] ?? array() );
		$append = $this->bool( $config, 'append' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( array() === $terms ) {
			return WordPressActionHelper::fail( __( 'Terms are required.', 'workflow-automate' ) );
		}

		$result = wp_set_object_terms( $postId, $terms, $taxonomy, $append );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'terms' => $terms,
			)
		);
	}

	public function removeTaxonomyFromPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$taxonomy = $this->str( $config, 'taxonomy' );
		$terms = WordPressActionHelper::parseList( $config['terms'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( array() === $terms ) {
			return WordPressActionHelper::fail( __( 'Terms are required.', 'workflow-automate' ) );
		}

		$result = wp_remove_object_terms( $postId, $terms, $taxonomy );

		if ( is_wp_error( $result ) || ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to remove taxonomy from post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	// -----------------------------------------------------------------
	// Media.
	// -----------------------------------------------------------------

	public function addNewImage( array $config ): array {
		$url = $this->str( $config, 'url' );

		if ( '' === $url ) {
			return WordPressActionHelper::fail( __( 'Image URL is required.', 'workflow-automate' ) );
		}

		$title = $this->str( $config, 'title' );
		$altText = $this->str( $config, 'alt_text' );
		$caption = $this->str( $config, 'caption' );
		$description = $this->str( $config, 'description' );

		WordPressActionHelper::ensureMediaIncludes();

		$sanitizedUrl = filter_var( $url, FILTER_SANITIZE_URL );
		$imageId = media_sideload_image( $sanitizedUrl, 0, '' === $title ? null : $title, 'id' );

		if ( is_wp_error( $imageId ) ) {
			return WordPressActionHelper::fail( $imageId->get_error_message() );
		}

		$imageId = (int) $imageId;
		$attachmentUrl = wp_get_attachment_url( $imageId );

		if ( empty( $attachmentUrl ) ) {
			return WordPressActionHelper::fail( __( 'Failed to upload image.', 'workflow-automate' ) );
		}

		$updateData = array_filter(
			array(
				'ID' => $imageId,
				'post_title' => $title,
				'post_excerpt' => $caption,
				'post_content' => $description,
			)
		);

		if ( count( $updateData ) > 1 ) {
			wp_update_post( $updateData );
		}

		if ( '' !== $altText ) {
			update_post_meta( $imageId, '_wp_attachment_image_alt', $altText );
		}

		return WordPressActionHelper::ok(
			array(
				'image_id' => $imageId,
				'image_url' => $attachmentUrl,
			)
		);
	}

	public function deleteMedia( array $config ): array {
		$mediaId = $this->int( $config, 'media_id' );
		$forceDelete = $this->bool( $config, 'force_delete' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		$result = wp_delete_attachment( $mediaId, $forceDelete );

		if ( ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to delete media.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'media_id' => $mediaId ) );
	}

	public function renameMedia( array $config ): array {
		$mediaId = $this->int( $config, 'media_id' );
		$title = $this->str( $config, 'title' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'New title is required.', 'workflow-automate' ) );
		}

		$media = get_post( $mediaId );

		if ( ! $media || 'attachment' !== $media->post_type ) {
			return WordPressActionHelper::fail( __( 'Media not found.', 'workflow-automate' ) );
		}

		$result = wp_update_post(
			array(
				'ID' => $mediaId,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'media_id' => $mediaId,
				'title' => $title,
			)
		);
	}

	public function getAllMedia( array $config ): array {
		unset( $config );

		$media = WordPressActionHelper::getPosts( 'attachment', null, null, 'inherit' );

		return WordPressActionHelper::ok( array_map( array( WordPressActionHelper::class, 'serializePost' ), $media ) );
	}

	public function getMediaByTitle( array $config ): array {
		$title = $this->str( $config, 'title' );

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'Media title is required.', 'workflow-automate' ) );
		}

		$media = WordPressActionHelper::getPosts( 'attachment', null, $title, 'inherit' );

		if ( array() === $media ) {
			return WordPressActionHelper::fail( __( 'Media not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializePost( reset( $media ) ) );
	}

	public function getMediaById( array $config ): array {
		$mediaId = $this->int( $config, 'media_id' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		$media = get_post( $mediaId );

		if ( ! $media ) {
			return WordPressActionHelper::fail( __( 'Media not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializePost( $media ) );
	}

	// -----------------------------------------------------------------
	// Taxonomies.
	// -----------------------------------------------------------------

	public function getAllTaxonomies( array $config ): array {
		unset( $config );

		$taxonomies = array_values( get_taxonomies( array(), 'objects' ) );

		return WordPressActionHelper::ok( array_map( static fn( $tax ) => (array) $tax, $taxonomies ) );
	}

	public function getTaxonomy( array $config ): array {
		$taxonomy = $this->str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		$tax = get_taxonomy( $taxonomy );

		if ( ! $tax ) {
			return WordPressActionHelper::fail( __( 'Taxonomy not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( (array) $tax );
	}

	public function registerTaxonomy( array $config ): array {
		$taxonomy = $this->str( $config, 'taxonomy' );
		$name = $this->str( $config, 'name' );
		$postTypes = WordPressActionHelper::parseList( $config['post_types'] ?? array() );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( '' === $name ) {
			return WordPressActionHelper::fail( __( 'Taxonomy name is required.', 'workflow-automate' ) );
		}

		if ( array() === $postTypes ) {
			return WordPressActionHelper::fail( __( 'Post types are required.', 'workflow-automate' ) );
		}

		$result = register_taxonomy(
			$taxonomy,
			$postTypes,
			array(
				'label' => $name,
				'public' => $this->bool( $config, 'public', true ),
				'show_ui' => $this->bool( $config, 'show_ui', true ),
				'hierarchical' => $this->bool( $config, 'hierarchy' ),
				'description' => $this->str( $config, 'description' ),
				'rewrite' => array( 'slug' => $this->str( $config, 'rewrite_slug', $taxonomy ) ),
				'show_in_rest' => $this->bool( $config, 'show_in_rest', true ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'taxonomy' => $taxonomy ) );
	}

	public function unregisterTaxonomy( array $config ): array {
		$taxonomy = $this->str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return WordPressActionHelper::fail( __( 'Taxonomy not found.', 'workflow-automate' ) );
		}

		$result = unregister_taxonomy( $taxonomy );

		if ( is_wp_error( $result ) || ! $result ) {
			return WordPressActionHelper::fail( __( 'Failed to unregister taxonomy.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'taxonomy' => $taxonomy ) );
	}

	// -----------------------------------------------------------------
	// Terms (generic taxonomy).
	// -----------------------------------------------------------------

	public function getAllTerms( array $config, ?string $taxonomy = null ): array {
		return WordPressActionHelper::getTerms( $taxonomy ?? ( '' === $this->str( $config, 'taxonomy' ) ? null : $this->str( $config, 'taxonomy' ) ) );
	}

	public function getTerm( array $config ): array {
		return WordPressActionHelper::getTerm( $this->int( $config, 'term_id' ), $this->str( $config, 'taxonomy' ) );
	}

	public function getTermByField( array $config ): array {
		$fieldKey = $this->str( $config, 'field_key' );
		$fieldValue = $this->str( $config, 'field_value' );
		$taxonomy = $this->str( $config, 'taxonomy' );

		if ( '' === $fieldKey ) {
			return WordPressActionHelper::fail( __( 'Field is required.', 'workflow-automate' ) );
		}

		if ( '' === $fieldValue ) {
			return WordPressActionHelper::fail( __( 'Field value is required.', 'workflow-automate' ) );
		}

		if ( 'term_taxonomy_id' === $fieldKey && '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required when field is term_taxonomy_id.', 'workflow-automate' ) );
		}

		$term = get_term_by( $fieldKey, $fieldValue, '' === $taxonomy ? '' : $taxonomy );

		if ( ! $term ) {
			return WordPressActionHelper::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( (array) $term );
	}

	public function getTermByTaxonomy( array $config ): array {
		$taxonomy = $this->str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::getTerms( $taxonomy );
	}

	public function createNewTerm( array $config ): array {
		return WordPressActionHelper::insertTerm(
			$this->str( $config, 'name' ),
			$this->str( $config, 'taxonomy' ),
			array(
				'slug' => $this->str( $config, 'slug' ),
				'description' => $this->str( $config, 'description' ),
			)
		);
	}

	public function updateTerm( array $config ): array {
		return WordPressActionHelper::updateTerm(
			$this->int( $config, 'term_id' ),
			$this->str( $config, 'taxonomy' ),
			array(
				'name' => $this->str( $config, 'name' ),
				'slug' => $this->str( $config, 'slug' ),
				'description' => $this->str( $config, 'description' ),
			)
		);
	}

	public function deleteTerm( array $config ): array {
		return WordPressActionHelper::deleteTerm( $this->int( $config, 'term_id' ), $this->str( $config, 'taxonomy' ) );
	}

	// -----------------------------------------------------------------
	// Terms bound to a fixed taxonomy (category, post_tag, and the
	// WooCommerce product_tag/product_cat/product_type taxonomies all
	// reuse this handful of methods via the catalog's `method_args`).
	// -----------------------------------------------------------------

	public function createTermByTax( array $config, string $taxonomy ): array {
		return WordPressActionHelper::insertTerm(
			$this->str( $config, 'name' ),
			$taxonomy,
			array(
				'slug' => $this->str( $config, 'slug' ),
				'description' => $this->str( $config, 'description' ),
			)
		);
	}

	public function updateTermByTax( array $config, string $taxonomy ): array {
		return WordPressActionHelper::updateTerm(
			$this->int( $config, 'term_id' ),
			$taxonomy,
			array(
				'name' => $this->str( $config, 'name' ),
				'slug' => $this->str( $config, 'slug' ),
				'description' => $this->str( $config, 'description' ),
			)
		);
	}

	public function deleteTermByTax( array $config, string $taxonomy ): array {
		return WordPressActionHelper::deleteTerm( $this->int( $config, 'term_id' ), $taxonomy );
	}

	public function getTermById( array $config, string $taxonomy ): array {
		return WordPressActionHelper::getTerm( $this->int( $config, 'term_id' ), $taxonomy );
	}

	// -----------------------------------------------------------------
	// Category.
	// -----------------------------------------------------------------

	public function addCategoryToPost( array $config ): array {
		$postId = $this->int( $config, 'post_id' );
		$categories = WordPressActionHelper::parseList( $config['categories'] ?? array() );
		$append = $this->bool( $config, 'append' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $categories ) {
			return WordPressActionHelper::fail( __( 'Category is required.', 'workflow-automate' ) );
		}

		$result = wp_set_post_categories( $postId, array_map( 'intval', $categories ), $append );

		if ( false === $result || is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( __( 'Failed to add category to post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'categories' => $result,
			)
		);
	}

	// -----------------------------------------------------------------
	// Plugins.
	// -----------------------------------------------------------------

	public function checkPluginActivationStatus( array $config ): array {
		$pluginFile = $this->str( $config, 'plugin_file' );

		if ( '' === $pluginFile ) {
			return WordPressActionHelper::fail( __( 'Plugin file is required.', 'workflow-automate' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		return WordPressActionHelper::ok(
			array(
				'plugin_file' => $pluginFile,
				'active' => is_plugin_active( $pluginFile ),
			)
		);
	}

	public function activatePlugin( array $config ): array {
		$pluginFile = $this->str( $config, 'plugin_file' );

		if ( '' === $pluginFile ) {
			return WordPressActionHelper::fail( __( 'Plugin file is required.', 'workflow-automate' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		if ( is_plugin_active( $pluginFile ) ) {
			return WordPressActionHelper::fail( __( 'Plugin is already active.', 'workflow-automate' ) );
		}

		$result = activate_plugin( $pluginFile );

		if ( is_wp_error( $result ) ) {
			return WordPressActionHelper::fail( $result->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'plugin_file' => $pluginFile ) );
	}
}
