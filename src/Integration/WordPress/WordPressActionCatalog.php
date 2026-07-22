<?php
/**
 * Static catalog of every built-in WordPress workflow action.
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
 * Declarative list of every WordPress action node type: its slug, label,
 * group, the `WordPressServices` method it dispatches to, and its config
 * schema. `WordPressActionRegistrar` turns each entry into a
 * `WordPressCatalogAction`.
 */
final class WordPressActionCatalog {

	/**
	 * @param string               $type  Field type (string, boolean, object, key_value, select, array).
	 * @param string               $label Field label (already translated).
	 * @param array<string, mixed> $extra Additional schema keys (required, default, options, multiline, ...).
	 *
	 * @return array<string, mixed>
	 */
	private static function field( string $type, string $label, array $extra = array() ): array {
		return array_merge( array( 'type' => $type, 'label' => $label ), $extra );
	}

	/**
	 * @return array{value: string, label: string}
	 */
	private static function option( string $value, string $label ): array {
		return array( 'value' => $value, 'label' => $label );
	}

	/**
	 * @return array<int, array{slug: string, label: string, description: string, group: string, group_label: string, method: string, method_args: array<int, mixed>, config_schema: array<string, mixed>}>
	 */
	public static function definitions(): array {
		$groups = array(
			'user' => __( 'User Management', 'workflow-automate' ),
			'user_retrieval' => __( 'User Retrieval', 'workflow-automate' ),
			'user_metadata' => __( 'User Metadata', 'workflow-automate' ),
			'role' => __( 'Role Management', 'workflow-automate' ),
			'capabilities' => __( 'Capabilities Management', 'workflow-automate' ),
			'post' => __( 'Post Management', 'workflow-automate' ),
			'comment' => __( 'Comment Management', 'workflow-automate' ),
			'post_type' => __( 'Post Type Management', 'workflow-automate' ),
			'post_tag' => __( 'Post Tag Management', 'workflow-automate' ),
			'media' => __( 'Media Management', 'workflow-automate' ),
			'term' => __( 'Term Management', 'workflow-automate' ),
			'taxonomy' => __( 'Taxonomy Management', 'workflow-automate' ),
			'category' => __( 'Category Management', 'workflow-automate' ),
			'product_tag' => __( 'Product Tag Management', 'workflow-automate' ),
			'product_category' => __( 'Product Category Management', 'workflow-automate' ),
			'product_type' => __( 'Product Type Management', 'workflow-automate' ),
			'plugin' => __( 'Plugin Management', 'workflow-automate' ),
		);

		// Reused across the create/update/delete/get-all/get-single quintets
		// for category, post tag, and the WooCommerce product_cat/
		// product_tag/product_type taxonomies (all plain `wp_terms` rows).
		$termFields = array(
			'name' => self::field( 'string', __( 'Name', 'workflow-automate' ), array( 'required' => true ) ),
			'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
			'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
		);

		$termUpdateFields = array(
			'term_id' => self::field( 'string', __( 'Term ID', 'workflow-automate' ), array( 'required' => true ) ),
			'name' => self::field( 'string', __( 'Name', 'workflow-automate' ) ),
			'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
			'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
		);

		$termIdField = array(
			'term_id' => self::field( 'string', __( 'Term ID', 'workflow-automate' ), array( 'required' => true ) ),
		);

		$definitions = array();

		// ---------------------------------------------------------------
		// User management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_create_user_action',
			'label' => __( 'Create New User', 'workflow-automate' ),
			'description' => __( 'Creates a new WordPress user account.', 'workflow-automate' ),
			'group' => 'user',
			'group_label' => $groups['user'],
			'method' => 'createUser',
			'method_args' => array(),
			'config_schema' => array(
				'email' => self::field( 'string', __( 'Email', 'workflow-automate' ), array( 'required' => true ) ),
				'username' => self::field( 'string', __( 'Username', 'workflow-automate' ), array( 'required' => true ) ),
				'password' => self::field( 'string', __( 'Password', 'workflow-automate' ) ),
				'auto_password' => self::field( 'boolean', __( 'Auto-generate password', 'workflow-automate' ), array( 'default' => false ) ),
				'nickname' => self::field( 'string', __( 'Nickname', 'workflow-automate' ) ),
				'display_name' => self::field( 'string', __( 'Display Name', 'workflow-automate' ) ),
				'first_name' => self::field( 'string', __( 'First Name', 'workflow-automate' ) ),
				'last_name' => self::field( 'string', __( 'Last Name', 'workflow-automate' ) ),
				'user_url' => self::field( 'string', __( 'Website URL', 'workflow-automate' ) ),
				'description' => self::field( 'string', __( 'Bio / Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'user_role' => self::field( 'string', __( 'User Role', 'workflow-automate' ), array( 'required' => true, 'default' => 'subscriber' ) ),
				'email_notification' => self::field(
					'select',
					__( 'Email Notification', 'workflow-automate' ),
					array(
						'default' => 'none',
						'options' => array(
							self::option( 'none', __( 'None', 'workflow-automate' ) ),
							self::option( 'user', __( 'Notify user only', 'workflow-automate' ) ),
							self::option( 'admin', __( 'Notify admin only', 'workflow-automate' ) ),
							self::option( 'both', __( 'Notify user and admin', 'workflow-automate' ) ),
						),
					)
				),
				'metadata' => self::field( 'key_value', __( 'User Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_user_action',
			'label' => __( 'Update User', 'workflow-automate' ),
			'description' => __( 'Updates fields on an existing WordPress user.', 'workflow-automate' ),
			'group' => 'user',
			'group_label' => $groups['user'],
			'method' => 'updateUser',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'username' => self::field( 'string', __( 'Username', 'workflow-automate' ) ),
				'email' => self::field( 'string', __( 'Email', 'workflow-automate' ) ),
				'password' => self::field( 'string', __( 'New Password (leave blank to keep current)', 'workflow-automate' ) ),
				'nickname' => self::field( 'string', __( 'Nickname', 'workflow-automate' ) ),
				'display_name' => self::field( 'string', __( 'Display Name', 'workflow-automate' ) ),
				'first_name' => self::field( 'string', __( 'First Name', 'workflow-automate' ) ),
				'last_name' => self::field( 'string', __( 'Last Name', 'workflow-automate' ) ),
				'user_url' => self::field( 'string', __( 'Website URL', 'workflow-automate' ) ),
				'description' => self::field( 'string', __( 'Bio / Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'user_role' => self::field( 'string', __( 'User Role (leave blank to keep current)', 'workflow-automate' ) ),
				'metadata' => self::field( 'key_value', __( 'User Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_user_action',
			'label' => __( 'Delete User', 'workflow-automate' ),
			'description' => __( 'Deletes a WordPress user, optionally reassigning their content.', 'workflow-automate' ),
			'group' => 'user',
			'group_label' => $groups['user'],
			'method' => 'deleteUser',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ) ),
				'use_email' => self::field( 'boolean', __( 'Find user by email instead of ID', 'workflow-automate' ), array( 'default' => false ) ),
				'user_email' => self::field( 'string', __( 'User Email', 'workflow-automate' ) ),
				'reassign_user_id' => self::field( 'string', __( 'Reassign Content To (User ID)', 'workflow-automate' ) ),
			),
		);

		// ---------------------------------------------------------------
		// User retrieval.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_users_action',
			'label' => __( 'Get All Users', 'workflow-automate' ),
			'description' => __( 'Retrieves every WordPress user.', 'workflow-automate' ),
			'group' => 'user_retrieval',
			'group_label' => $groups['user_retrieval'],
			'method' => 'getAllUsers',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_users_by_role_action',
			'label' => __( 'Get All Users by Role', 'workflow-automate' ),
			'description' => __( 'Retrieves every user that has the given role.', 'workflow-automate' ),
			'group' => 'user_retrieval',
			'group_label' => $groups['user_retrieval'],
			'method' => 'getAllUsersByRole',
			'method_args' => array(),
			'config_schema' => array(
				'user_role' => self::field( 'string', __( 'User Role', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_by_id_action',
			'label' => __( 'Get User by Id', 'workflow-automate' ),
			'description' => __( 'Retrieves a single user by their ID.', 'workflow-automate' ),
			'group' => 'user_retrieval',
			'group_label' => $groups['user_retrieval'],
			'method' => 'getUserById',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_by_email_action',
			'label' => __( 'Get User by Email', 'workflow-automate' ),
			'description' => __( 'Retrieves a single user by their email address.', 'workflow-automate' ),
			'group' => 'user_retrieval',
			'group_label' => $groups['user_retrieval'],
			'method' => 'getUserByEmail',
			'method_args' => array(),
			'config_schema' => array(
				'user_email' => self::field( 'string', __( 'User Email', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_by_field_action',
			'label' => __( 'Get User by Field', 'workflow-automate' ),
			'description' => __( 'Retrieves a single user by an arbitrary field (id, slug, email, login).', 'workflow-automate' ),
			'group' => 'user_retrieval',
			'group_label' => $groups['user_retrieval'],
			'method' => 'getUserByField',
			'method_args' => array(),
			'config_schema' => array(
				'field_key' => self::field(
					'select',
					__( 'Field', 'workflow-automate' ),
					array(
						'required' => true,
						'default' => 'login',
						'options' => array(
							self::option( 'id', __( 'ID', 'workflow-automate' ) ),
							self::option( 'slug', __( 'Slug', 'workflow-automate' ) ),
							self::option( 'email', __( 'Email', 'workflow-automate' ) ),
							self::option( 'login', __( 'Login', 'workflow-automate' ) ),
						),
					)
				),
				'field_value' => self::field( 'string', __( 'Field Value', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// User metadata.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_user_metadata_all_action',
			'label' => __( 'Get User Metadata (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every metadata entry for a user.', 'workflow-automate' ),
			'group' => 'user_metadata',
			'group_label' => $groups['user_metadata'],
			'method' => 'getUserMetadata',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_metadata_single_action',
			'label' => __( 'Get User Metadata (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single metadata value for a user by meta key.', 'workflow-automate' ),
			'group' => 'user_metadata',
			'group_label' => $groups['user_metadata'],
			'method' => 'getUserMetadataByMetaKey',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => self::field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_user_metadata_action',
			'label' => __( 'Update User Metadata', 'workflow-automate' ),
			'description' => __( 'Adds or updates one or more metadata entries for a user.', 'workflow-automate' ),
			'group' => 'user_metadata',
			'group_label' => $groups['user_metadata'],
			'method' => 'updateUserMetadata',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'metadata' => self::field( 'key_value', __( 'Metadata', 'workflow-automate' ), array( 'default' => array() ) ),
				'meta_key' => self::field( 'string', __( 'Meta Key (used when Metadata is empty)', 'workflow-automate' ) ),
				'meta_value' => self::field( 'string', __( 'Meta Value (used when Metadata is empty)', 'workflow-automate' ) ),
			),
		);

		// ---------------------------------------------------------------
		// Role management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_create_role_action',
			'label' => __( 'Create Role', 'workflow-automate' ),
			'description' => __( 'Registers a new WordPress role with the given capabilities.', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'createRole',
			'method_args' => array(),
			'config_schema' => array(
				'role_name' => self::field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_display_name' => self::field( 'string', __( 'Role Display Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => self::field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'default' => array() ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_role_action',
			'label' => __( 'Delete Role', 'workflow-automate' ),
			'description' => __( 'Removes a WordPress role.', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'deleteRole',
			'method_args' => array(),
			'config_schema' => array(
				'role_name' => self::field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_add_user_role_action',
			'label' => __( 'Add User Role', 'workflow-automate' ),
			'description' => __( 'Adds one or more roles to a user (existing roles are kept).', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'manageUserRole',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => self::field( 'array', __( 'User Role(s) (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_remove_user_role_action',
			'label' => __( 'Remove User Role', 'workflow-automate' ),
			'description' => __( 'Removes one or more roles from a user.', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'manageUserRole',
			'method_args' => array( true ),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => self::field( 'array', __( 'User Role(s) (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_user_role_action',
			'label' => __( 'Update User Role', 'workflow-automate' ),
			'description' => __( 'Replaces all of a user\'s roles with a single new role.', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'manageUserRole',
			'method_args' => array( false, true ),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'user_role' => self::field( 'string', __( 'New User Role', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_all_roles_action',
			'label' => __( 'Get All Roles', 'workflow-automate' ),
			'description' => __( 'Retrieves every registered role and its capabilities.', 'workflow-automate' ),
			'group' => 'role',
			'group_label' => $groups['role'],
			'method' => 'getAllRoles',
			'method_args' => array(),
			'config_schema' => array(),
		);

		// ---------------------------------------------------------------
		// Capabilities management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_capabilities_action',
			'label' => __( 'Get All Capabilities', 'workflow-automate' ),
			'description' => __( 'Retrieves every capability defined across all roles.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'getAllCapabilities',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_role_capabilities_action',
			'label' => __( 'Get Role Capabilities', 'workflow-automate' ),
			'description' => __( 'Retrieves the capabilities assigned to a role.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'getRoleCapabilities',
			'method_args' => array(),
			'config_schema' => array(
				'role_name' => self::field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_add_role_capabilities_action',
			'label' => __( 'Add Role Capabilities', 'workflow-automate' ),
			'description' => __( 'Adds one or more capabilities to a role.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'manageRoleCapabilities',
			'method_args' => array(),
			'config_schema' => array(
				'role_name' => self::field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => self::field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_remove_role_capabilities_action',
			'label' => __( 'Remove Role Capabilities', 'workflow-automate' ),
			'description' => __( 'Removes one or more capabilities from a role.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'manageRoleCapabilities',
			'method_args' => array( true ),
			'config_schema' => array(
				'role_name' => self::field( 'string', __( 'Role Name', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => self::field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_capabilities_action',
			'label' => __( 'Get User Capabilities', 'workflow-automate' ),
			'description' => __( 'Retrieves every capability a user has through their roles.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'getUserCapabilities',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_add_user_capabilities_action',
			'label' => __( 'Add User Capabilities', 'workflow-automate' ),
			'description' => __( 'Grants one or more capabilities directly to a user.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'manageUserCapabilities',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => self::field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_remove_user_capabilities_action',
			'label' => __( 'Remove User Capabilities', 'workflow-automate' ),
			'description' => __( 'Removes one or more capabilities directly from a user.', 'workflow-automate' ),
			'group' => 'capabilities',
			'group_label' => $groups['capabilities'],
			'method' => 'manageUserCapabilities',
			'method_args' => array( true ),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
				'role_capabilities' => self::field( 'array', __( 'Capabilities (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Post management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_posts_action',
			'label' => __( 'Get Post (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every post, optionally filtered by type and status.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getAllPosts',
			'method_args' => array(),
			'config_schema' => array(
				'post_type' => self::field( 'string', __( 'Post Type (optional)', 'workflow-automate' ) ),
				'post_status' => self::field( 'string', __( 'Post Status (optional, default any)', 'workflow-automate' ), array( 'default' => 'any' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_by_id_action',
			'label' => __( 'Get Post (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single post by its ID.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostById',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_posts_by_post_type_action',
			'label' => __( 'Get Posts By Post Type', 'workflow-automate' ),
			'description' => __( 'Retrieves every post of a given post type.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostsByPostType',
			'method_args' => array(),
			'config_schema' => array(
				'post_type' => self::field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_posts_by_metadata_action',
			'label' => __( 'Get Posts by Metadata', 'workflow-automate' ),
			'description' => __( 'Retrieves posts of a given type matching a meta key/value pair.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostsByMetadata',
			'method_args' => array(),
			'config_schema' => array(
				'post_type' => self::field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => self::field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_value' => self::field( 'string', __( 'Meta Value', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_metadata_all_action',
			'label' => __( 'Get Post Metadata (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every metadata entry for a post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostMetadata',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_metadata_single_action',
			'label' => __( 'Get Post Metadata (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single metadata value for a post by meta key.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostMetadataByMetaKey',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => self::field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_permalink_action',
			'label' => __( 'Get Post Permalink', 'workflow-automate' ),
			'description' => __( 'Retrieves the public URL of a post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostPermalink',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_content_action',
			'label' => __( 'Get Post Content', 'workflow-automate' ),
			'description' => __( 'Retrieves the raw content of a post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostContent',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_excerpt_action',
			'label' => __( 'Get Post Excerpt', 'workflow-automate' ),
			'description' => __( 'Retrieves the excerpt of a post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostExcerpt',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_status_action',
			'label' => __( 'Get Post Status', 'workflow-automate' ),
			'description' => __( 'Retrieves the status of a post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'getPostStatus',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$postStatusOptions = array(
			self::option( 'draft', __( 'Draft', 'workflow-automate' ) ),
			self::option( 'publish', __( 'Published', 'workflow-automate' ) ),
			self::option( 'pending', __( 'Pending Review', 'workflow-automate' ) ),
			self::option( 'private', __( 'Private', 'workflow-automate' ) ),
			self::option( 'future', __( 'Scheduled', 'workflow-automate' ) ),
		);

		$definitions[] = array(
			'slug' => 'wp_create_post_action',
			'label' => __( 'Create New Post', 'workflow-automate' ),
			'description' => __( 'Creates a new post of any post type.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'createNewPost',
			'method_args' => array(),
			'config_schema' => array(
				'title' => self::field( 'string', __( 'Title', 'workflow-automate' ), array( 'required' => true ) ),
				'content' => self::field( 'string', __( 'Content', 'workflow-automate' ), array( 'multiline' => true ) ),
				'excerpt' => self::field( 'string', __( 'Excerpt', 'workflow-automate' ), array( 'multiline' => true ) ),
				'post_type' => self::field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'required' => true, 'default' => 'post' ) ),
				'post_status' => self::field( 'select', __( 'Post Status', 'workflow-automate' ), array( 'required' => true, 'default' => 'draft', 'options' => $postStatusOptions ) ),
				'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'date' => self::field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'date_gmt' => self::field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'parent_id' => self::field( 'string', __( 'Parent Post ID', 'workflow-automate' ) ),
				'post_password' => self::field( 'string', __( 'Post Password', 'workflow-automate' ) ),
				'post_author' => self::field( 'string', __( 'Author (User ID)', 'workflow-automate' ) ),
				'categories' => self::field( 'array', __( 'Categories (comma-separated category IDs)', 'workflow-automate' ) ),
				'tags' => self::field( 'array', __( 'Tags (comma-separated)', 'workflow-automate' ) ),
				'taxonomy' => self::field( 'string', __( 'Custom Taxonomy (optional)', 'workflow-automate' ) ),
				'terms' => self::field( 'array', __( 'Custom Taxonomy Terms (comma-separated)', 'workflow-automate' ) ),
				'custom_fields' => self::field( 'key_value', __( 'Custom Fields', 'workflow-automate' ), array( 'default' => array() ) ),
				'featured_image' => self::field( 'string', __( 'Featured Image URL', 'workflow-automate' ) ),
				'featured_image_id' => self::field( 'string', __( 'Featured Image Attachment ID', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_post_action',
			'label' => __( 'Update Post', 'workflow-automate' ),
			'description' => __( 'Updates fields on an existing post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'updateExistingPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'title' => self::field( 'string', __( 'Title', 'workflow-automate' ) ),
				'content' => self::field( 'string', __( 'Content', 'workflow-automate' ), array( 'multiline' => true ) ),
				'excerpt' => self::field( 'string', __( 'Excerpt', 'workflow-automate' ), array( 'multiline' => true ) ),
				'post_type' => self::field( 'string', __( 'Post Type', 'workflow-automate' ) ),
				'post_status' => self::field( 'select', __( 'Post Status', 'workflow-automate' ), array( 'default' => '', 'options' => $postStatusOptions ) ),
				'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'date' => self::field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'date_gmt' => self::field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'parent_id' => self::field( 'string', __( 'Parent Post ID', 'workflow-automate' ) ),
				'post_password' => self::field( 'string', __( 'Post Password', 'workflow-automate' ) ),
				'post_author' => self::field( 'string', __( 'Author (User ID)', 'workflow-automate' ) ),
				'categories' => self::field( 'array', __( 'Categories (comma-separated category IDs)', 'workflow-automate' ) ),
				'tags' => self::field( 'array', __( 'Tags (comma-separated)', 'workflow-automate' ) ),
				'taxonomy' => self::field( 'string', __( 'Custom Taxonomy (optional)', 'workflow-automate' ) ),
				'terms' => self::field( 'array', __( 'Custom Taxonomy Terms (comma-separated)', 'workflow-automate' ) ),
				'custom_fields' => self::field( 'key_value', __( 'Custom Fields', 'workflow-automate' ), array( 'default' => array() ) ),
				'featured_image' => self::field( 'string', __( 'Featured Image URL', 'workflow-automate' ) ),
				'featured_image_id' => self::field( 'string', __( 'Featured Image Attachment ID', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_post_status_action',
			'label' => __( 'Update Post Status', 'workflow-automate' ),
			'description' => __( 'Changes only the status of an existing post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'updatePostStatus',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'post_status' => self::field( 'select', __( 'Post Status', 'workflow-automate' ), array( 'required' => true, 'default' => 'publish', 'options' => $postStatusOptions ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_post_action',
			'label' => __( 'Delete Post', 'workflow-automate' ),
			'description' => __( 'Deletes (or trashes) an existing post.', 'workflow-automate' ),
			'group' => 'post',
			'group_label' => $groups['post'],
			'method' => 'deleteExistingPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'force_delete' => self::field( 'boolean', __( 'Force Delete (skip trash)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		// ---------------------------------------------------------------
		// Comment management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_post_comments_action',
			'label' => __( 'Get Post Comments (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every comment across the whole site.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getAllPostComments',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_comments_action',
			'label' => __( 'Get Post Comments (Single Post)', 'workflow-automate' ),
			'description' => __( 'Retrieves every comment on a specific post.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getPostComments',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_comments_action',
			'label' => __( 'Get User Comments', 'workflow-automate' ),
			'description' => __( 'Retrieves every comment authored by a registered user.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getUserComments',
			'method_args' => array(),
			'config_schema' => array(
				'user_id' => self::field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_user_comments_by_email_action',
			'label' => __( 'Get User Comments (By Email)', 'workflow-automate' ),
			'description' => __( 'Retrieves every comment authored using a given email address.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getUserCommentsByEmail',
			'method_args' => array(),
			'config_schema' => array(
				'user_email' => self::field( 'string', __( 'User Email', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_comment_metadata_all_action',
			'label' => __( 'Get Comment Metadata (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every metadata entry for a comment.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getCommentMetadata',
			'method_args' => array(),
			'config_schema' => array(
				'comment_id' => self::field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_comment_metadata_single_action',
			'label' => __( 'Get Comment Metadata (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single metadata value for a comment by meta key.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'getCommentMetadataByMetaKey',
			'method_args' => array(),
			'config_schema' => array(
				'comment_id' => self::field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => self::field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_create_comment_action',
			'label' => __( 'Create New Comment', 'workflow-automate' ),
			'description' => __( 'Adds a new top-level comment to a post.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'createNewComment',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'comment' => self::field( 'string', __( 'Comment', 'workflow-automate' ), array( 'required' => true, 'multiline' => true ) ),
				'author_name' => self::field( 'string', __( 'Author Name', 'workflow-automate' ), array( 'required' => true ) ),
				'author_email' => self::field( 'string', __( 'Author Email', 'workflow-automate' ) ),
				'author_url' => self::field( 'string', __( 'Author URL', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_reply_to_comment_action',
			'label' => __( 'Reply To Comment', 'workflow-automate' ),
			'description' => __( 'Adds a reply underneath an existing comment.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'replyToComment',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'parent_id' => self::field( 'string', __( 'Parent Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'comment' => self::field( 'string', __( 'Comment', 'workflow-automate' ), array( 'required' => true, 'multiline' => true ) ),
				'author_name' => self::field( 'string', __( 'Author Name', 'workflow-automate' ), array( 'required' => true ) ),
				'author_email' => self::field( 'string', __( 'Author Email', 'workflow-automate' ) ),
				'author_url' => self::field( 'string', __( 'Author URL', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_comment_action',
			'label' => __( 'Delete Comment', 'workflow-automate' ),
			'description' => __( 'Deletes (or trashes) an existing comment.', 'workflow-automate' ),
			'group' => 'comment',
			'group_label' => $groups['comment'],
			'method' => 'deleteExistingComment',
			'method_args' => array(),
			'config_schema' => array(
				'comment_id' => self::field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'force_delete' => self::field( 'boolean', __( 'Force Delete (skip trash)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		// ---------------------------------------------------------------
		// Post type management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_post_types_action',
			'label' => __( 'Get Post Type (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every registered post type.', 'workflow-automate' ),
			'group' => 'post_type',
			'group_label' => $groups['post_type'],
			'method' => 'getAllPostTypes',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_type_action',
			'label' => __( 'Get Post Type (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves the post type slug of a given post.', 'workflow-automate' ),
			'group' => 'post_type',
			'group_label' => $groups['post_type'],
			'method' => 'getPostType',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_register_post_type_action',
			'label' => __( 'Register Post Type', 'workflow-automate' ),
			'description' => __( 'Registers a new custom post type.', 'workflow-automate' ),
			'group' => 'post_type',
			'group_label' => $groups['post_type'],
			'method' => 'registerPostType',
			'method_args' => array(),
			'config_schema' => array(
				'key' => self::field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
				'label' => self::field( 'string', __( 'Label', 'workflow-automate' ), array( 'required' => true ) ),
				'hierarchy' => self::field( 'boolean', __( 'Hierarchical', 'workflow-automate' ), array( 'default' => false ) ),
				'public' => self::field( 'boolean', __( 'Public', 'workflow-automate' ), array( 'default' => true ) ),
				'show_ui' => self::field( 'boolean', __( 'Show Admin UI', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_menu' => self::field( 'boolean', __( 'Show in Admin Menu', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_nav_menu' => self::field( 'boolean', __( 'Show in Nav Menus', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_admin_bar' => self::field( 'boolean', __( 'Show in Admin Bar', 'workflow-automate' ), array( 'default' => true ) ),
				'menu_position' => self::field( 'string', __( 'Menu Position', 'workflow-automate' ) ),
				'supports' => self::field( 'array', __( 'Supports (comma-separated, e.g. title,editor,thumbnail)', 'workflow-automate' ), array( 'default' => array( 'title', 'editor' ) ) ),
				'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'rewrite_slug' => self::field( 'string', __( 'Custom URL Slug', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_unregister_post_type_action',
			'label' => __( 'Unregister Post Type', 'workflow-automate' ),
			'description' => __( 'Unregisters a previously registered custom post type.', 'workflow-automate' ),
			'group' => 'post_type',
			'group_label' => $groups['post_type'],
			'method' => 'unregisterPostType',
			'method_args' => array(),
			'config_schema' => array(
				'key' => self::field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_add_post_type_features_action',
			'label' => __( 'Add Post Type Features (Support)', 'workflow-automate' ),
			'description' => __( 'Adds support for one or more features (e.g. thumbnail) to a post type.', 'workflow-automate' ),
			'group' => 'post_type',
			'group_label' => $groups['post_type'],
			'method' => 'addPostTypeFeatures',
			'method_args' => array(),
			'config_schema' => array(
				'key' => self::field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
				'supports' => self::field( 'array', __( 'Features (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Post tag management (+ generic taxonomy/tag assignment).
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_create_post_tag_action',
			'label' => __( 'Create Post Tag', 'workflow-automate' ),
			'description' => __( 'Creates a new post tag term.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'createTermByTax',
			'method_args' => array( 'post_tag' ),
			'config_schema' => $termFields,
		);

		$definitions[] = array(
			'slug' => 'wp_update_post_tag_action',
			'label' => __( 'Update Post Tag', 'workflow-automate' ),
			'description' => __( 'Updates an existing post tag term.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'updateTermByTax',
			'method_args' => array( 'post_tag' ),
			'config_schema' => $termUpdateFields,
		);

		$definitions[] = array(
			'slug' => 'wp_delete_post_tag_action',
			'label' => __( 'Delete Post Tag', 'workflow-automate' ),
			'description' => __( 'Deletes an existing post tag term.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'deleteTermByTax',
			'method_args' => array( 'post_tag' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug' => 'wp_get_all_post_tags_action',
			'label' => __( 'Get Post Tag (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every post tag term.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'getAllTerms',
			'method_args' => array( 'post_tag' ),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_post_tag_action',
			'label' => __( 'Get Post Tag (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single post tag term by ID.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'getTermById',
			'method_args' => array( 'post_tag' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug' => 'wp_add_tags_to_post_action',
			'label' => __( 'Add Tags to Post', 'workflow-automate' ),
			'description' => __( 'Assigns one or more tags to a post.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'addTagsToPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'tags' => self::field( 'string', __( 'Tags (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
				'append' => self::field( 'boolean', __( 'Append (keep existing tags)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_remove_tags_from_post_action',
			'label' => __( 'Remove Tags From Post', 'workflow-automate' ),
			'description' => __( 'Removes one or more tags from a post.', 'workflow-automate' ),
			'group' => 'post_tag',
			'group_label' => $groups['post_tag'],
			'method' => 'removeTagsFromPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'tags' => self::field( 'string', __( 'Tags (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Media management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_add_new_image_action',
			'label' => __( 'Add New Image To Media Library', 'workflow-automate' ),
			'description' => __( 'Sideloads a remote image URL into the media library.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'addNewImage',
			'method_args' => array(),
			'config_schema' => array(
				'url' => self::field( 'string', __( 'Image URL', 'workflow-automate' ), array( 'required' => true ) ),
				'title' => self::field( 'string', __( 'Title', 'workflow-automate' ) ),
				'alt_text' => self::field( 'string', __( 'Alt Text', 'workflow-automate' ) ),
				'caption' => self::field( 'string', __( 'Caption', 'workflow-automate' ) ),
				'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_media_action',
			'label' => __( 'Delete Media From Media Library', 'workflow-automate' ),
			'description' => __( 'Deletes an item from the media library.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'deleteMedia',
			'method_args' => array(),
			'config_schema' => array(
				'media_id' => self::field( 'string', __( 'Media ID', 'workflow-automate' ), array( 'required' => true ) ),
				'force_delete' => self::field( 'boolean', __( 'Force Delete (skip trash)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_rename_media_action',
			'label' => __( 'Rename Media', 'workflow-automate' ),
			'description' => __( 'Renames an item in the media library.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'renameMedia',
			'method_args' => array(),
			'config_schema' => array(
				'media_id' => self::field( 'string', __( 'Media ID', 'workflow-automate' ), array( 'required' => true ) ),
				'title' => self::field( 'string', __( 'New Title', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_all_media_action',
			'label' => __( 'Get Media (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every item in the media library.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'getAllMedia',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_media_by_title_action',
			'label' => __( 'Get Media (By Title)', 'workflow-automate' ),
			'description' => __( 'Retrieves a media library item by its title.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'getMediaByTitle',
			'method_args' => array(),
			'config_schema' => array(
				'title' => self::field( 'string', __( 'Title', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_media_by_id_action',
			'label' => __( 'Get Media (By Id)', 'workflow-automate' ),
			'description' => __( 'Retrieves a media library item by its ID.', 'workflow-automate' ),
			'group' => 'media',
			'group_label' => $groups['media'],
			'method' => 'getMediaById',
			'method_args' => array(),
			'config_schema' => array(
				'media_id' => self::field( 'string', __( 'Media ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Taxonomy management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_taxonomies_action',
			'label' => __( 'Get Taxonomy (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every registered taxonomy.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'getAllTaxonomies',
			'method_args' => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_taxonomy_action',
			'label' => __( 'Get Taxonomy (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single registered taxonomy by slug.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'getTaxonomy',
			'method_args' => array(),
			'config_schema' => array(
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_register_taxonomy_action',
			'label' => __( 'Register Taxonomy', 'workflow-automate' ),
			'description' => __( 'Registers a new custom taxonomy for one or more post types.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'registerTaxonomy',
			'method_args' => array(),
			'config_schema' => array(
				'taxonomy' => self::field( 'string', __( 'Taxonomy Slug', 'workflow-automate' ), array( 'required' => true ) ),
				'name' => self::field( 'string', __( 'Taxonomy Name', 'workflow-automate' ), array( 'required' => true ) ),
				'post_types' => self::field( 'array', __( 'Post Types (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
				'hierarchy' => self::field( 'boolean', __( 'Hierarchical', 'workflow-automate' ), array( 'default' => false ) ),
				'public' => self::field( 'boolean', __( 'Public', 'workflow-automate' ), array( 'default' => true ) ),
				'show_ui' => self::field( 'boolean', __( 'Show Admin UI', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_rest' => self::field( 'boolean', __( 'Show in REST API', 'workflow-automate' ), array( 'default' => true ) ),
				'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'rewrite_slug' => self::field( 'string', __( 'Custom URL Slug', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_unregister_taxonomy_action',
			'label' => __( 'Unregister Taxonomy', 'workflow-automate' ),
			'description' => __( 'Unregisters a previously registered custom taxonomy.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'unregisterTaxonomy',
			'method_args' => array(),
			'config_schema' => array(
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_add_taxonomy_to_post_action',
			'label' => __( 'Add Taxonomy to Post', 'workflow-automate' ),
			'description' => __( 'Assigns terms of a custom taxonomy to a post.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'addTaxonomyToPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
				'terms' => self::field( 'string', __( 'Terms (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
				'append' => self::field( 'boolean', __( 'Append (keep existing terms)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_remove_taxonomy_from_post_action',
			'label' => __( 'Remove Taxonomy From Post', 'workflow-automate' ),
			'description' => __( 'Removes terms of a custom taxonomy from a post.', 'workflow-automate' ),
			'group' => 'taxonomy',
			'group_label' => $groups['taxonomy'],
			'method' => 'removeTaxonomyFromPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
				'terms' => self::field( 'string', __( 'Terms (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Term management (generic taxonomy).
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_get_all_terms_action',
			'label' => __( 'Get Term (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every term, optionally restricted to one taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'getAllTerms',
			'method_args' => array(),
			'config_schema' => array(
				'taxonomy' => self::field( 'string', __( 'Taxonomy (optional, leave empty for all)', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_term_action',
			'label' => __( 'Get Term (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single term by ID within a taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'getTerm',
			'method_args' => array(),
			'config_schema' => array(
				'term_id' => self::field( 'string', __( 'Term ID', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_term_by_field_action',
			'label' => __( 'Get Term by Field', 'workflow-automate' ),
			'description' => __( 'Retrieves a single term by an arbitrary field (slug, name, term_taxonomy_id, id).', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'getTermByField',
			'method_args' => array(),
			'config_schema' => array(
				'field_key' => self::field(
					'select',
					__( 'Field', 'workflow-automate' ),
					array(
						'required' => true,
						'default' => 'slug',
						'options' => array(
							self::option( 'slug', __( 'Slug', 'workflow-automate' ) ),
							self::option( 'name', __( 'Name', 'workflow-automate' ) ),
							self::option( 'term_taxonomy_id', __( 'Term Taxonomy ID', 'workflow-automate' ) ),
							self::option( 'id', __( 'ID', 'workflow-automate' ) ),
						),
					)
				),
				'field_value' => self::field( 'string', __( 'Field Value', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy (required for Term Taxonomy ID)', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_term_by_taxonomy_action',
			'label' => __( 'Get Term by Taxonomy', 'workflow-automate' ),
			'description' => __( 'Retrieves every term belonging to a specific taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'getTermByTaxonomy',
			'method_args' => array(),
			'config_schema' => array(
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_create_term_action',
			'label' => __( 'Create New Term', 'workflow-automate' ),
			'description' => __( 'Creates a new term within any taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'createNewTerm',
			'method_args' => array(),
			'config_schema' => array(
				'name' => self::field( 'string', __( 'Name', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
				'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_update_term_action',
			'label' => __( 'Update Term', 'workflow-automate' ),
			'description' => __( 'Updates an existing term within any taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'updateTerm',
			'method_args' => array(),
			'config_schema' => array(
				'term_id' => self::field( 'string', __( 'Term ID', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
				'name' => self::field( 'string', __( 'Name', 'workflow-automate' ) ),
				'slug' => self::field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'description' => self::field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_delete_term_action',
			'label' => __( 'Delete Term', 'workflow-automate' ),
			'description' => __( 'Deletes an existing term within any taxonomy.', 'workflow-automate' ),
			'group' => 'term',
			'group_label' => $groups['term'],
			'method' => 'deleteTerm',
			'method_args' => array(),
			'config_schema' => array(
				'term_id' => self::field( 'string', __( 'Term ID', 'workflow-automate' ), array( 'required' => true ) ),
				'taxonomy' => self::field( 'string', __( 'Taxonomy', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		// ---------------------------------------------------------------
		// Category management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_create_category_action',
			'label' => __( 'Create Category', 'workflow-automate' ),
			'description' => __( 'Creates a new post category.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'createTermByTax',
			'method_args' => array( 'category' ),
			'config_schema' => $termFields,
		);

		$definitions[] = array(
			'slug' => 'wp_update_category_action',
			'label' => __( 'Update Category', 'workflow-automate' ),
			'description' => __( 'Updates an existing post category.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'updateTermByTax',
			'method_args' => array( 'category' ),
			'config_schema' => $termUpdateFields,
		);

		$definitions[] = array(
			'slug' => 'wp_delete_category_action',
			'label' => __( 'Delete Category', 'workflow-automate' ),
			'description' => __( 'Deletes an existing post category.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'deleteTermByTax',
			'method_args' => array( 'category' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug' => 'wp_add_category_to_post_action',
			'label' => __( 'Add Category To Post', 'workflow-automate' ),
			'description' => __( 'Assigns one or more categories to a post.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'addCategoryToPost',
			'method_args' => array(),
			'config_schema' => array(
				'post_id' => self::field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'categories' => self::field( 'array', __( 'Category IDs (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
				'append' => self::field( 'boolean', __( 'Append (keep existing categories)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_get_all_categories_action',
			'label' => __( 'Get Category (All)', 'workflow-automate' ),
			'description' => __( 'Retrieves every post category.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'getAllTerms',
			'method_args' => array( 'category' ),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug' => 'wp_get_category_action',
			'label' => __( 'Get Category (Single)', 'workflow-automate' ),
			'description' => __( 'Retrieves a single post category by ID.', 'workflow-automate' ),
			'group' => 'category',
			'group_label' => $groups['category'],
			'method' => 'getTermById',
			'method_args' => array( 'category' ),
			'config_schema' => $termIdField,
		);

		// ---------------------------------------------------------------
		// WooCommerce product tag / category / type (plain `wp_terms`
		// rows on the product_tag / product_cat / product_type taxonomies).
		// ---------------------------------------------------------------

		$productTaxonomies = array(
			'product_tag' => array( 'group' => 'product_tag', 'noun' => __( 'Product Tag', 'workflow-automate' ), 'plural' => __( 'Product Tags', 'workflow-automate' ) ),
			'product_cat' => array( 'group' => 'product_category', 'noun' => __( 'Product Category', 'workflow-automate' ), 'plural' => __( 'Product Categories', 'workflow-automate' ) ),
			'product_type' => array( 'group' => 'product_type', 'noun' => __( 'Product Type', 'workflow-automate' ), 'plural' => __( 'Product Types', 'workflow-automate' ) ),
		);

		foreach ( $productTaxonomies as $taxonomySlug => $meta ) {
			$group = $meta['group'];
			$noun = $meta['noun'];
			$plural = $meta['plural'];
			$slugPrefix = 'wp_' . $group;

			$definitions[] = array(
				'slug' => $slugPrefix . '_create_action',
				/* translators: %s: e.g. "Product Tag". */
				'label' => sprintf( __( 'Create %s', 'workflow-automate' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description' => sprintf( __( 'Creates a new %s term.', 'workflow-automate' ), strtolower( $noun ) ),
				'group' => $group,
				'group_label' => $groups[ $group ],
				'method' => 'createTermByTax',
				'method_args' => array( $taxonomySlug ),
				'config_schema' => $termFields,
			);

			$definitions[] = array(
				'slug' => $slugPrefix . '_update_action',
				'label' => sprintf( __( 'Update %s', 'workflow-automate' ), $noun ),
				'description' => sprintf( __( 'Updates an existing %s term.', 'workflow-automate' ), strtolower( $noun ) ),
				'group' => $group,
				'group_label' => $groups[ $group ],
				'method' => 'updateTermByTax',
				'method_args' => array( $taxonomySlug ),
				'config_schema' => $termUpdateFields,
			);

			$definitions[] = array(
				'slug' => $slugPrefix . '_delete_action',
				'label' => sprintf( __( 'Delete %s', 'workflow-automate' ), $noun ),
				'description' => sprintf( __( 'Deletes an existing %s term.', 'workflow-automate' ), strtolower( $noun ) ),
				'group' => $group,
				'group_label' => $groups[ $group ],
				'method' => 'deleteTermByTax',
				'method_args' => array( $taxonomySlug ),
				'config_schema' => $termIdField,
			);

			$definitions[] = array(
				'slug' => $slugPrefix . '_get_all_action',
				/* translators: %s: e.g. "Product Tag". */
				'label' => sprintf( __( 'Get %s (All)', 'workflow-automate' ), $noun ),
				'description' => sprintf( __( 'Retrieves every %s term.', 'workflow-automate' ), strtolower( $noun ) ),
				'group' => $group,
				'group_label' => $groups[ $group ],
				'method' => 'getAllTerms',
				'method_args' => array( $taxonomySlug ),
				'config_schema' => array(),
			);

			$definitions[] = array(
				'slug' => $slugPrefix . '_get_single_action',
				/* translators: %s: e.g. "Product Tag". */
				'label' => sprintf( __( 'Get %s (Single)', 'workflow-automate' ), $noun ),
				'description' => sprintf( __( 'Retrieves a single %s term by ID.', 'workflow-automate' ), strtolower( $noun ) ),
				'group' => $group,
				'group_label' => $groups[ $group ],
				'method' => 'getTermById',
				'method_args' => array( $taxonomySlug ),
				'config_schema' => $termIdField,
			);
		}

		// ---------------------------------------------------------------
		// Plugin management.
		// ---------------------------------------------------------------

		$definitions[] = array(
			'slug' => 'wp_check_plugin_activation_status_action',
			'label' => __( 'Check Plugin Activation Status', 'workflow-automate' ),
			'description' => __( 'Checks whether a plugin is currently active.', 'workflow-automate' ),
			'group' => 'plugin',
			'group_label' => $groups['plugin'],
			'method' => 'checkPluginActivationStatus',
			'method_args' => array(),
			'config_schema' => array(
				'plugin_file' => self::field( 'string', __( 'Plugin File (e.g. akismet/akismet.php)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug' => 'wp_activate_plugin_action',
			'label' => __( 'Activate Plugin', 'workflow-automate' ),
			'description' => __( 'Activates an installed but inactive plugin.', 'workflow-automate' ),
			'group' => 'plugin',
			'group_label' => $groups['plugin'],
			'method' => 'activatePlugin',
			'method_args' => array(),
			'config_schema' => array(
				'plugin_file' => self::field( 'string', __( 'Plugin File (e.g. akismet/akismet.php)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
