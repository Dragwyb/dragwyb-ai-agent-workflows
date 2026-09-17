<?php
/**
 * Taxonomy, Term, Category, Tag, and Media catalog definitions.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Integration\WordPress\Catalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WordPress Action Catalog definitions for Taxonomy, Category, Tag, and Media Management.
 */
final class TaxonomyActionCatalog {

	/**
	 * @param callable(string, string, array<string, mixed>=): array<string, mixed> $field
	 * @param callable(string, string): array{value: string, label: string}         $option
	 * @param array<string, string>                                                 $groups
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions( callable $field, callable $option, array $groups ): array {
		$definitions = array();

		$termFields = array(
			'name'        => $field( 'string', __( 'Name', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			'slug'        => $field( 'string', __( 'Slug', 'dragwyb-ai-agent-workflows' ) ),
			'description' => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
		);

		$termUpdateFields = array(
			'term_id'     => $field( 'string', __( 'Term ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			'name'        => $field( 'string', __( 'Name', 'dragwyb-ai-agent-workflows' ) ),
			'slug'        => $field( 'string', __( 'Slug', 'dragwyb-ai-agent-workflows' ) ),
			'description' => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
		);

		$termIdField = array(
			'term_id' => $field( 'string', __( 'Term ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
		);

		// Post tag management.
		$definitions[] = array(
			'slug'          => 'wp_create_post_tag_action',
			'label'         => __( 'Create Post Tag', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Creates a new post tag term.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'createTermByTax',
			'method_args'   => array( 'post_tag' ),
			'config_schema' => $termFields,
		);

		$definitions[] = array(
			'slug'          => 'wp_update_post_tag_action',
			'label'         => __( 'Update Post Tag', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Updates an existing post tag term.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'updateTermByTax',
			'method_args'   => array( 'post_tag' ),
			'config_schema' => $termUpdateFields,
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_post_tag_action',
			'label'         => __( 'Delete Post Tag', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Deletes an existing post tag term.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'deleteTermByTax',
			'method_args'   => array( 'post_tag' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_post_tags_action',
			'label'         => __( 'Get Post Tag (All)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves every post tag term.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'getAllTerms',
			'method_args'   => array( 'post_tag' ),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_post_tag_action',
			'label'         => __( 'Get Post Tag (Single)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a single post tag term by ID.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'getTermById',
			'method_args'   => array( 'post_tag' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug'          => 'wp_add_tags_to_post_action',
			'label'         => __( 'Add Tags to Post', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Assigns one or more tags to a post.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'addTagsToPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'tags'    => $field( 'string', __( 'Tags (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'append'  => $field( 'boolean', __( 'Append (keep existing tags)', 'dragwyb-ai-agent-workflows' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_tags_from_post_action',
			'label'         => __( 'Remove Tags From Post', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Removes one or more tags from a post.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'post_tag',
			'group_label'   => $groups['post_tag'],
			'method'        => 'removeTagsFromPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id' => $field( 'string', __( 'Post ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'tags'    => $field( 'string', __( 'Tags (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		// Media management.
		$definitions[] = array(
			'slug'          => 'wp_add_new_image_action',
			'label'         => __( 'Add New Image To Media Library', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Sideloads a remote image URL into the media library. Only call with a real direct image URL; skip rather than inventing URLs.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'addNewImage',
			'method_args'   => array(),
			'config_schema' => array(
				'url'         => $field(
					'string',
					__( 'Image URL', 'dragwyb-ai-agent-workflows' ),
					array(
						'required'    => true,
						'description' => __( 'Direct https image URL (.jpg/.png/.webp). Do not use example.com or search-page URLs.', 'dragwyb-ai-agent-workflows' ),
					)
				),
				'title'       => $field( 'string', __( 'Title', 'dragwyb-ai-agent-workflows' ) ),
				'alt_text'    => $field( 'string', __( 'Alt Text', 'dragwyb-ai-agent-workflows' ) ),
				'caption'     => $field( 'string', __( 'Caption', 'dragwyb-ai-agent-workflows' ) ),
				'description' => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_media_action',
			'label'         => __( 'Delete Media From Media Library', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Deletes an item from the media library.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'deleteMedia',
			'method_args'   => array(),
			'config_schema' => array(
				'media_id'     => $field( 'string', __( 'Media ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'force_delete' => $field( 'boolean', __( 'Force Delete (skip trash)', 'dragwyb-ai-agent-workflows' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_rename_media_action',
			'label'         => __( 'Rename Media', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Renames an item in the media library.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'renameMedia',
			'method_args'   => array(),
			'config_schema' => array(
				'media_id' => $field( 'string', __( 'Media ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'title'    => $field( 'string', __( 'New Title', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_media_action',
			'label'         => __( 'Get Media (All)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Lists media library items (capped). Prefer get-by-id/title when possible.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'getAllMedia',
			'method_args'   => array(),
			'config_schema' => array(
				'limit' => $field(
					'integer',
					__( 'Limit', 'dragwyb-ai-agent-workflows' ),
					array(
						'default'     => 50,
						'description' => __( 'Max items (default 50, max 200).', 'dragwyb-ai-agent-workflows' ),
					)
				),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_media_by_title_action',
			'label'         => __( 'Get Media (By Title)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a media library item by its title.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'getMediaByTitle',
			'method_args'   => array(),
			'config_schema' => array(
				'title' => $field( 'string', __( 'Title', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_media_by_id_action',
			'label'         => __( 'Get Media (By Id)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a media library item by its ID.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'media',
			'group_label'   => $groups['media'],
			'method'        => 'getMediaById',
			'method_args'   => array(),
			'config_schema' => array(
				'media_id' => $field( 'string', __( 'Media ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		// Taxonomy management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_taxonomies_action',
			'label'         => __( 'Get Taxonomy (All)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves every registered taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'getAllTaxonomies',
			'method_args'   => array(),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_taxonomy_action',
			'label'         => __( 'Get Taxonomy (Single)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a single registered taxonomy by slug.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'getTaxonomy',
			'method_args'   => array(),
			'config_schema' => array(
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_register_taxonomy_action',
			'label'         => __( 'Register Taxonomy', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Registers a new custom taxonomy for one or more post types.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'registerTaxonomy',
			'method_args'   => array(),
			'config_schema' => array(
				'taxonomy'     => $field( 'string', __( 'Taxonomy Slug', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'name'         => $field( 'string', __( 'Taxonomy Name', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'post_types'   => $field( 'array', __( 'Post Types (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'hierarchy'    => $field( 'boolean', __( 'Hierarchical', 'dragwyb-ai-agent-workflows' ), array( 'default' => false ) ),
				'public'       => $field( 'boolean', __( 'Public', 'dragwyb-ai-agent-workflows' ), array( 'default' => true ) ),
				'show_ui'      => $field( 'boolean', __( 'Show Admin UI', 'dragwyb-ai-agent-workflows' ), array( 'default' => true ) ),
				'show_in_rest' => $field( 'boolean', __( 'Show in REST API', 'dragwyb-ai-agent-workflows' ), array( 'default' => true ) ),
				'description'  => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
				'rewrite_slug' => $field( 'string', __( 'Custom URL Slug', 'dragwyb-ai-agent-workflows' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_unregister_taxonomy_action',
			'label'         => __( 'Unregister Taxonomy', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Unregisters a previously registered custom taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'unregisterTaxonomy',
			'method_args'   => array(),
			'config_schema' => array(
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_add_taxonomy_to_post_action',
			'label'         => __( 'Add Taxonomy to Post', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Assigns terms of a custom taxonomy to a post.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'addTaxonomyToPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'  => $field( 'string', __( 'Post ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'terms'    => $field( 'string', __( 'Terms (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'append'   => $field( 'boolean', __( 'Append (keep existing terms)', 'dragwyb-ai-agent-workflows' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_remove_taxonomy_from_post_action',
			'label'         => __( 'Remove Taxonomy From Post', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Removes terms of a custom taxonomy from a post.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'taxonomy',
			'group_label'   => $groups['taxonomy'],
			'method'        => 'removeTaxonomyFromPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'  => $field( 'string', __( 'Post ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'terms'    => $field( 'string', __( 'Terms (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		// Term management.
		$definitions[] = array(
			'slug'          => 'wp_get_all_terms_action',
			'label'         => __( 'Get Term (All)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves every term, optionally restricted to one taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'getAllTerms',
			'method_args'   => array(),
			'config_schema' => array(
				'taxonomy' => $field( 'string', __( 'Taxonomy (optional, leave empty for all)', 'dragwyb-ai-agent-workflows' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_term_action',
			'label'         => __( 'Get Term (Single)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a single term by ID within a taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'getTerm',
			'method_args'   => array(),
			'config_schema' => array(
				'term_id'  => $field( 'string', __( 'Term ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_term_by_field_action',
			'label'         => __( 'Get Term by Field', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a single term by an arbitrary field (slug, name, term_taxonomy_id, id).', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'getTermByField',
			'method_args'   => array(),
			'config_schema' => array(
				'field_key'   => $field(
					'select',
					__( 'Field', 'dragwyb-ai-agent-workflows' ),
					array(
						'required' => true,
						'default'  => 'slug',
						'options'  => array(
							$option( 'slug', __( 'Slug', 'dragwyb-ai-agent-workflows' ) ),
							$option( 'name', __( 'Name', 'dragwyb-ai-agent-workflows' ) ),
							$option( 'term_taxonomy_id', __( 'Term Taxonomy ID', 'dragwyb-ai-agent-workflows' ) ),
							$option( 'id', __( 'ID', 'dragwyb-ai-agent-workflows' ) ),
						),
					)
				),
				'field_value' => $field( 'string', __( 'Field Value', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy'    => $field( 'string', __( 'Taxonomy (required for Term Taxonomy ID)', 'dragwyb-ai-agent-workflows' ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_term_by_taxonomy_action',
			'label'         => __( 'Get Term by Taxonomy', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves every term belonging to a specific taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'getTermByTaxonomy',
			'method_args'   => array(),
			'config_schema' => array(
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_create_term_action',
			'label'         => __( 'Create New Term', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Creates a new term within any taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'createNewTerm',
			'method_args'   => array(),
			'config_schema' => array(
				'name'        => $field( 'string', __( 'Name', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy'    => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'slug'        => $field( 'string', __( 'Slug', 'dragwyb-ai-agent-workflows' ) ),
				'description' => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_update_term_action',
			'label'         => __( 'Update Term', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Updates an existing term within any taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'updateTerm',
			'method_args'   => array(),
			'config_schema' => array(
				'term_id'     => $field( 'string', __( 'Term ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy'    => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'name'        => $field( 'string', __( 'Name', 'dragwyb-ai-agent-workflows' ) ),
				'slug'        => $field( 'string', __( 'Slug', 'dragwyb-ai-agent-workflows' ) ),
				'description' => $field( 'string', __( 'Description', 'dragwyb-ai-agent-workflows' ), array( 'multiline' => true ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_term_action',
			'label'         => __( 'Delete Term', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Deletes an existing term within any taxonomy.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'term',
			'group_label'   => $groups['term'],
			'method'        => 'deleteTerm',
			'method_args'   => array(),
			'config_schema' => array(
				'term_id'  => $field( 'string', __( 'Term ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'taxonomy' => $field( 'string', __( 'Taxonomy', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
			),
		);

		// Category management.
		$definitions[] = array(
			'slug'          => 'wp_create_category_action',
			'label'         => __( 'Create Category', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Creates a new post category.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'createTermByTax',
			'method_args'   => array( 'category' ),
			'config_schema' => $termFields,
		);

		$definitions[] = array(
			'slug'          => 'wp_update_category_action',
			'label'         => __( 'Update Category', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Updates an existing post category.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'updateTermByTax',
			'method_args'   => array( 'category' ),
			'config_schema' => $termUpdateFields,
		);

		$definitions[] = array(
			'slug'          => 'wp_delete_category_action',
			'label'         => __( 'Delete Category', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Deletes an existing post category.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'deleteTermByTax',
			'method_args'   => array( 'category' ),
			'config_schema' => $termIdField,
		);

		$definitions[] = array(
			'slug'          => 'wp_add_category_to_post_action',
			'label'         => __( 'Add Category To Post', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Assigns one or more categories to a post.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'addCategoryToPost',
			'method_args'   => array(),
			'config_schema' => array(
				'post_id'    => $field( 'string', __( 'Post ID', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'categories' => $field( 'array', __( 'Category IDs (comma-separated)', 'dragwyb-ai-agent-workflows' ), array( 'required' => true ) ),
				'append'     => $field( 'boolean', __( 'Append (keep existing categories)', 'dragwyb-ai-agent-workflows' ), array( 'default' => false ) ),
			),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_all_categories_action',
			'label'         => __( 'Get Category (All)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves every post category.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'getAllTerms',
			'method_args'   => array( 'category' ),
			'config_schema' => array(),
		);

		$definitions[] = array(
			'slug'          => 'wp_get_category_action',
			'label'         => __( 'Get Category (Single)', 'dragwyb-ai-agent-workflows' ),
			'description'   => __( 'Retrieves a single post category by ID.', 'dragwyb-ai-agent-workflows' ),
			'group'         => 'category',
			'group_label'   => $groups['category'],
			'method'        => 'getTermById',
			'method_args'   => array( 'category' ),
			'config_schema' => $termIdField,
		);

		// WooCommerce Product Taxonomies.
		$productTaxonomies = array(
			'product_tag'  => array(
				'group'  => 'product_tag',
				'noun'   => __( 'Product Tag', 'dragwyb-ai-agent-workflows' ),
				'plural' => __( 'Product Tags', 'dragwyb-ai-agent-workflows' ),
			),
			'product_cat'  => array(
				'group'  => 'product_category',
				'noun'   => __( 'Product Category', 'dragwyb-ai-agent-workflows' ),
				'plural' => __( 'Product Categories', 'dragwyb-ai-agent-workflows' ),
			),
			'product_type' => array(
				'group'  => 'product_type',
				'noun'   => __( 'Product Type', 'dragwyb-ai-agent-workflows' ),
				'plural' => __( 'Product Types', 'dragwyb-ai-agent-workflows' ),
			),
		);

		foreach ( $productTaxonomies as $taxonomySlug => $meta ) {
			$group      = $meta['group'];
			$noun       = $meta['noun'];
			$slugPrefix = 'wp_' . $group;

			$definitions[] = array(
				'slug'          => $slugPrefix . '_create_action',
				/* translators: %s: e.g. "Product Tag". */
				'label'         => sprintf( __( 'Create %s', 'dragwyb-ai-agent-workflows' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description'   => sprintf( __( 'Creates a new %s term.', 'dragwyb-ai-agent-workflows' ), strtolower( $noun ) ),
				'group'         => $group,
				'group_label'   => $groups[ $group ],
				'method'        => 'createTermByTax',
				'method_args'   => array( $taxonomySlug ),
				'config_schema' => $termFields,
			);

			$definitions[] = array(
				'slug'          => $slugPrefix . '_update_action',
				/* translators: %s: e.g. "Product Tag". */
				'label'         => sprintf( __( 'Update %s', 'dragwyb-ai-agent-workflows' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description'   => sprintf( __( 'Updates an existing %s term.', 'dragwyb-ai-agent-workflows' ), strtolower( $noun ) ),
				'group'         => $group,
				'group_label'   => $groups[ $group ],
				'method'        => 'updateTermByTax',
				'method_args'   => array( $taxonomySlug ),
				'config_schema' => $termUpdateFields,
			);

			$definitions[] = array(
				'slug'          => $slugPrefix . '_delete_action',
				/* translators: %s: e.g. "Product Tag". */
				'label'         => sprintf( __( 'Delete %s', 'dragwyb-ai-agent-workflows' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description'   => sprintf( __( 'Deletes an existing %s term.', 'dragwyb-ai-agent-workflows' ), strtolower( $noun ) ),
				'group'         => $group,
				'group_label'   => $groups[ $group ],
				'method'        => 'deleteTermByTax',
				'method_args'   => array( $taxonomySlug ),
				'config_schema' => $termIdField,
			);

			$definitions[] = array(
				'slug'          => $slugPrefix . '_get_all_action',
				/* translators: %s: e.g. "Product Tag". */
				'label'         => sprintf( __( 'Get %s (All)', 'dragwyb-ai-agent-workflows' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description'   => sprintf( __( 'Retrieves every %s term.', 'dragwyb-ai-agent-workflows' ), strtolower( $noun ) ),
				'group'         => $group,
				'group_label'   => $groups[ $group ],
				'method'        => 'getAllTerms',
				'method_args'   => array( $taxonomySlug ),
				'config_schema' => array(),
			);

			$definitions[] = array(
				'slug'          => $slugPrefix . '_get_single_action',
				/* translators: %s: e.g. "Product Tag". */
				'label'         => sprintf( __( 'Get %s (Single)', 'dragwyb-ai-agent-workflows' ), $noun ),
				/* translators: %s: e.g. "product tag". */
				'description'   => sprintf( __( 'Retrieves a single %s term by ID.', 'dragwyb-ai-agent-workflows' ), strtolower( $noun ) ),
				'group'         => $group,
				'group_label'   => $groups[ $group ],
				'method'        => 'getTermById',
				'method_args'   => array( $taxonomySlug ),
				'config_schema' => $termIdField,
			);
		}

		return $definitions;
	}
}
