<?php
/**
 * Business logic for WordPress Taxonomy, Term, Category, Tag, and Media actions.
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
 * Taxonomy and Media domain service.
 */
final class TaxonomyWordPressService {

	public function createTermByTax( array $config, string $taxonomy ): array {
		$name = WordPressActionHelper::str( $config, 'name' );

		if ( '' === $name ) {
			return WordPressActionHelper::fail( __( 'Name is required.', 'workflow-automate' ) );
		}

		$args = array();
		$slug = WordPressActionHelper::str( $config, 'slug' );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		$desc = WordPressActionHelper::str( $config, 'description' );
		if ( '' !== $desc ) {
			$args['description'] = $desc;
		}

		$res = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'term_id'          => $res['term_id'],
				'term_taxonomy_id' => $res['term_taxonomy_id'],
			)
		);
	}

	public function updateTermByTax( array $config, string $taxonomy ): array {
		$termId = WordPressActionHelper::int( $config, 'term_id' );

		if ( $termId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		$args = array();
		$name = WordPressActionHelper::str( $config, 'name' );
		if ( '' !== $name ) {
			$args['name'] = $name;
		}

		$slug = WordPressActionHelper::str( $config, 'slug' );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		$desc = WordPressActionHelper::str( $config, 'description' );
		if ( '' !== $desc ) {
			$args['description'] = $desc;
		}

		$res = wp_update_term( $termId, $taxonomy, $args );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'term_id'          => $res['term_id'],
				'term_taxonomy_id' => $res['term_taxonomy_id'],
			)
		);
	}

	public function deleteTermByTax( array $config, string $taxonomy ): array {
		$termId = WordPressActionHelper::int( $config, 'term_id' );

		if ( $termId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		$res = wp_delete_term( $termId, $taxonomy );

		if ( is_wp_error( $res ) || ! $res ) {
			return WordPressActionHelper::fail( is_wp_error( $res ) ? $res->get_error_message() : __( 'Failed to delete term.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'term_id' => $termId ) );
	}

	public function getAllTerms( array $config, string $forcedTaxonomy = '' ): array {
		$taxonomy = '' !== $forcedTaxonomy ? $forcedTaxonomy : WordPressActionHelper::str( $config, 'taxonomy' );

		$args = array( 'hide_empty' => false );
		if ( '' !== $taxonomy ) {
			$args['taxonomy'] = $taxonomy;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return WordPressActionHelper::fail( $terms->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array_map(
				function( $t ): array {
					return WordPressActionHelper::serializeTerm( $t );
				},
				$terms
			)
		);
	}

	public function getTermById( array $config, string $forcedTaxonomy = '' ): array {
		$termId   = WordPressActionHelper::int( $config, 'term_id' );
		$taxonomy = '' !== $forcedTaxonomy ? $forcedTaxonomy : WordPressActionHelper::str( $config, 'taxonomy' );

		if ( $termId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		$term = get_term( $termId, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return WordPressActionHelper::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializeTerm( $term ) );
	}

	public function getTerm( array $config ): array {
		return $this->getTermById( $config );
	}

	public function getTermByField( array $config ): array {
		$fieldKey   = WordPressActionHelper::str( $config, 'field_key' );
		$fieldValue = WordPressActionHelper::str( $config, 'field_value' );
		$taxonomy   = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $fieldKey ) {
			return WordPressActionHelper::fail( __( 'Field key is required.', 'workflow-automate' ) );
		}

		if ( '' === $fieldValue ) {
			return WordPressActionHelper::fail( __( 'Field value is required.', 'workflow-automate' ) );
		}

		$term = get_term_by( $fieldKey, $fieldValue, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return WordPressActionHelper::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializeTerm( $term ) );
	}

	public function getTermByTaxonomy( array $config ): array {
		return $this->getAllTerms( $config );
	}

	public function createNewTerm( array $config ): array {
		$taxonomy = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		return $this->createTermByTax( $config, $taxonomy );
	}

	public function updateTerm( array $config ): array {
		$taxonomy = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		return $this->updateTermByTax( $config, $taxonomy );
	}

	public function deleteTerm( array $config ): array {
		$taxonomy = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		return $this->deleteTermByTax( $config, $taxonomy );
	}

	public function addTagsToPost( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );
		$tags   = WordPressActionHelper::parseList( $config['tags'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $tags ) {
			return WordPressActionHelper::fail( __( 'Tags are required.', 'workflow-automate' ) );
		}

		$append = WordPressActionHelper::bool( $config, 'append' );
		$res    = wp_set_post_tags( $postId, $tags, $append );

		if ( false === $res || is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( __( 'Failed to assign tags to post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'tags'    => $tags,
			)
		);
	}

	public function removeTagsFromPost( array $config ): array {
		$postId     = WordPressActionHelper::int( $config, 'post_id' );
		$removeTags = WordPressActionHelper::parseList( $config['tags'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $removeTags ) {
			return WordPressActionHelper::fail( __( 'Tags are required.', 'workflow-automate' ) );
		}

		$current = wp_get_post_tags( $postId, array( 'fields' => 'names' ) );
		$kept    = array_diff( $current, $removeTags );

		wp_set_post_tags( $postId, $kept, false );

		return WordPressActionHelper::ok(
			array(
				'post_id'      => $postId,
				'removed_tags' => $removeTags,
			)
		);
	}

	public function addCategoryToPost( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );
		$cats   = WordPressActionHelper::parseList( $config['categories'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( array() === $cats ) {
			return WordPressActionHelper::fail( __( 'Categories are required.', 'workflow-automate' ) );
		}

		$append = WordPressActionHelper::bool( $config, 'append' );
		$catIds = array_map( 'intval', $cats );

		$res = wp_set_post_categories( $postId, $catIds, $append );

		if ( false === $res || is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( __( 'Failed to assign categories to post.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id'    => $postId,
				'categories' => $catIds,
			)
		);
	}

	public function getAllTaxonomies(): array {
		$taxonomies = get_taxonomies( array(), 'objects' );
		$data       = array();

		foreach ( $taxonomies as $slug => $obj ) {
			$data[] = array(
				'slug'           => $slug,
				'label'          => $obj->label,
				'singular_label' => $obj->labels->singular_name ?? $obj->label,
				'hierarchical'   => $obj->hierarchical,
				'post_types'     => $obj->object_type,
			);
		}

		return WordPressActionHelper::ok( $data );
	}

	public function getTaxonomy( array $config ): array {
		$slug = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $slug ) {
			return WordPressActionHelper::fail( __( 'Taxonomy slug is required.', 'workflow-automate' ) );
		}

		$obj = get_taxonomy( $slug );

		if ( ! $obj ) {
			return WordPressActionHelper::fail( __( 'Taxonomy not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok(
			array(
				'slug'           => $slug,
				'label'          => $obj->label,
				'singular_label' => $obj->labels->singular_name ?? $obj->label,
				'hierarchical'   => $obj->hierarchical,
				'post_types'     => $obj->object_type,
			)
		);
	}

	public function registerTaxonomy( array $config ): array {
		$slug      = WordPressActionHelper::str( $config, 'taxonomy' );
		$name      = WordPressActionHelper::str( $config, 'name' );
		$postTypes = WordPressActionHelper::parseList( $config['post_types'] ?? array() );

		if ( '' === $slug ) {
			return WordPressActionHelper::fail( __( 'Taxonomy slug is required.', 'workflow-automate' ) );
		}

		if ( '' === $name ) {
			return WordPressActionHelper::fail( __( 'Taxonomy name is required.', 'workflow-automate' ) );
		}

		if ( array() === $postTypes ) {
			return WordPressActionHelper::fail( __( 'Post types are required.', 'workflow-automate' ) );
		}

		$args = array(
			'label'        => $name,
			'hierarchical' => WordPressActionHelper::bool( $config, 'hierarchy', false ),
			'public'       => WordPressActionHelper::bool( $config, 'public', true ),
			'show_ui'      => WordPressActionHelper::bool( $config, 'show_ui', true ),
			'show_in_rest' => WordPressActionHelper::bool( $config, 'show_in_rest', true ),
			'description'  => WordPressActionHelper::str( $config, 'description' ),
		);

		$rewrite = WordPressActionHelper::str( $config, 'rewrite_slug' );
		if ( '' !== $rewrite ) {
			$args['rewrite'] = array( 'slug' => $rewrite );
		}

		$res = register_taxonomy( $slug, $postTypes, $args );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'taxonomy' => $slug,
				'name'     => $name,
			)
		);
	}

	public function unregisterTaxonomy( array $config ): array {
		$slug = WordPressActionHelper::str( $config, 'taxonomy' );

		if ( '' === $slug ) {
			return WordPressActionHelper::fail( __( 'Taxonomy slug is required.', 'workflow-automate' ) );
		}

		if ( ! taxonomy_exists( $slug ) ) {
			return WordPressActionHelper::fail( __( 'Taxonomy not found.', 'workflow-automate' ) );
		}

		$res = unregister_taxonomy( $slug );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'taxonomy' => $slug ) );
	}

	public function addTaxonomyToPost( array $config ): array {
		$postId   = WordPressActionHelper::int( $config, 'post_id' );
		$taxonomy = WordPressActionHelper::str( $config, 'taxonomy' );
		$terms    = WordPressActionHelper::parseList( $config['terms'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( array() === $terms ) {
			return WordPressActionHelper::fail( __( 'Terms are required.', 'workflow-automate' ) );
		}

		$append = WordPressActionHelper::bool( $config, 'append' );
		$res    = wp_set_object_terms( $postId, $terms, $taxonomy, $append );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id'  => $postId,
				'taxonomy' => $taxonomy,
				'terms'    => $terms,
			)
		);
	}

	public function removeTaxonomyFromPost( array $config ): array {
		$postId      = WordPressActionHelper::int( $config, 'post_id' );
		$taxonomy    = WordPressActionHelper::str( $config, 'taxonomy' );
		$removeTerms = WordPressActionHelper::parseList( $config['terms'] ?? array() );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return WordPressActionHelper::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( array() === $removeTerms ) {
			return WordPressActionHelper::fail( __( 'Terms are required.', 'workflow-automate' ) );
		}

		$current = wp_get_object_terms( $postId, $taxonomy, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $current ) ) {
			return WordPressActionHelper::fail( $current->get_error_message() );
		}

		$kept = array_diff( $current, $removeTerms );
		$res  = wp_set_object_terms( $postId, $kept, $taxonomy, false );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id'       => $postId,
				'taxonomy'      => $taxonomy,
				'removed_terms' => $removeTerms,
			)
		);
	}

	// Media management.
	public function addNewImage( array $config ): array {
		$url = WordPressActionHelper::str( $config, 'url' );

		if ( '' === $url ) {
			return WordPressActionHelper::fail( __( 'Image URL is required.', 'workflow-automate' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return WordPressActionHelper::fail( $tmp->get_error_message() );
		}

		$fileArray = array(
			'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$postData = array(
			'post_title'   => WordPressActionHelper::str( $config, 'title' ),
			'post_content' => WordPressActionHelper::str( $config, 'description' ),
			'post_excerpt' => WordPressActionHelper::str( $config, 'caption' ),
		);

		$id = media_handle_sideload( $fileArray, 0, null, $postData );
		wp_delete_file( $tmp );

		if ( is_wp_error( $id ) ) {
			return WordPressActionHelper::fail( $id->get_error_message() );
		}

		$alt = WordPressActionHelper::str( $config, 'alt_text' );
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}

		$attachment = get_post( $id );

		return WordPressActionHelper::ok(
			array(
				'media_id' => $id,
				'media'    => $attachment ? WordPressActionHelper::serializeMedia( $attachment ) : array(),
			)
		);
	}

	public function deleteMedia( array $config ): array {
		$mediaId = WordPressActionHelper::int( $config, 'media_id' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		if ( ! get_post( $mediaId ) ) {
			return WordPressActionHelper::fail( __( 'Media item not found.', 'workflow-automate' ) );
		}

		WordPressActionHelper::ensureMediaIncludes();

		$force = WordPressActionHelper::bool( $config, 'force_delete' );
		$res   = wp_delete_attachment( $mediaId, $force );

		if ( ! $res ) {
			return WordPressActionHelper::fail( __( 'Failed to delete media item.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( array( 'media_id' => $mediaId ) );
	}

	public function renameMedia( array $config ): array {
		$mediaId = WordPressActionHelper::int( $config, 'media_id' );
		$title   = WordPressActionHelper::str( $config, 'title' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'Title is required.', 'workflow-automate' ) );
		}

		if ( ! get_post( $mediaId ) ) {
			return WordPressActionHelper::fail( __( 'Media item not found.', 'workflow-automate' ) );
		}

		$res = wp_update_post(
			array(
				'ID'         => $mediaId,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'media_id' => $mediaId,
				'title'    => $title,
			)
		);
	}

	public function getAllMedia( array $config ): array {
		$limit       = WordPressActionHelper::int( $config, 'limit' );
		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'numberposts' => WordPressActionHelper::resolveListLimit( $limit > 0 ? $limit : null ),
				'post_status' => 'any',
			)
		);

		return WordPressActionHelper::ok(
			array_map(
				function( $m ): array {
					return WordPressActionHelper::serializeMedia( $m );
				},
				$attachments
			)
		);
	}

	public function getMediaByTitle( array $config ): array {
		$title = WordPressActionHelper::str( $config, 'title' );

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'Title is required.', 'workflow-automate' ) );
		}

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'title'       => $title,
				'numberposts' => 1,
				'post_status' => 'any',
			)
		);

		if ( empty( $attachments ) ) {
			return WordPressActionHelper::fail( __( 'Media item not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializeMedia( $attachments[0] ) );
	}

	public function getMediaById( array $config ): array {
		$mediaId = WordPressActionHelper::int( $config, 'media_id' );

		if ( $mediaId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Media id is required.', 'workflow-automate' ) );
		}

		$attachment = get_post( $mediaId );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return WordPressActionHelper::fail( __( 'Media item not found.', 'workflow-automate' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializeMedia( $attachment ) );
	}
}
