<?php
/**
 * User, Role, and Capabilities catalog definitions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\WordPress\Catalog;

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
			'label'         => __( 'Create New User', 'workflow-automate' ),
			'description'   => __( 'Creates a new WordPress user account.', 'workflow-automate' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'createUser',
			'method_args'   => array(),
			'config_schema' => array(
				'email'              => $field( 'string', __( 'Email', 'workflow-automate' ), array( 'required' => true ) ),
				'username'           => $field( 'string', __( 'Username', 'workflow-automate' ), array( 'required' => true ) ),
				'password'           => $field( 'string', __( 'Password', 'workflow-automate' ) ),
				'auto_password'      => $field( 'boolean', __( 'Auto-generate password', 'workflow-automate' ), array( 'default' => false ) ),
				'nickname'           => $field( 'string', __( 'Nickname', 'workflow-automate' ) ),
				'display_name'       => $field( 'string', __( 'Display Name', 'workflow-automate' ) ),
				'first_name'         => $field( 'string', __( 'First Name', 'workflow-automate' ) ),
				'last_name'          => $field( 'string', __( 'Last Name', 'workflow-automate' ) ),
				'user_url'           => $field( 'string', __( 'Website URL', 'workflow-automate' ) ),
				'description'        => $field( 'string', __( 'Bio / Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'user_role'          => $field(
					'string',
					__( 'User Role', 'workflow-automate' ),
					array(
						'required' => true,
						'default'  => 'subscriber',
					)
				),
				'email_notification' => $field(
					'select',
					__( 'Email Notification', 'workflow-automate' ),
					array(
						'default' => 'none',
						'options' => array(
							$option( 'none', __( 'None', 'workflow-automate' ) ),
							$option( 'user', __( 'Notify user only', 'workflow-automate' ) ),
							$option( 'admin', __( 'Notify admin only', 'workflow-automate' ) ),
							$option( 'both', __( 'Notify user and admin', 'workflow-automate' ) ),
						),
					)
				),
				'metadata'           => $field( 'key_value', __( 'User Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_action',
			'label'         => __( 'Update User', 'workflow-automate' ),
			'description'   => __( 'Updates fields on an existing WordPress user.', 'workflow-automate' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'updateUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'      => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'username'     => $field( 'string', __( 'Username', 'workflow-automate' ) ),
				'email'        => $field( 'string', __( 'Email', 'workflow-automate' ) ),
				'password'     => $field( 'string', __( 'New Password (leave blank to keep current)', 'workflow-automate' ) ),
				'nickname'     => $field( 'string', __( 'Nickname', 'workflow-automate' ) ),
				'display_name' => $field( 'string', __( 'Display Name', 'workflow-automate' ) ),
				'first_name'   => $field( 'string', __( 'First Name', 'workflow-automate' ) ),
				'last_name'    => $field( 'string', __( 'Last Name', 'workflow-automate' ) ),
				'user_url'     => $field( 'string', __( 'Website URL', 'workflow-automate' ) ),
				'description'  => $field( 'string', __( 'Bio / Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'user_role'    => $field( 'string', __( 'User Role (leave blank to keep current)', 'workflow-automate' ) ),
				'metadata'     => $field( 'key_value', __( 'User Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_user_action',
			'label'         => __( 'Delete User', 'workflow-automate' ),
			'description'   => __( 'Deletes a WordPress user, optionally reassigning their content.', 'workflow-automate' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'deleteUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'          => $field( 'string', __( 'User ID', 'workflow-automate' ) ),
				'use_email'        => $field( 'boolean', __( 'Find user by email instead of ID', 'workflow-automate' ), array( 'default' => false ) ),
				'user_email'       => $field( 'string', __( 'User Email', 'workflow-automate' ) ),
				'reassign_user_id' => $field( 'string', __( 'Reassign Content To (User ID)', 'workflow-automate' ) ),
			),
		);

		// User retrieval.
		$definitions[] = array(
			'slug'          => 'wp_get_all_users_action',
			'label'         => __( 'Get All Users', 'workflow-automate' ),
			'description'   => __( 'Retrieves WordPress users (capped; pass limit). Prefer get-by-id/email when you know the target.', 'workflow-automate' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsers',
			'method_args'   => array(),
			'config_schema' => array(
				'limit' => $field(
					'integer',
					__( 'Limit', 'workflow-automate' ),
					array(
						'default'     => 50,
						'description' => __( 'Max users to return (default 50, max 200).', 'workflow-automate' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_users_by_role_action',
			'label'         => __( 'Get All Users by Role', 'workflow-automate' ),
			'description'   => __( 'Retrieves every user that has the given role.', 'workflow-automate' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsersByRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_role' => $field( 'string', __( 'User Role', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_id_action',
			'label'         => __( 'Get User by Id', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single user by their ID.', 'workflow-automate' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserById',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_email_action',
			'label'         => __( 'Get User by Email', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single user by their email address.', 'workflow-automate' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByEmail',
			'method_args'   => array(),
			'config_schema' => array(
				'user_email' => $field( 'string', __( 'User Email', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_field_action',
			'label'         => __( 'Get User by Field', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single user by an arbitrary field (id, slug, email, login).', 'workflow-automate' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByField',
			'method_args'   => array(),
			'config_schema' => array(
				'field_key'   => $field(
					'select',
					__( 'Field', 'workflow-automate' ),
					array(
						'required' => true,
						'default'  => 'login',
						'options'  => array(
							$option( 'id', __( 'ID', 'workflow-automate' ) ),
							$option( 'slug', __( 'Slug', 'workflow-automate' ) ),
							$option( 'email', __( 'Email', 'workflow-automate' ) ),
							$option( 'login', __( 'Login', 'workflow-automate' ) ),
						),
					)
				),
				'field_value' => $field( 'string', __( 'Field Value', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// User metadata.
		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_all_action',
			'label'         => __( 'Get User Metadata (All)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every metadata entry for a user.', 'workflow-automate' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_single_action',
			'label'         => __( 'Get User Metadata (Single)', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single metadata value for a user by meta key.', 'workflow-automate' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'  => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => $field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_metadata_action',
			'label'         => __( 'Update User Metadata', 'workflow-automate' ),
			'description'   => __( 'Adds or updates one or more metadata entries for a user.', 'workflow-automate' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'updateUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'    => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'metadata'   => $field( 'key_value', __( 'Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key (used when Metadata is empty)', 'workflow-automate' ) ),
				'meta_value' => $field( 'string', __( 'Meta Value (used when Metadata is empty)', 'workflow-automate' ) ),
			),
		);

		// Role management.
		$definitions[] = array(
			'slug'          => 'wp_create_role_action',
			'label'         => __( 'Create Role', 'workflow-automate' ),
			'description'   => __( 'Registers a new WordPress role with the given capabilities.', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'createRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_display_name' => $field( 'string', __( 'Role Display Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_role_action',
			'label'         => __( 'Delete Role', 'workflow-automate' ),
			'description'   => __( 'Removes a WordPress role.', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'deleteRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_role_action',
			'label'         => __( 'Add User Role', 'workflow-automate' ),
			'description'   => __( 'Adds one or more roles to a user (existing roles are kept).', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_role_action',
			'label'         => __( 'Remove User Role', 'workflow-automate' ),
			'description'   => __( 'Removes one or more roles from a user.', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_role_action',
			'label'         => __( 'Update User Role', 'workflow-automate' ),
			'description'   => __( 'Replaces all of a user\'s roles with a single new role.', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( false, true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => $field( 'string', __( 'New User Role', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_roles_action',
			'label'         => __( 'Get All Roles', 'workflow-automate' ),
			'description'   => __( 'Retrieves every registered role and its capabilities.', 'workflow-automate' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'getAllRoles',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		// Capabilities management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_capabilities_action',
			'label'         => __( 'Get All Capabilities', 'workflow-automate' ),
			'description'   => __( 'Retrieves every capability defined across all roles.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getAllCapabilities',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_role_capabilities_action',
			'label'         => __( 'Get Role Capabilities', 'workflow-automate' ),
			'description'   => __( 'Retrieves the capabilities assigned to a role.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_role_capabilities_action',
			'label'         => __( 'Add Role Capabilities', 'workflow-automate' ),
			'description'   => __( 'Adds one or more capabilities to a role.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_role_capabilities_action',
			'label'         => __( 'Remove Role Capabilities', 'workflow-automate' ),
			'description'   => __( 'Removes one or more capabilities from a role.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_capabilities_action',
			'label'         => __( 'Get User Capabilities', 'workflow-automate' ),
			'description'   => __( 'Retrieves every capability a user has through their roles.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_capabilities_action',
			'label'         => __( 'Add User Capabilities', 'workflow-automate' ),
			'description'   => __( 'Grants one or more capabilities directly to a user.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_capabilities_action',
			'label'         => __( 'Remove User Capabilities', 'workflow-automate' ),
			'description'   => __( 'Removes one or more capabilities directly from a user.', 'workflow-automate' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
