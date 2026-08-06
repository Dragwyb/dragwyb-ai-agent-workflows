<?php
/**
 * Post, Comment, and Post Type catalog definitions.
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
			$option( 'draft', __( 'Draft', 'dragwyb-visual-automation' ) ),
			$option( 'publish', __( 'Published', 'dragwyb-visual-automation' ) ),
			$option( 'pending', __( 'Pending Review', 'dragwyb-visual-automation' ) ),
			$option( 'private', __( 'Private', 'dragwyb-visual-automation' ) ),
			$option( 'future', __( 'Scheduled', 'dragwyb-visual-automation' ) ),
		);

		// Post management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_posts_action',
			'label'         => __( 'Get Post (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Lists posts (capped). Results include language + translations map for WPML/Polylang. Prefer Get Post by ID for full content.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getAllPosts',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type'   => $field( 'string', __( 'Post Type (optional)', 'dragwyb-visual-automation' ) ),
				'post_status' => $field( 'string', __( 'Post Status (optional, default any)', 'dragwyb-visual-automation' ), array( 'default' => 'any' ) ),
				'search'      => $field( 'string', __( 'Search (optional)', 'dragwyb-visual-automation' ), array( 'description' => __( 'Free-text search to narrow results.', 'dragwyb-visual-automation' ) ) ),
				'limit'       => $field(
					'integer',
					__( 'Limit', 'dragwyb-visual-automation' ),
					array(
						'default'     => 50,
						'description' => __( 'Max posts to return (default 50, max 200). Content is truncated in list results.', 'dragwyb-visual-automation' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_by_id_action',
			'label'         => __( 'Get Post (Single)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single post by its ID (full content + language/translations).', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostById',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_posts_by_post_type_action',
			'label'         => __( 'Get Posts By Post Type', 'dragwyb-visual-automation' ),
			'description'   => __( 'Lists posts of a given type (capped; includes language/translations).', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostsByPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type' => $field( 'string', __( 'Post Type', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'limit'     => $field(
					'integer',
					__( 'Limit', 'dragwyb-visual-automation' ),
					array(
						'default'     => 50,
						'description' => __( 'Max posts (default 50, max 200).', 'dragwyb-visual-automation' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_posts_by_metadata_action',
			'label'         => __( 'Get Posts by Metadata', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves posts of a given type matching a meta key/value pair.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostsByMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'post_type'  => $field( 'string', __( 'Post Type', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
				'meta_value' => $field( 'string', __( 'Meta Value', 'dragwyb-visual-automation' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- config field name for a builder UI, not a live query.
				'limit'      => $field( 'integer', __( 'Limit', 'dragwyb-visual-automation' ), array( 'default' => 50 ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_metadata_all_action',
			'label'         => __( 'Get Post Metadata (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every metadata entry for a post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_metadata_single_action',
			'label'         => __( 'Get Post Metadata (Single)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single metadata value for a post by meta key.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'  => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'meta_key' => $field( 'string', __( 'Meta Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_permalink_action',
			'label'         => __( 'Get Post Permalink', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the public URL of a post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostPermalink',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_content_action',
			'label'         => __( 'Get Post Content', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the raw content of a post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostContent',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_excerpt_action',
			'label'         => __( 'Get Post Excerpt', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the excerpt of a post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostExcerpt',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_status_action',
			'label'         => __( 'Get Post Status', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the status of a post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'getPostStatus',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_create_post_action',
			'label'         => __( 'Create New Post', 'dragwyb-visual-automation' ),
			'description'   => __( 'Creates a new post or page. For designed/colorful layouts pass design_sections JSON (hero/columns/cta) or Gutenberg block markup in content.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'createNewPost',
			'method_args'   => array(),
			'config_schema' => array(
				'title'             => $field( 'string', __( 'Title', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'content'           => $field(
					'string',
					__( 'Content', 'dragwyb-visual-automation' ),
					array(
						'multiline'   => true,
						'description' => __( 'Post/page body. For designed pages prefer design_sections, or pass full Gutenberg block markup (<!-- wp:heading -->…<!-- /wp:heading -->). Plain paragraphs alone are not a designed page.', 'dragwyb-visual-automation' ),
					)
				),
				'design_sections'   => $field(
					'string',
					__( 'Design sections (JSON)', 'dragwyb-visual-automation' ),
					array(
						'multiline'   => true,
						'description' => __( 'Preferred for designed pages. JSON array of sections converted server-side into Gutenberg blocks. Example: [{"type":"hero","heading":"Title","text":"Subtitle","background":"#0f172a","text_color":"#ffffff","button_text":"Explore","button_url":"/"},{"type":"columns","items":[{"title":"One","text":"…"},{"title":"Two","text":"…"}]},{"type":"cta","heading":"Ready?","button_text":"Go","background":"#f97316"}]. Types: hero, heading, paragraph, columns, cta, buttons, spacer, separator, group. When set, overrides content.', 'dragwyb-visual-automation' ),
					)
				),
				'excerpt'           => $field( 'string', __( 'Excerpt', 'dragwyb-visual-automation' ), array( 'multiline' => true ) ),
				'post_type'         => $field(
					'string',
					__( 'Post Type', 'dragwyb-visual-automation' ),
					array(
						'required'    => true,
						'default'     => '{{trigger.post_type}}',
						'description' => __( 'Defaults to the trigger post type ({{trigger.post_type}}). Change manually to force a type (e.g. page or post); a manual value always wins over the trigger.', 'dragwyb-visual-automation' ),
						'help'        => __( 'Leave as {{trigger.post_type}} to follow the saved post/page. Replace with page, post, or a CPT slug to override.', 'dragwyb-visual-automation' ),
					)
				),
				'post_status'       => $field(
					'select',
					__( 'Post Status', 'dragwyb-visual-automation' ),
					array(
						'required' => true,
						'default'  => 'draft',
						'options'  => $postStatusOptions,
					)
				),
				'slug'              => $field( 'string', __( 'Slug', 'dragwyb-visual-automation' ) ),
				'date'              => $field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'dragwyb-visual-automation' ) ),
				'date_gmt'          => $field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'dragwyb-visual-automation' ) ),
				'parent_id'         => $field( 'string', __( 'Parent Post ID', 'dragwyb-visual-automation' ) ),
				'post_password'     => $field( 'string', __( 'Post Password', 'dragwyb-visual-automation' ) ),
				'post_author'       => $field( 'string', __( 'Author (User ID)', 'dragwyb-visual-automation' ) ),
				'categories'        => $field( 'string', __( 'Categories (comma-separated category IDs)', 'dragwyb-visual-automation' ) ),
				'tags'              => $field( 'string', __( 'Tags (comma-separated)', 'dragwyb-visual-automation' ) ),
				'taxonomy'          => $field( 'string', __( 'Custom Taxonomy (optional)', 'dragwyb-visual-automation' ) ),
				'terms'             => $field( 'string', __( 'Custom Taxonomy Terms (comma-separated)', 'dragwyb-visual-automation' ) ),
				'custom_fields'     => $field( 'key_value', __( 'Custom Fields', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
				'featured_image'    => $field( 'string', __( 'Featured Image URL', 'dragwyb-visual-automation' ), array( 'description' => __( 'Optional. Only a real, direct image URL (ending in .jpg/.png/.webp etc.). Omit rather than using example.com or generic search URLs. Invalid URLs are skipped without failing the create.', 'dragwyb-visual-automation' ) ) ),
				'featured_image_id' => $field( 'string', __( 'Featured Image Attachment ID', 'dragwyb-visual-automation' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_post_action',
			'label'         => __( 'Update Post', 'dragwyb-visual-automation' ),
			'description'   => __( 'Updates fields on an existing post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'updateExistingPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'           => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'title'             => $field( 'string', __( 'Title', 'dragwyb-visual-automation' ) ),
				'content'           => $field(
					'string',
					__( 'Content', 'dragwyb-visual-automation' ),
					array(
						'multiline'   => true,
						'description' => __( 'Post/page body. For designed pages prefer design_sections, or pass full Gutenberg block markup. Plain paragraphs alone are not a designed page.', 'dragwyb-visual-automation' ),
					)
				),
				'design_sections'   => $field(
					'string',
					__( 'Design sections (JSON)', 'dragwyb-visual-automation' ),
					array(
						'multiline'   => true,
						'description' => __( 'Preferred for designed pages. JSON array of sections (hero, heading, paragraph, columns, cta, buttons, spacer, separator, group) converted into Gutenberg blocks. When set, overrides content.', 'dragwyb-visual-automation' ),
					)
				),
				'excerpt'           => $field( 'string', __( 'Excerpt', 'dragwyb-visual-automation' ), array( 'multiline' => true ) ),
				'post_type'         => $field( 'string', __( 'Post Type', 'dragwyb-visual-automation' ), array( 'description' => __( 'Use "page" when updating a page.', 'dragwyb-visual-automation' ) ) ),
				'post_status'       => $field(
					'select',
					__( 'Post Status', 'dragwyb-visual-automation' ),
					array(
						'default' => '',
						'options' => $postStatusOptions,
					)
				),
				'slug'              => $field( 'string', __( 'Slug', 'dragwyb-visual-automation' ) ),
				'date'              => $field( 'string', __( 'Date (Y-m-d H:i:s, optional)', 'dragwyb-visual-automation' ) ),
				'date_gmt'          => $field( 'string', __( 'Date GMT (Y-m-d H:i:s, optional)', 'dragwyb-visual-automation' ) ),
				'parent_id'         => $field( 'string', __( 'Parent Post ID', 'dragwyb-visual-automation' ) ),
				'post_password'     => $field( 'string', __( 'Post Password', 'dragwyb-visual-automation' ) ),
				'post_author'       => $field( 'string', __( 'Author (User ID)', 'dragwyb-visual-automation' ) ),
				'categories'        => $field( 'string', __( 'Categories (comma-separated category IDs)', 'dragwyb-visual-automation' ) ),
				'tags'              => $field( 'string', __( 'Tags (comma-separated)', 'dragwyb-visual-automation' ) ),
				'taxonomy'          => $field( 'string', __( 'Custom Taxonomy (optional)', 'dragwyb-visual-automation' ) ),
				'terms'             => $field( 'string', __( 'Custom Taxonomy Terms (comma-separated)', 'dragwyb-visual-automation' ) ),
				'custom_fields'     => $field( 'key_value', __( 'Custom Fields', 'dragwyb-visual-automation' ), array( 'default' => array() ) ),
				'featured_image'    => $field( 'string', __( 'Featured Image URL', 'dragwyb-visual-automation' ), array( 'description' => __( 'Optional. Only a real direct image URL. Invalid URLs are skipped without failing the update.', 'dragwyb-visual-automation' ) ) ),
				'featured_image_id' => $field( 'string', __( 'Featured Image Attachment ID', 'dragwyb-visual-automation' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_post_status_action',
			'label'         => __( 'Update Post Status', 'dragwyb-visual-automation' ),
			'description'   => __( 'Changes only the status of an existing post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'updatePostStatus',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'     => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'post_status' => $field(
					'select',
					__( 'Post Status', 'dragwyb-visual-automation' ),
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
			'label'         => __( 'Delete Post', 'dragwyb-visual-automation' ),
			'description'   => __( 'Deletes (or trashes) an existing post.', 'dragwyb-visual-automation' ),
			'group'         => 'post',
			'group_label'   => $groups['post'],
			'method'        => 'deleteExistingPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'force_delete' => $field( 'boolean', __( 'Force Delete (skip trash)', 'dragwyb-visual-automation' ), array( 'default' => false ) ),
			),
		);

		// Comment management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_post_comments_action',
			'label'         => __( 'Get Post Comments (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every comment across the whole site.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getAllPostComments',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_comments_action',
			'label'         => __( 'Get Post Comments (Single Post)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every comment on a specific post.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getPostComments',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_comments_action',
			'label'         => __( 'Get User Comments', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every comment authored by a registered user.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getUserComments',
			'method_args'   => array(),
			'config_schema' => array(
				'user_id' => $field( 'string', __( 'User ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_user_comments_by_email_action',
			'label'         => __( 'Get User Comments (By Email)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every comment authored using a given email address.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getUserCommentsByEmail',
			'method_args'   => array(),
			'config_schema' => array(
				'user_email' => $field( 'string', __( 'User Email', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_comment_metadata_all_action',
			'label'         => __( 'Get Comment Metadata (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every metadata entry for a comment.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getCommentMetadata',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id' => $field( 'string', __( 'Comment ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_comment_metadata_single_action',
			'label'         => __( 'Get Comment Metadata (Single)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves a single metadata value for a comment by meta key.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'getCommentMetadataByMetaKey',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id' => $field( 'string', __( 'Comment ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'meta_key'   => $field( 'string', __( 'Meta Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- config field name for a builder UI, not a live query.
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_create_comment_action',
			'label'         => __( 'Create New Comment', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds a new top-level comment to a post.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'createNewComment',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'comment'      => $field(
					'string',
					__( 'Comment', 'dragwyb-visual-automation' ),
					array(
						'required'  => true,
						'multiline' => true,
					)
				),
				'author_name'  => $field( 'string', __( 'Author Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'author_email' => $field( 'string', __( 'Author Email', 'dragwyb-visual-automation' ) ),
				'author_url'   => $field( 'string', __( 'Author URL', 'dragwyb-visual-automation' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_reply_to_comment_action',
			'label'         => __( 'Reply To Comment', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds a reply underneath an existing comment.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'replyToComment',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'      => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'parent_id'    => $field( 'string', __( 'Parent Comment ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'comment'      => $field(
					'string',
					__( 'Comment', 'dragwyb-visual-automation' ),
					array(
						'required'  => true,
						'multiline' => true,
					)
				),
				'author_name'  => $field( 'string', __( 'Author Name', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'author_email' => $field( 'string', __( 'Author Email', 'dragwyb-visual-automation' ) ),
				'author_url'   => $field( 'string', __( 'Author URL', 'dragwyb-visual-automation' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_comment_action',
			'label'         => __( 'Delete Comment', 'dragwyb-visual-automation' ),
			'description'   => __( 'Deletes (or trashes) an existing comment.', 'dragwyb-visual-automation' ),
			'group'         => 'comment',
			'group_label'   => $groups['comment'],
			'method'        => 'deleteExistingComment',
			'method_args'   => array(),
			'config_schema' => array(
				'comment_id'   => $field( 'string', __( 'Comment ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'force_delete' => $field( 'boolean', __( 'Force Delete (skip trash)', 'dragwyb-visual-automation' ), array( 'default' => false ) ),
			),
		);

		// Post type management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_post_types_action',
			'label'         => __( 'Get Post Type (All)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves every registered post type.', 'dragwyb-visual-automation' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'getAllPostTypes',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_type_action',
			'label'         => __( 'Get Post Type (Single)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Retrieves the post type slug of a given post.', 'dragwyb-visual-automation' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'getPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_register_post_type_action',
			'label'         => __( 'Register Post Type', 'dragwyb-visual-automation' ),
			'description'   => __( 'Registers a new custom post type.', 'dragwyb-visual-automation' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'registerPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'key'               => $field( 'string', __( 'Post Type Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'label'             => $field( 'string', __( 'Label', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'hierarchy'         => $field( 'boolean', __( 'Hierarchical', 'dragwyb-visual-automation' ), array( 'default' => false ) ),
				'public'            => $field( 'boolean', __( 'Public', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'show_ui'           => $field( 'boolean', __( 'Show Admin UI', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'show_in_menu'      => $field( 'boolean', __( 'Show in Admin Menu', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'show_in_nav_menu'  => $field( 'boolean', __( 'Show in Nav Menus', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'show_in_admin_bar' => $field( 'boolean', __( 'Show in Admin Bar', 'dragwyb-visual-automation' ), array( 'default' => true ) ),
				'menu_position'     => $field( 'string', __( 'Menu Position', 'dragwyb-visual-automation' ) ),
				'supports'          => $field( 'array', __( 'Supports (comma-separated, e.g. title,editor,thumbnail)', 'dragwyb-visual-automation' ), array( 'default' => array( 'title', 'editor' ) ) ),
				'description'       => $field( 'string', __( 'Description', 'dragwyb-visual-automation' ), array( 'multiline' => true ) ),
				'rewrite_slug'      => $field( 'string', __( 'Custom URL Slug', 'dragwyb-visual-automation' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_unregister_post_type_action',
			'label'         => __( 'Unregister Post Type', 'dragwyb-visual-automation' ),
			'description'   => __( 'Unregisters a previously registered custom post type.', 'dragwyb-visual-automation' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'unregisterPostType',
			'method_args'   => array(),
			'config_schema' => array(
				'key' => $field( 'string', __( 'Post Type Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_post_type_features_action',
			'label'         => __( 'Add Post Type Features (Support)', 'dragwyb-visual-automation' ),
			'description'   => __( 'Adds support for one or more features (e.g. thumbnail) to a post type.', 'dragwyb-visual-automation' ),
			'group'         => 'post_type',
			'group_label'   => $groups['post_type'],
			'method'        => 'addPostTypeFeatures',
			'method_args'   => array(),
			'config_schema' => array(
				'key'      => $field( 'string', __( 'Post Type Key', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
				'supports' => $field( 'array', __( 'Features (comma-separated)', 'dragwyb-visual-automation' ), array( 'required' => true ) ),
			),
		);

		return $definitions;
	}
}
