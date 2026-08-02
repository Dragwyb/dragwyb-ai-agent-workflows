<?php
/**
 * User, Role, and Capabilities catalog definitions.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\WordPress\Catalog;

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
			'label'         => __( 'Create New User', 'dragwyb-agentflow' ),
			'description'   => __( 'Creates a new WordPress user account.', 'dragwyb-agentflow' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'createUser',
			'method_args'   => array(),
			'config_schema' => array(
				'email'              => $field( 'string', __( 'Email', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'username'           => $field( 'string', __( 'Username', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'password'           => $field( 'string', __( 'Password', 'dragwyb-agentflow' ) ),
				'auto_password'      => $field( 'boolean', __( 'Auto-generate password', 'dragwyb-agentflow' ), array( 'default' => false ) ),
				'nickname'           => $field( 'string', __( 'Nickname', 'dragwyb-agentflow' ) ),
				'display_name'       => $field( 'string', __( 'Display Name', 'dragwyb-agentflow' ) ),
				'first_name'         => $field( 'string', __( 'First Name', 'dragwyb-agentflow' ) ),
				'last_name'          => $field( 'string', __( 'Last Name', 'dragwyb-agentflow' ) ),
				'user_url'           => $field( 'string', __( 'Website URL', 'dragwyb-agentflow' ) ),
				'description'        => $field( 'string', __( 'Bio / Description', 'dragwyb-agentflow' ), array( 'multiline' => true ) ),
				'user_role'          => $field(
					'string',
					__( 'User Role', 'dragwyb-agentflow' ),
					array(
						'required' => true,
						'default'  => 'subscriber',
					)
				),
				'email_notification' => $field(
					'select',
					__( 'Email Notification', 'dragwyb-agentflow' ),
					array(
						'default' => 'none',
						'options' => array(
							$option( 'none', __( 'None', 'dragwyb-agentflow' ) ),
							$option( 'user', __( 'Notify user only', 'dragwyb-agentflow' ) ),
							$option( 'admin', __( 'Notify admin only', 'dragwyb-agentflow' ) ),
							$option( 'both', __( 'Notify user and admin', 'dragwyb-agentflow' ) ),
						),
					)
				),
				'metadata'           => $field( 'key_value', __( 'User Metadata', 'dragwyb-agentflow' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_action',
			'label'         => __( 'Update User', 'dragwyb-agentflow' ),
			'description'   => __( 'Updates fields on an existing WordPress user.', 'dragwyb-agentflow' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'updateUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'      => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'username'     => $field( 'string', __( 'Username', 'dragwyb-agentflow' ) ),
				'email'        => $field( 'string', __( 'Email', 'dragwyb-agentflow' ) ),
				'password'     => $field( 'string', __( 'New Password (leave blank to keep current)', 'dragwyb-agentflow' ) ),
				'nickname'     => $field( 'string', __( 'Nickname', 'dragwyb-agentflow' ) ),
				'display_name' => $field( 'string', __( 'Display Name', 'dragwyb-agentflow' ) ),
				'first_name'   => $field( 'string', __( 'First Name', 'dragwyb-agentflow' ) ),
				'last_name'    => $field( 'string', __( 'Last Name', 'dragwyb-agentflow' ) ),
				'user_url'     => $field( 'string', __( 'Website URL', 'dragwyb-agentflow' ) ),
				'description'  => $field( 'string', __( 'Bio / Description', 'dragwyb-agentflow' ), array( 'multiline' => true ) ),
				'user_role'    => $field( 'string', __( 'User Role (leave blank to keep current)', 'dragwyb-agentflow' ) ),
				'metadata'     => $field( 'key_value', __( 'User Metadata', 'dragwyb-agentflow' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_user_action',
			'label'         => __( 'Delete User', 'dragwyb-agentflow' ),
			'description'   => __( 'Deletes a WordPress user, optionally reassigning their content.', 'dragwyb-agentflow' ),
			'group'         => 'user',
			'group_label'   => $groups['user'],
			'method'        => 'deleteUser',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'          => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ) ),
				'use_email'        => $field( 'boolean', __( 'Find user by email instead of ID', 'dragwyb-agentflow' ), array( 'default' => false ) ),
				'user_email'       => $field( 'string', __( 'User Email', 'dragwyb-agentflow' ) ),
				'reassign_user_id' => $field( 'string', __( 'Reassign Content To (User ID)', 'dragwyb-agentflow' ) ),
			),
		);

		// User retrieval.
		$definitions[] = array(
			'slug'          => 'wp_get_all_users_action',
			'label'         => __( 'Get All Users', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves WordPress users (capped; pass limit). Prefer get-by-id/email when you know the target.', 'dragwyb-agentflow' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsers',
			'method_args'   => array(),
			'config_schema' => array(
				'limit' => $field(
					'integer',
					__( 'Limit', 'dragwyb-agentflow' ),
					array(
						'default'     => 50,
						'description' => __( 'Max users to return (default 50, max 200).', 'dragwyb-agentflow' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_users_by_role_action',
			'label'         => __( 'Get All Users by Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves every user that has the given role.', 'dragwyb-agentflow' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getAllUsersByRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_role' => $field( 'string', __( 'User Role', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_id_action',
			'label'         => __( 'Get User by Id', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves a single user by their ID.', 'dragwyb-agentflow' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserById',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_email_action',
			'label'         => __( 'Get User by Email', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves a single user by their email address.', 'dragwyb-agentflow' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByEmail',
			'method_args'   => array(),
			'config_schema' => array(
				'user_email' => $field( 'string', __( 'User Email', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_by_field_action',
			'label'         => __( 'Get User by Field', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves a single user by an arbitrary field (id, slug, email, login).', 'dragwyb-agentflow' ),
			'group'         => 'user_retrieval',
			'group_label'   => $groups['user_retrieval'],
			'method'        => 'getUserByField',
			'method_args'   => array(),
			'config_schema' => array(
				'field_key'   => $field(
					'select',
					__( 'Field', 'dragwyb-agentflow' ),
					array(
						'required' => true,
						'default'  => 'login',
						'options'  => array(
							$option( 'id', __( 'ID', 'dragwyb-agentflow' ) ),
							$option( 'slug', __( 'Slug', 'dragwyb-agentflow' ) ),
							$option( 'email', __( 'Email', 'dragwyb-agentflow' ) ),
							$option( 'login', __( 'Login', 'dragwyb-agentflow' ) ),
						),
					)
				),
				'field_value' => $field( 'string', __( 'Field Value', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		// User metadata.
		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_all_action',
			'label'         => __( 'Get User Metadata (All)', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves every metadata entry for a user.', 'dragwyb-agentflow' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_metadata_single_action',
			'label'         => __( 'Get User Metadata (Single)', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves a single metadata value for a user by meta key.', 'dragwyb-agentflow' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'getUserMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'  => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'meta_key' => $field( 'string', __( 'Meta Key', 'dragwyb-agentflow' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_metadata_action',
			'label'         => __( 'Update User Metadata', 'dragwyb-agentflow' ),
			'description'   => __( 'Adds or updates one or more metadata entries for a user.', 'dragwyb-agentflow' ),
			'group'         => 'user_metadata',
			'group_label'   => $groups['user_metadata'],
			'method'        => 'updateUserMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'    => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'metadata'   => $field( 'key_value', __( 'Metadata', 'dragwyb-agentflow' ), array( 'default' => array() ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key (used when Metadata is empty)', 'dragwyb-agentflow' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
				'meta_value' => $field( 'string', __( 'Meta Value (used when Metadata is empty)', 'dragwyb-agentflow' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- config field name for a builder UI, not a live query.
			),
		);

		// Role management.
		$definitions[] = array(
			'slug'          => 'wp_create_role_action',
			'label'         => __( 'Create Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Registers a new WordPress role with the given capabilities.', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'createRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_display_name' => $field( 'string', __( 'Role Display Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-agentflow' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_role_action',
			'label'         => __( 'Delete Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Removes a WordPress role.', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'deleteRole',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_role_action',
			'label'         => __( 'Add User Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Adds one or more roles to a user (existing roles are kept).', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_role_action',
			'label'         => __( 'Remove User Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Removes one or more roles from a user.', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'user_role' => $field( 'array', __( 'User Role(s) (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_user_role_action',
			'label'         => __( 'Update User Role', 'dragwyb-agentflow' ),
			'description'   => __( 'Replaces all of a user\'s roles with a single new role.', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'manageUserRole',
			'method_args'   => array( false, true ),
			'config_schema' => array(
				'user_id'   => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'user_role' => $field( 'string', __( 'New User Role', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_roles_action',
			'label'         => __( 'Get All Roles', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves every registered role and its capabilities.', 'dragwyb-agentflow' ),
			'group'         => 'role',
			'group_label'   => $groups['role'],
			'method'        => 'getAllRoles',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		// Capabilities management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_capabilities_action',
			'label'         => __( 'Get All Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves every capability defined across all roles.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getAllCapabilities',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_role_capabilities_action',
			'label'         => __( 'Get Role Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves the capabilities assigned to a role.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name' => $field( 'string', __( 'Role Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_role_capabilities_action',
			'label'         => __( 'Add Role Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Adds one or more capabilities to a role.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_role_capabilities_action',
			'label'         => __( 'Remove Role Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Removes one or more capabilities from a role.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageRoleCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'role_name'         => $field( 'string', __( 'Role Name', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_capabilities_action',
			'label'         => __( 'Get User Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Retrieves every capability a user has through their roles.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'getUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_user_capabilities_action',
			'label'         => __( 'Add User Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Grants one or more capabilities directly to a user.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_user_capabilities_action',
			'label'         => __( 'Remove User Capabilities', 'dragwyb-agentflow' ),
			'description'   => __( 'Removes one or more capabilities directly from a user.', 'dragwyb-agentflow' ),
			'group'         => 'capabilities',
			'group_label'   => $groups['capabilities'],
			'method'        => 'manageUserCapabilities',
			'method_args'   => array( true ),
			'config_schema' => array(
				'user_id'           => $field( 'string', __( 'User ID', 'dragwyb-agentflow' ), array( 'required' => true ) ),
				'role_capabilities' => $field( 'array', __( 'Capabilities (comma-separated)', 'dragwyb-agentflow' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
