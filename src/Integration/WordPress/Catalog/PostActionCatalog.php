<?php
/**
 * Post, Comment, and Post Type catalog definitions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress\Catalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WordPress Action Catalog definitions for Post and Comment Management.
 */
final class PostActionCatalog {

	/**
	 * @param callable(string, string, array<string, mixed>=): array<string, mixed> $field
	 * @param callable(string, string): array{value: string, label: string}         $option
	 * @param array<string, string>                                                 $groups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions( callable $field, callable $option, array $groups ): array {
		$definitions = array();

		$postStatusOptions = array(
			$option( 'draft', __( 'Draft', 'workflow-automate' ) ),
			$option( 'publish', __( 'Published', 'workflow-automate' ) ),
			$option( 'pending', __( 'Pending Review', 'workflow-automate' ) ),
			$option( 'private', __( 'Private', 'workflow-automate' ) ),
			$option( 'future', __( 'Scheduled', 'workflow-automate' ) ),
		);

		// Post management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_posts_action',
			'label'         => __( 'Get Post (All)', 'workflow-automate' ),
			'description'   => __( 'Lists posts (capped). Results include language + translations map for WPML/Polylang. Prefer Get Post by ID for full content.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getAllPosts',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type'   => $field( 'string', __( 'Post Type (optional)', 'workflow-automate' ) ),
				'post_status' => $field( 'string', __( 'Post Status (optional, default any)', 'workflow-automate' ), array( 'default' => 'any' ) ),
				'search'      => $field( 'string', __( 'Search (optional)', 'workflow-automate' ), array( 'description' => __( 'Free-text search to narrow results.', 'workflow-automate' ) ) ),
				'limit'       => $field(
					'integer',
					__( 'Limit', 'workflow-automate' ),
					array(
						'default'     => 50,
						'description' => __( 'Max posts to return (default 50, max 200). Content is truncated in list results.', 'workflow-automate' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_by_id_action',
			'label'         => __( 'Get Post (Single)', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single post by its ID (full content + language/translations).', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostById',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_posts_by_post_type_action',
			'label'         => __( 'Get Posts By Post Type', 'workflow-automate' ),
			'description'   => __( 'Lists posts of a given type (capped; includes language/translations).', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostsByPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type' => $field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'required' => true ) ),
				'limit'     => $field(
					'integer',
					__( 'Limit', 'workflow-automate' ),
					array(
						'default'     => 50,
						'description' => __( 'Max posts (default 50, max 200).', 'workflow-automate' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_posts_by_metadata_action',
			'label'         => __( 'Get Posts by Metadata', 'workflow-automate' ),
			'description'   => __( 'Retrieves posts of a given type matching a meta key/value pair.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostsByMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type'  => $field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_value' => $field( 'string', __( 'Meta Value', 'workflow-automate' ), array( 'required' => true ) ),
				'limit'      => $field( 'integer', __( 'Limit', 'workflow-automate' ), array( 'default' => 50 ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_metadata_all_action',
			'label'         => __( 'Get Post Metadata (All)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every metadata entry for a post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_metadata_single_action',
			'label'         => __( 'Get Post Metadata (Single)', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single metadata value for a post by meta key.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'  => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key' => $field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_permalink_action',
			'label'         => __( 'Get Post Permalink', 'workflow-automate' ),
			'description'   => __( 'Retrieves the public URL of a post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostPermalink',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_content_action',
			'label'         => __( 'Get Post Content', 'workflow-automate' ),
			'description'   => __( 'Retrieves the raw content of a post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostContent',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_excerpt_action',
			'label'         => __( 'Get Post Excerpt', 'workflow-automate' ),
			'description'   => __( 'Retrieves the excerpt of a post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostExcerpt',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_status_action',
			'label'         => __( 'Get Post Status', 'workflow-automate' ),
			'description'   => __( 'Retrieves the status of a post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostStatus',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_create_post_action',
			'label'         => __( 'Create New Post', 'workflow-automate' ),
			'description'   => __( 'Creates a new post or page. For designed/colorful layouts pass design_sections JSON (hero/columns/cta) or Gutenberg block markup in content.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'createNewPost',
			'method_args'   => array(),
			'config_schema' => array(
				'title'             => $field( 'string', __( 'Title', 'workflow-automate' ), array( 'required' => true ) ),
				'content'           => $field(
					'string',
					__( 'Content', 'workflow-automate' ),
					array(
						'multiline'   => true,
						'description' => __( 'Post/page body. For designed pages prefer design_sections, or pass full Gutenberg block markup (<!-- wp:heading -->…<!-- /wp:heading -->). Plain paragraphs alone are not a designed page.', 'workflow-automate' ),
					)
				),
				'design_sections'   => $field(
					'string',
					__( 'Design sections (JSON)', 'workflow-automate' ),
					array(
						'multiline'   => true,
						'description' => __( 'Preferred for designed pages. JSON array of sections converted server-side into Gutenberg blocks. Example: [{"type":"hero","heading":"Title","text":"Subtitle","background":"#0f172a","text_color":"#ffffff","button_text":"Explore","button_url":"/"},{"type":"columns","items":[{"title":"One","text":"…"},{"title":"Two","text":"…"}]},{"type":"cta","heading":"Ready?","button_text":"Go","background":"#f97316"}]. Types: hero, heading, paragraph, columns, cta, buttons, spacer, separator, group. When set, overrides content.', 'workflow-automate' ),
					)
				),
				'excerpt'           => $field( 'string', __( 'Excerpt', 'workflow-automate' ), array( 'multiline' => true ) ),
				'post_type'         => $field(
					'string',
					__( 'Post Type', 'workflow-automate' ),
					array(
						'required'    => true,
						'default'     => '{{trigger.post_type}}',
						'description' => __( 'Defaults to the trigger post type ({{trigger.post_type}}). Change manually to force a type (e.g. page or post); a manual value always wins over the trigger.', 'workflow-automate' ),
						'help'        => __( 'Leave as {{trigger.post_type}} to follow the saved post/page. Replace with page, post, or a CPT slug to override.', 'workflow-automate' ),
					)
				),
				'post_status'       => $field(
					'select',
					__( 'Post Status', 'workflow-automate' ),
					array(
						'required' => true,
						'default'  => 'draft',
						'options'  => $postStatusOptions,
					)
				),
				'slug'              => $field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'date'              => $field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'date_gmt'          => $field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'parent_id'         => $field( 'string', __( 'Parent Post ID', 'workflow-automate' ) ),
				'post_password'     => $field( 'string', __( 'Post Password', 'workflow-automate' ) ),
				'post_author'       => $field( 'string', __( 'Author (User ID)', 'workflow-automate' ) ),
				'categories'        => $field( 'string', __( 'Categories (comma-separated category IDs)', 'workflow-automate' ) ),
				'tags'              => $field( 'string', __( 'Tags (comma-separated)', 'workflow-automate' ) ),
				'taxonomy'          => $field( 'string', __( 'Custom Taxonomy (optional)', 'workflow-automate' ) ),
				'terms'             => $field( 'string', __( 'Custom Taxonomy Terms (comma-separated)', 'workflow-automate' ) ),
				'custom_fields'     => $field( 'key_value', __( 'Custom Fields', 'workflow-automate' ), array( 'default' => array() ) ),
				'featured_image'    => $field( 'string', __( 'Featured Image URL', 'workflow-automate' ), array( 'description' => __( 'Optional. Only a real, direct image URL (ending in .jpg/.png/.webp etc.). Omit rather than using example.com or generic search URLs. Invalid URLs are skipped without failing the create.', 'workflow-automate' ) ) ),
				'featured_image_id' => $field( 'string', __( 'Featured Image Attachment ID', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_post_action',
			'label'         => __( 'Update Post', 'workflow-automate' ),
			'description'   => __( 'Updates fields on an existing post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'updateExistingPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'           => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'title'             => $field( 'string', __( 'Title', 'workflow-automate' ) ),
				'content'           => $field(
					'string',
					__( 'Content', 'workflow-automate' ),
					array(
						'multiline'   => true,
						'description' => __( 'Post/page body. For designed pages prefer design_sections, or pass full Gutenberg block markup. Plain paragraphs alone are not a designed page.', 'workflow-automate' ),
					)
				),
				'design_sections'   => $field(
					'string',
					__( 'Design sections (JSON)', 'workflow-automate' ),
					array(
						'multiline'   => true,
						'description' => __( 'Preferred for designed pages. JSON array of sections (hero, heading, paragraph, columns, cta, buttons, spacer, separator, group) converted into Gutenberg blocks. When set, overrides content.', 'workflow-automate' ),
					)
				),
				'excerpt'           => $field( 'string', __( 'Excerpt', 'workflow-automate' ), array( 'multiline' => true ) ),
				'post_type'         => $field( 'string', __( 'Post Type', 'workflow-automate' ), array( 'description' => __( 'Use "page" when updating a page.', 'workflow-automate' ) ) ),
				'post_status'       => $field(
					'select',
					__( 'Post Status', 'workflow-automate' ),
					array(
						'default' => '',
						'options' => $postStatusOptions,
					)
				),
				'slug'              => $field( 'string', __( 'Slug', 'workflow-automate' ) ),
				'date'              => $field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'date_gmt'          => $field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'workflow-automate' ) ),
				'parent_id'         => $field( 'string', __( 'Parent Post ID', 'workflow-automate' ) ),
				'post_password'     => $field( 'string', __( 'Post Password', 'workflow-automate' ) ),
				'post_author'       => $field( 'string', __( 'Author (User ID)', 'workflow-automate' ) ),
				'categories'        => $field( 'string', __( 'Categories (comma-separated category IDs)', 'workflow-automate' ) ),
				'tags'              => $field( 'string', __( 'Tags (comma-separated)', 'workflow-automate' ) ),
				'taxonomy'          => $field( 'string', __( 'Custom Taxonomy (optional)', 'workflow-automate' ) ),
				'terms'             => $field( 'string', __( 'Custom Taxonomy Terms (comma-separated)', 'workflow-automate' ) ),
				'custom_fields'     => $field( 'key_value', __( 'Custom Fields', 'workflow-automate' ), array( 'default' => array() ) ),
				'featured_image'    => $field( 'string', __( 'Featured Image URL', 'workflow-automate' ), array( 'description' => __( 'Optional. Only a real direct image URL. Invalid URLs are skipped without failing the update.', 'workflow-automate' ) ) ),
				'featured_image_id' => $field( 'string', __( 'Featured Image Attachment ID', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_post_status_action',
			'label'         => __( 'Update Post Status', 'workflow-automate' ),
			'description'   => __( 'Changes only the status of an existing post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'updatePostStatus',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'     => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'post_status' => $field(
					'select',
					__( 'Post Status', 'workflow-automate' ),
					array(
						'required' => true,
						'default'  => 'publish',
						'options'  => $postStatusOptions,
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_post_action',
			'label'         => __( 'Delete Post', 'workflow-automate' ),
			'description'   => __( 'Deletes (or trashes) an existing post.', 'workflow-automate' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'deleteExistingPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'force_delete' => $field( 'boolean', __( 'Force Delete (skip trash)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		// Comment management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_post_comments_action',
			'label'         => __( 'Get Post Comments (All)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every comment across the whole site.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getAllPostComments',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_comments_action',
			'label'         => __( 'Get Post Comments (Single Post)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every comment on a specific post.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getPostComments',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_comments_action',
			'label'         => __( 'Get User Comments', 'workflow-automate' ),
			'description'   => __( 'Retrieves every comment authored by a registered user.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getUserComments',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_comments_by_email_action',
			'label'         => __( 'Get User Comments (By Email)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every comment authored using a given email address.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getUserCommentsByEmail',
			'method_args'   => array(),
			'config_schema' => array(
				'user_email' => $field( 'string', __( 'User Email', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_comment_metadata_all_action',
			'label'         => __( 'Get Comment Metadata (All)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every metadata entry for a comment.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getCommentMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id' => $field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_comment_metadata_single_action',
			'label'         => __( 'Get Comment Metadata (Single)', 'workflow-automate' ),
			'description'   => __( 'Retrieves a single metadata value for a comment by meta key.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getCommentMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id' => $field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_create_comment_action',
			'label'         => __( 'Create New Comment', 'workflow-automate' ),
			'description'   => __( 'Adds a new top-level comment to a post.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'createNewComment',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'comment'      => $field(
					'string',
					__( 'Comment', 'workflow-automate' ),
					array(
						'required'  => true,
						'multiline' => true,
					)
				),
				'author_name'  => $field( 'string', __( 'Author Name', 'workflow-automate' ), array( 'required' => true ) ),
				'author_email' => $field( 'string', __( 'Author Email', 'workflow-automate' ) ),
				'author_url'   => $field( 'string', __( 'Author URL', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_reply_to_comment_action',
			'label'         => __( 'Reply To Comment', 'workflow-automate' ),
			'description'   => __( 'Adds a reply underneath an existing comment.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'replyToComment',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
				'parent_id'    => $field( 'string', __( 'Parent Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'comment'      => $field(
					'string',
					__( 'Comment', 'workflow-automate' ),
					array(
						'required'  => true,
						'multiline' => true,
					)
				),
				'author_name'  => $field( 'string', __( 'Author Name', 'workflow-automate' ), array( 'required' => true ) ),
				'author_email' => $field( 'string', __( 'Author Email', 'workflow-automate' ) ),
				'author_url'   => $field( 'string', __( 'Author URL', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_comment_action',
			'label'         => __( 'Delete Comment', 'workflow-automate' ),
			'description'   => __( 'Deletes (or trashes) an existing comment.', 'workflow-automate' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'deleteExistingComment',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id'   => $field( 'string', __( 'Comment ID', 'workflow-automate' ), array( 'required' => true ) ),
				'force_delete' => $field( 'boolean', __( 'Force Delete (skip trash)', 'workflow-automate' ), array( 'default' => false ) ),
			),
		);

		// Post type management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_post_types_action',
			'label'         => __( 'Get Post Type (All)', 'workflow-automate' ),
			'description'   => __( 'Retrieves every registered post type.', 'workflow-automate' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'getAllPostTypes',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_type_action',
			'label'         => __( 'Get Post Type (Single)', 'workflow-automate' ),
			'description'   => __( 'Retrieves the post type slug of a given post.', 'workflow-automate' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'getPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_register_post_type_action',
			'label'         => __( 'Register Post Type', 'workflow-automate' ),
			'description'   => __( 'Registers a new custom post type.', 'workflow-automate' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'registerPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'key'               => $field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
				'label'             => $field( 'string', __( 'Label', 'workflow-automate' ), array( 'required' => true ) ),
				'hierarchy'         => $field( 'boolean', __( 'Hierarchical', 'workflow-automate' ), array( 'default' => false ) ),
				'public'            => $field( 'boolean', __( 'Public', 'workflow-automate' ), array( 'default' => true ) ),
				'show_ui'           => $field( 'boolean', __( 'Show Admin UI', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_menu'      => $field( 'boolean', __( 'Show in Admin Menu', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_nav_menu'  => $field( 'boolean', __( 'Show in Nav Menus', 'workflow-automate' ), array( 'default' => true ) ),
				'show_in_admin_bar' => $field( 'boolean', __( 'Show in Admin Bar', 'workflow-automate' ), array( 'default' => true ) ),
				'menu_position'     => $field( 'string', __( 'Menu Position', 'workflow-automate' ) ),
				'supports'          => $field( 'array', __( 'Supports (comma-separated, e.g. title,editor,thumbnail)', 'workflow-automate' ), array( 'default' => array( 'title', 'editor' ) ) ),
				'description'       => $field( 'string', __( 'Description', 'workflow-automate' ), array( 'multiline' => true ) ),
				'rewrite_slug'      => $field( 'string', __( 'Custom URL Slug', 'workflow-automate' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_unregister_post_type_action',
			'label'         => __( 'Unregister Post Type', 'workflow-automate' ),
			'description'   => __( 'Unregisters a previously registered custom post type.', 'workflow-automate' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'unregisterPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'key' => $field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_post_type_features_action',
			'label'         => __( 'Add Post Type Features (Support)', 'workflow-automate' ),
			'description'   => __( 'Adds support for one or more features (e.g. thumbnail) to a post type.', 'workflow-automate' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'addPostTypeFeatures',
			'method_args'   => array(),
			'config_schema' => array(
				'key'      => $field( 'string', __( 'Post Type Key', 'workflow-automate' ), array( 'required' => true ) ),
				'supports' => $field( 'array', __( 'Features (comma-separated)', 'workflow-automate' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
