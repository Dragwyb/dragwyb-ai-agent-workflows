<?php
/**
 * User, Role, and Capabilities catalog definitions.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\WordPress\Catalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WordPress Action Catalog definitions for User Management.
 */
final class UserActionCatalog {

	/**
	 * @param callable(string, string, array<string, mixed>=): array<string, mixed> $field
	 * @param callable(string, string): array{value: string, label: string}         $option
	 * @param array<string, string>                                                 $groups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions( callable $field, callable $option, array $groups ): array {
		$definitions = array();

		// User management.
		$definitions[] = array(
			'slug'          => 'wp_create_user_action',
			'label'         => __( 'Create New User', 'dragwyb-visual-automation' ),
			'description'   => __( 'Creates a new WordPress user account.', 'dragwyb-visual-automation' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'createUser',
			'method_args'   => array(),
			'config_schema' => array(
				'email'              => $field( 'string', __( 'Email', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'username'           => $field( 'string', __( 'Username', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'password'           => $field( 'string', __( 'Password', 'dragwyb-visual-automation' ) ),
				'auto_password'      => $field( 'boolean', __( 'Auto-generate password', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'nickname'           => $field( 'string', __( 'Nickname', 'dragwyb-visual-automation' ) ),
				'display_name'       => $field( 'string', __( 'Display Name', 'dragwyb-visual-automation' ) ),
				'first_name'         => $field( 'string', __( 'First Name', 'dragwyb-visual-automation' ) ),
				'last_name'          => $field( 'string', __( 'Last Name', 'dragwyb-visual-automation' ) ),
				'user_url'           => $field( 'string', __( 'Website URL', 'dragwyb-visual-automation' ) ),
				'description'        => $field( 'string', __( 'Bio / Description', 'dragwyb-visual-automation' ), array( 'multiline' => true ) ),
				'user_role'          => $field(
					'string',
					__( 'User Role', 'dragwyb-visual-automation' ),
					array(
						'default' => 'subscriber',
					)
				),
				'email_notification' => $field(
					'select',
					__( 'Email Notification', 'dragwyb-visual-automation' ),
					array(
						'default' => 'none',
						'options' => array(
							$option( 'none', __( 'None', 'dragwyb-visual-automation' ) ),
							$option( 'user', __( 'Notify user only', 'dragwyb-visual-automation' ) ),
							$option( 'admin', __( 'Notify admin only', 'dragwyb-visual-automation' ) ),
							$option( 'both', __( 'Notify user and admin', 'dragwyb-visual-automation' ) ),
						),
					)
				),
				'metadata'           => $field( 'key_value', __( 'User Metadata (safe custom keys only)', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_action',
			'label'         => __( 'Update User', 'dragwyb-visual-automation' ),
			'description'   => __( 'Updates fields on an existing WordPress user.', 'dragwyb-visual-automation' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'updateUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'      => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'username'     => $field( 'string', __( 'Username', 'dragwyb-visual-automation' ) ),
				'email'        => $field( 'string', __( 'Email', 'dragwyb-visual-automation' ) ),
				'password'     => $field( 'string', __( 'New Password (leave blank to keep current)', 'dragwyb-visual-automation' ) ),
				'nickname'     => $field( 'string', __( 'Nickname', 'dragwyb-visual-automation' ) ),
				'display_name' => $field( 'string', __( 'Display Name', 'dragwyb-visual-automation' ) ),
				'first_name'   => $field( 'string', __( 'First Name', 'dragwyb-visual-automation' ) ),
				'last_name'    => $field( 'string', __( 'Last Name', 'dragwyb-visual-automation' ) ),
				'user_url'     => $field( 'string', __( 'Website URL', 'dragwyb-visual-automation' ) ),
				'description'  => $field( 'string', __( 'Bio / Description', 'dragwyb-visual-automation' ), array( 'multiline' => true ) ),
				'user_role'    => $field( 'string', __( 'User Role (leave blank to keep current)', 'dragwyb-visual-automation' ) ),
				'metadata'     => $field( 'key_value', __( 'User Metadata (safe custom keys only)', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_user_action',
			'label'         => __( 'Delete User', 'dragwyb-visual-automation' ),
			'description'   => __( 'Deletes a WordPress user, optionally reassigning their content.', 'dragwyb-visual-automation' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'deleteUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'          => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ) ),
				'use_email'        => $field( 'boolean', __( 'Find user by email instead of ID', 'dragwyb-visual-automation' ), array( 'default' => false ) ),
				'user_email'       => $field( 'string', __( 'User Email', 'dragwyb-visual-automation' ) ),
				'reassign_user_id' => $field( 'string', __( 'Reassign Content To (User ID)', 'dragwyb-visual-automation' ) ),
			),
		);

		// User retrieval.
		$definitions[] = array(
			'slug'          => 'wp_get_all_users_action',
			'label'         => __( 'Get All Users', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves WordPress users (capped; pass limit). Prefer get-by-id/email when you know the target.', 'dragwyb-visual-automation' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsers',
			'method_args'   => array(),
			'config_schema' => array(
				'limit' => $field(
					'integer',
					__( 'Limit', 'dragwyb-visual-automation' ),
					array(
						'default'     => 50,
						'description' => __( 'Max users to return (default 50, max 200).', 'dragwyb-visual-automation' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_users_by_role_action',
			'label'         => __( 'Get All Users by Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every user that has the given role.', 'dragwyb-visual-automation' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsersByRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_role' => $field( 'string', __( 'User Role', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_id_action',
			'label'         => __( 'Get User by Id', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single user by their ID.', 'dragwyb-visual-automation' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserById',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_email_action',
			'label'         => __( 'Get User by Email', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single user by their email address.', 'dragwyb-visual-automation' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByEmail',
			'method_args'   => array(),
			'config_schema' => array(
				'user_email' => $field( 'string', __( 'User Email', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_field_action',
			'label'         => __( 'Get User by Field', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single user by an arbitrary field (id, slug, email, login).', 'dragwyb-visual-automation' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByField',
			'method_args'   => array(),
			'config_schema' => array(
				'field_key'   => $field(
					'select',
					__( 'Field', 'dragwyb-visual-automation' ),
					array(
						'required' => true,
						'default'  => 'login',
						'options'  => array(
							$option( 'id', __( 'ID', 'dragwyb-visual-automation' ) ),
							$option( 'slug', __( 'Slug', 'dragwyb-visual-automation' ) ),
							$option( 'email', __( 'Email', 'dragwyb-visual-automation' ) ),
							$option( 'login', __( 'Login', 'dragwyb-visual-automation' ) ),
						),
					)
				),
				'field_value' => $field( 'string', __( 'Field Value', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		// User metadata.
		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_all_action',
			'label'         => __( 'Get User Metadata (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every metadata entry for a user.', 'dragwyb-visual-automation' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_single_action',
			'label'         => __( 'Get User Metadata (Single)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single metadata value for a user by meta key.', 'dragwyb-visual-automation' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'  => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'meta_key' => $field( 'string', __( 'Meta Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_metadata_action',
			'label'         => __( 'Update User Metadata', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds or updates safe custom metadata entries for a user. Privileged keys (capabilities, wp_*, session tokens, etc.) are rejected.', 'dragwyb-visual-automation' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'updateUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'    => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'metadata'   => $field( 'key_value', __( 'Metadata (safe custom keys only)', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key (used when Metadata is empty)', 'dragwyb-visual-automation' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
				'meta_value' => $field( 'string', __( 'Meta Value (used when Metadata is empty)', 'dragwyb-visual-automation' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- config field name for a builder UI, not a live query.
			),
		);

		// Role management.
		$definitions[] = array(
			'slug'          => 'wp_create_role_action',
			'label'         => __( 'Create Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Registers a new WordPress role with the given capabilities.', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'createRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_display_name' => $field( 'string', __( 'Role Display Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_role_action',
			'label'         => __( 'Delete Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Removes a WordPress role.', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'deleteRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_role_action',
			'label'         => __( 'Add User Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds one or more roles to a user (existing roles are kept).', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_role_action',
			'label'         => __( 'Remove User Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Removes one or more roles from a user.', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_role_action',
			'label'         => __( 'Update User Role', 'dragwyb-visual-automation' ),
			'description'   => __( 'Replaces all of a user\'s roles with a single new role.', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( false, true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'user_role' => $field( 'string', __( 'New User Role', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_roles_action',
			'label'         => __( 'Get All Roles', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every registered role and its capabilities.', 'dragwyb-visual-automation' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'getAllRoles',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		// Capabilities management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_capabilities_action',
			'label'         => __( 'Get All Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every capability defined across all roles.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getAllCapabilities',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_role_capabilities_action',
			'label'         => __( 'Get Role Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the capabilities assigned to a role.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_role_capabilities_action',
			'label'         => __( 'Add Role Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds one or more capabilities to a role.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_role_capabilities_action',
			'label'         => __( 'Remove Role Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Removes one or more capabilities from a role.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_capabilities_action',
			'label'         => __( 'Get User Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every capability a user has through their roles.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_capabilities_action',
			'label'         => __( 'Add User Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Grants one or more capabilities directly to a user.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_capabilities_action',
			'label'         => __( 'Remove User Capabilities', 'dragwyb-visual-automation' ),
			'description'   => __( 'Removes one or more capabilities directly from a user.', 'dragwyb-visual-automation' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
