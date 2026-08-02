<?php
/**
 * Built-in WordPress core hook trigger catalog.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curated list of WordPress action hooks exposed as individual palette triggers.
 */
class WordPressHookCatalog {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions(): array {
		$definitions = array_merge(
			self::postTriggers(),
			self::userTriggers(),
			self::customTriggers(),
			self::pluginTriggers(),
			self::mediaTriggers(),
			self::optionTriggers(),
			self::commentTriggers(),
			self::termTriggers(),
			self::authenticationTriggers()
		);

		if ( is_multisite() ) {
			$definitions = array_merge( $definitions, self::multisiteTriggers() );
		}

		return $definitions;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function postTriggers(): array {
		$group       = 'post';
		$group_label = __( 'Post', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'transition_post_status', __( 'On Post Status Update', 'ai-agent-workflow-automation' ), $group, $group_label, 'transition_post_status', 10, 3 ),
			self::entry( 'post_moved_to_trash', __( 'Post Moved to Trash', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_trash_post', 10, 1 ),
			self::entry( 'deleted_post', __( 'Post Deleted', 'ai-agent-workflow-automation' ), $group, $group_label, 'deleted_post', 10, 1 ),
			self::entry( 'revision_creation', __( 'Revision Creation', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_save_post_revision', 10, 1 ),
			self::entry( 'meta_box_setup', __( 'Meta Box Setup', 'ai-agent-workflow-automation' ), $group, $group_label, 'add_meta_boxes', 10, 2 ),
			self::entry( 'before_delete_post', __( 'Before Delete Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'before_delete_post', 10, 2 ),
			self::entry( 'delete_post', __( 'Delete Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_post', 10, 1 ),
			self::entry( 'post_updated', __( 'Post Updated', 'ai-agent-workflow-automation' ), $group, $group_label, 'post_updated', 10, 3 ),
			self::entry( 'save_post', __( 'Save Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'save_post', 10, 3 ),
			self::entry( 'untrash_post', __( 'Untrash Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'untrash_post', 10, 1 ),
			self::entry( 'wp_after_insert_post', __( 'WP After Insert Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_after_insert_post', 10, 4 ),
			self::entry( 'wp_insert_post', __( 'WP Insert Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_insert_post', 10, 3 ),
			self::entry( 'wp_trash_post', __( 'WP Trash Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_trash_post', 10, 1 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function userTriggers(): array {
		$group       = 'user';
		$group_label = __( 'User', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'add_user_role', __( 'User Added to a Role', 'ai-agent-workflow-automation' ), $group, $group_label, 'add_user_role', 10, 2 ),
			self::entry( 'after_password_reset', __( 'After Password Reset', 'ai-agent-workflow-automation' ), $group, $group_label, 'after_password_reset', 10, 2 ),
			self::entry( 'delete_user', __( 'Delete User', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_user', 10, 2 ),
			self::entry( 'edit_user', __( 'Edit User', 'ai-agent-workflow-automation' ), $group, $group_label, 'edit_user_profile', 10, 1 ),
			self::entry( 'user_profile_edit', __( 'User Profile Edit', 'ai-agent-workflow-automation' ), $group, $group_label, 'edit_user_profile_update', 10, 1 ),
			self::entry( 'profile_update', __( 'Profile Update', 'ai-agent-workflow-automation' ), $group, $group_label, 'profile_update', 10, 3 ),
			self::entry( 'password_reset', __( 'Password Reset', 'ai-agent-workflow-automation' ), $group, $group_label, 'password_reset', 10, 2 ),
			self::entry( 'personal_options_update', __( 'Personal Options Update', 'ai-agent-workflow-automation' ), $group, $group_label, 'personal_options_update', 10, 1 ),
			self::entry( 'register_form', __( 'Registration Form', 'ai-agent-workflow-automation' ), $group, $group_label, 'register_form', 10, 0 ),
			self::entry( 'password_retrieval', __( 'Password Retrieval', 'ai-agent-workflow-automation' ), $group, $group_label, 'lostpassword_form', 10, 0 ),
			self::entry( 'set_user_role', __( 'Set User Role', 'ai-agent-workflow-automation' ), $group, $group_label, 'set_user_role', 10, 3 ),
			self::entry( 'show_user_profile', __( 'User Profile Show', 'ai-agent-workflow-automation' ), $group, $group_label, 'show_user_profile', 10, 1 ),
			self::entry( 'user_register', __( 'User Register', 'ai-agent-workflow-automation' ), $group, $group_label, 'user_register', 10, 1 ),
			self::entry( 'validate_reset', __( 'Validate Reset', 'ai-agent-workflow-automation' ), $group, $group_label, 'validate_password_reset', 10, 3 ),
			self::entry( 'wp_authenticate', __( 'WP Authenticate', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_authenticate', 10, 3 ),
			self::entry( 'create_app_password', __( 'Create App Password', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_create_application_password', 10, 3 ),
			self::entry( 'delete_app_password', __( 'Delete App Password', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_delete_application_password', 10, 1 ),
			self::entry( 'deleted_user', __( 'WP Delete User', 'ai-agent-workflow-automation' ), $group, $group_label, 'deleted_user', 10, 2 ),
			self::entry( 'wp_login', __( 'WP Login', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_login', 10, 2 ),
			self::entry( 'wp_login_failed', __( 'WP Login Failed', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_login_failed', 10, 2 ),
			self::entry( 'wp_logout', __( 'WP Logout', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_logout', 10, 1 ),
			self::entry( 'update_app_password', __( 'Update App Password', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_update_application_password', 10, 3 ),
			self::entry( 'wp_update_user', __( 'WP Update User', 'ai-agent-workflow-automation' ), $group, $group_label, 'profile_update', 10, 3 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function customTriggers(): array {
		$group       = 'custom';
		$group_label = __( 'Custom', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'add_action', __( 'Add Action', 'ai-agent-workflow-automation' ), $group, $group_label, 'init', 10, 0 ),
			self::entry( 'do_action', __( 'Do Action', 'ai-agent-workflow-automation' ), $group, $group_label, 'all', 10, 1 ),
			self::entry( 'admin_post_action', __( 'Admin Post Action', 'ai-agent-workflow-automation' ), $group, $group_label, 'admin_post', 10, 0 ),
			self::entry( 'customizer_registration', __( 'Customizer Registration', 'ai-agent-workflow-automation' ), $group, $group_label, 'customize_register', 10, 1 ),
			self::entry( 'rewrite_rules', __( 'Rewrite Rules', 'ai-agent-workflow-automation' ), $group, $group_label, 'generate_rewrite_rules', 10, 1 ),
			self::entry( 'rest_api_init', __( 'REST API Init', 'ai-agent-workflow-automation' ), $group, $group_label, 'rest_api_init', 10, 0 ),
			self::entry( 'theme_switch', __( 'Theme Switch', 'ai-agent-workflow-automation' ), $group, $group_label, 'switch_theme', 10, 3 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function pluginTriggers(): array {
		$group       = 'plugin';
		$group_label = __( 'Plugin', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'plugin_activation', __( 'Plugin Activation', 'ai-agent-workflow-automation' ), $group, $group_label, 'activated_plugin', 10, 2 ),
			self::entry( 'plugin_deactivation', __( 'Plugin Deactivation', 'ai-agent-workflow-automation' ), $group, $group_label, 'deactivated_plugin', 10, 2 ),
			self::entry( 'update_complete', __( 'Update Complete', 'ai-agent-workflow-automation' ), $group, $group_label, 'upgrader_process_complete', 10, 3 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function mediaTriggers(): array {
		$group       = 'media';
		$group_label = __( 'Media', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'add_attachment', __( 'Add Attachment', 'ai-agent-workflow-automation' ), $group, $group_label, 'add_attachment', 10, 1 ),
			self::entry( 'attachment_edit', __( 'Attachment Edit', 'ai-agent-workflow-automation' ), $group, $group_label, 'attachment_fields_to_edit', 10, 2 ),
			self::entry( 'attachment_save', __( 'Attachment Save', 'ai-agent-workflow-automation' ), $group, $group_label, 'attachment_fields_to_save', 10, 2 ),
			self::entry( 'attachment_updated', __( 'Attachment Updated', 'ai-agent-workflow-automation' ), $group, $group_label, 'attachment_updated', 10, 3 ),
			self::entry( 'media_deletion', __( 'Media Deletion', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_attachment', 10, 1 ),
			self::entry( 'media_edit', __( 'Media Edit', 'ai-agent-workflow-automation' ), $group, $group_label, 'edit_attachment', 10, 1 ),
			self::entry( 'image_sizes', __( 'Image Sizes', 'ai-agent-workflow-automation' ), $group, $group_label, 'intermediate_image_sizes_advanced', 10, 2 ),
			self::entry( 'media_tabs', __( 'Media Tabs', 'ai-agent-workflow-automation' ), $group, $group_label, 'media_upload_tabs', 10, 1 ),
			self::entry( 'attachment_count', __( 'Attachment Count', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_update_attachment_metadata', 10, 2 ),
			self::entry( 'generate_attachment_metadata', __( 'Generate Attachment Metadata', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_generate_attachment_metadata', 10, 2 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function optionTriggers(): array {
		$group       = 'option';
		$group_label = __( 'Option', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'option_addition', __( 'Option Addition', 'ai-agent-workflow-automation' ), $group, $group_label, 'added_option', 10, 2 ),
			self::entry( 'option_deletion', __( 'Option Deletion', 'ai-agent-workflow-automation' ), $group, $group_label, 'deleted_option', 10, 1 ),
			self::entry( 'post_option_delete', __( 'Post Option Delete', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_option', 10, 1 ),
			self::entry( 'option_update', __( 'Option Update', 'ai-agent-workflow-automation' ), $group, $group_label, 'update_option', 10, 3 ),
			self::entry( 'updated_option', __( 'Updated Option', 'ai-agent-workflow-automation' ), $group, $group_label, 'updated_option', 10, 3 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function commentTriggers(): array {
		$group       = 'comment';
		$group_label = __( 'Comment', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'comment_post', __( 'Comment Post', 'ai-agent-workflow-automation' ), $group, $group_label, 'comment_post', 10, 2 ),
			self::entry( 'comment_deletion', __( 'Comment Deletion', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_comment', 10, 2 ),
			self::entry( 'edit_comment', __( 'Edit Comment', 'ai-agent-workflow-automation' ), $group, $group_label, 'edit_comment', 10, 2 ),
			self::entry( 'pre_approve_comment', __( 'Pre-Approve Comment', 'ai-agent-workflow-automation' ), $group, $group_label, 'pre_comment_approved', 10, 2 ),
			self::entry( 'transition_comment_status', __( 'Transition Comment Status', 'ai-agent-workflow-automation' ), $group, $group_label, 'transition_comment_status', 10, 3 ),
			self::entry( 'trashed_comment', __( 'Trashed Comment', 'ai-agent-workflow-automation' ), $group, $group_label, 'trash_comment', 10, 1 ),
			self::entry( 'untrashed_comment', __( 'Untrashed Comment', 'ai-agent-workflow-automation' ), $group, $group_label, 'untrash_comment', 10, 1 ),
			self::entry( 'wp_insert_comment', __( 'WP Insert Comment', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_insert_comment', 10, 2 ),
			self::entry( 'wp_set_comment_status', __( 'WP Set Comment Status', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_set_comment_status', 10, 2 ),
			self::entry( 'update_comment_count', __( 'Update Comment Count', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_update_comment_count', 10, 1 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function termTriggers(): array {
		$group       = 'term';
		$group_label = __( 'Term', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'term_creation', __( 'Term Creation', 'ai-agent-workflow-automation' ), $group, $group_label, 'pre_insert_term', 10, 2 ),
			self::entry( 'term_created', __( 'Term Created', 'ai-agent-workflow-automation' ), $group, $group_label, 'created_term', 10, 3 ),
			self::entry( 'term_deletion', __( 'Term Deletion', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_term', 10, 5 ),
			self::entry( 'delete_term_taxonomy', __( 'Delete Term Taxonomy', 'ai-agent-workflow-automation' ), $group, $group_label, 'delete_term_taxonomy', 10, 2 ),
			self::entry( 'term_update', __( 'Term Update', 'ai-agent-workflow-automation' ), $group, $group_label, 'edited_term', 10, 3 ),
			self::entry( 'term_edit', __( 'Term Edit', 'ai-agent-workflow-automation' ), $group, $group_label, 'edit_terms', 10, 2 ),
			self::entry( 'term_edited', __( 'Term Edited', 'ai-agent-workflow-automation' ), $group, $group_label, 'edited_terms', 10, 2 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function multisiteTriggers(): array {
		$group       = 'multisite';
		$group_label = __( 'Multisite', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'site_deletion', __( 'Site Deletion', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_delete_site', 10, 1 ),
			self::entry( 'remove_blog_user', __( 'Remove Blog User', 'ai-agent-workflow-automation' ), $group, $group_label, 'remove_user_from_blog', 10, 2 ),
			self::entry( 'signup_blog_form', __( 'Signup Blog Form', 'ai-agent-workflow-automation' ), $group, $group_label, 'signup_blogform', 10, 0 ),
			self::entry( 'signup_extra_fields', __( 'Signup Extra Fields', 'ai-agent-workflow-automation' ), $group, $group_label, 'signup_extra_fields', 10, 1 ),
			self::entry( 'signup_finished', __( 'Signup Finished', 'ai-agent-workflow-automation' ), $group, $group_label, 'signup_finished', 10, 2 ),
			self::entry( 'signup_header', __( 'Signup Header', 'ai-agent-workflow-automation' ), $group, $group_label, 'signup_header', 10, 0 ),
			self::entry( 'blog_switch', __( 'Blog Switch', 'ai-agent-workflow-automation' ), $group, $group_label, 'switch_blog', 10, 2 ),
			self::entry( 'update_blog_public', __( 'Update Blog Public', 'ai-agent-workflow-automation' ), $group, $group_label, 'update_blog_public', 10, 2 ),
			self::entry( 'update_blog_status', __( 'Update Blog Status', 'ai-agent-workflow-automation' ), $group, $group_label, 'update_blog_status', 10, 1 ),
			self::entry( 'site_creation', __( 'Site Creation', 'ai-agent-workflow-automation' ), $group, $group_label, 'wp_insert_site', 10, 1 ),
			self::entry( 'activate_user', __( 'Activate User', 'ai-agent-workflow-automation' ), $group, $group_label, 'wpmu_activate_user', 10, 3 ),
			self::entry( 'delete_network_user', __( 'Delete Network User', 'ai-agent-workflow-automation' ), $group, $group_label, 'wpmu_delete_user', 10, 1 ),
			self::entry( 'new_blog', __( 'New Blog', 'ai-agent-workflow-automation' ), $group, $group_label, 'wpmu_new_blog', 10, 2 ),
			self::entry( 'new_network_user', __( 'New Network User', 'ai-agent-workflow-automation' ), $group, $group_label, 'wpmu_new_user', 10, 2 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function authenticationTriggers(): array {
		$group       = 'authentication';
		$group_label = __( 'Authentication', 'ai-agent-workflow-automation' );

		return array(
			self::entry( 'login_footer', __( 'Login Footer', 'ai-agent-workflow-automation' ), $group, $group_label, 'login_footer', 10, 0 ),
			self::entry( 'login_form', __( 'Login Form', 'ai-agent-workflow-automation' ), $group, $group_label, 'login_form', 10, 0 ),
			self::entry( 'login_head', __( 'Login Head', 'ai-agent-workflow-automation' ), $group, $group_label, 'login_head', 10, 0 ),
			self::entry( 'login_initialization', __( 'Login Initialization', 'ai-agent-workflow-automation' ), $group, $group_label, 'login_init', 10, 0 ),
			self::entry( 'lost_password_form', __( 'Lost Password Form', 'ai-agent-workflow-automation' ), $group, $group_label, 'lostpassword_form', 10, 0 ),
		);
	}

	/**
	 * @param string $slug
	 * @param string $label
	 * @param string $group
	 * @param string $group_label
	 * @param string $hook_name
	 * @param int    $priority
	 * @param int    $accepted_args
	 *
	 * @return array<string, mixed>
	 */
	private static function entry(
		string $slug,
		string $label,
		string $group,
		string $group_label,
		string $hook_name,
		int $priority = 10,
		int $accepted_args = 1
	): array {
		return array(
			'slug'          => 'wp_' . $slug,
			'label'         => $label,
			'group'         => $group,
			'group_label'   => $group_label,
			'hook_name'     => $hook_name,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}
