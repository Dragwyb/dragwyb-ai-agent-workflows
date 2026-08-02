<?php
/**
 * Business logic for WordPress Post and Post Type actions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\WordPress\Service;

use AIAWA\Plugin\Integration\WordPress\WordPressActionHelper;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post management domain service.
 */
final class PostWordPressService {

	private function applyPostTaxonomies( int $postId, array $config, bool $append ): void {
		$taxonomy = WordPressActionHelper::str( $config, 'taxonomy' );
		$terms    = WordPressActionHelper::parseList( $config['terms'] ?? array() );

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

	public function getAllPosts( array $config ): array {
		$limit = WordPressActionHelper::int( $config, 'limit' );
		$args  = array(
			'numberposts' => WordPressActionHelper::resolveListLimit( $limit > 0 ? $limit : null ),
			'post_type'   => WordPressActionHelper::str( $config, 'post_type', 'any' ),
			'post_status' => WordPressActionHelper::str( $config, 'post_status', 'any' ),
		);

		$search = WordPressActionHelper::str( $config, 'search' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$posts = get_posts( $args );

		return WordPressActionHelper::ok(
			array_map(
				function( $p ): array {
					return WordPressActionHelper::serializePost( $p );
				},
				$posts
			)
		);
	}

	public function getPostById( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( WordPressActionHelper::serializePost( $post, true ) );
	}

	public function getPostsByPostType( array $config ): array {
		$postType = WordPressActionHelper::str( $config, 'post_type' );

		if ( '' === $postType ) {
			return WordPressActionHelper::fail( __( 'Post type is required.', 'dragwyb-agentflow' ) );
		}

		$limit = WordPressActionHelper::int( $config, 'limit' );
		$posts = get_posts(
			array(
				'post_type'   => $postType,
				'numberposts' => WordPressActionHelper::resolveListLimit( $limit > 0 ? $limit : null ),
			)
		);

		return WordPressActionHelper::ok(
			array_map(
				function( $p ): array { return WordPressActionHelper::serializePost( $p ); },
				$posts
			)
		);
	}

	public function getPostsByMetadata( array $config ): array {
		$postType  = WordPressActionHelper::str( $config, 'post_type' );
		$metaKey   = WordPressActionHelper::str( $config, 'meta_key' );
		$metaValue = WordPressActionHelper::str( $config, 'meta_value' );

		if ( '' === $postType ) {
			return WordPressActionHelper::fail( __( 'Post type is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $metaValue ) {
			return WordPressActionHelper::fail( __( 'Meta value is required.', 'dragwyb-agentflow' ) );
		}

		$limit = WordPressActionHelper::int( $config, 'limit' );
		$posts = get_posts(
			array(
				'post_type'   => $postType,
				// This action's entire purpose is querying by an arbitrary, user-configured meta key/value pair; there is no fixed key to index around.
				'meta_key'    => $metaKey, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $metaValue, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'numberposts' => WordPressActionHelper::resolveListLimit( $limit > 0 ? $limit : null ),
			)
		);

		return WordPressActionHelper::ok(
			array_map(
				function( $p ): array { return WordPressActionHelper::serializePost( $p ); },
				$posts
			)
		);
	}

	public function getPostMetadata( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$meta = get_post_meta( $postId );

		if ( empty( $meta ) ) {
			return WordPressActionHelper::fail( __( 'Post metadata not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( $meta );
	}

	public function getPostMetadataByMetaKey( array $config ): array {
		$postId  = WordPressActionHelper::int( $config, 'post_id' );
		$metaKey = WordPressActionHelper::str( $config, 'meta_key' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'dragwyb-agentflow' ) );
		}

		$val = get_post_meta( $postId, $metaKey, true );

		if ( '' === $val ) {
			return WordPressActionHelper::fail( __( 'Post metadata not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( $metaKey => $val ) );
	}

	public function getPostPermalink( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$permalink = get_permalink( $postId );

		if ( ! $permalink ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'permalink' => $permalink ) );
	}

	public function getPostContent( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'content' => $post->post_content ) );
	}

	public function getPostExcerpt( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'excerpt' => $post->post_excerpt ) );
	}

	public function getPostStatus( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$status = get_post_status( $postId );

		if ( ! $status ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'post_status' => $status ) );
	}

	public function createNewPost( array $config ): array {
		$title = WordPressActionHelper::str( $config, 'title' );

		if ( '' === $title ) {
			return WordPressActionHelper::fail( __( 'Title is required.', 'dragwyb-agentflow' ) );
		}

		$postType = WordPressActionHelper::str( $config, 'post_type', 'post' );
		$rawType  = trim( (string) ( $config['post_type'] ?? '' ) );
		if ( function_exists( 'str_contains' ) && str_contains( $rawType, '{{' ) ) {
			$postType = 'post';
		}

		$content = WordPressActionHelper::resolvePostContent( $config );

		$postData = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => WordPressActionHelper::str( $config, 'excerpt' ),
			'post_type'    => $postType,
			'post_status'  => WordPressActionHelper::str( $config, 'post_status', 'draft' ),
		);

		$slug = WordPressActionHelper::str( $config, 'slug' );
		if ( '' !== $slug ) {
			$postData['post_name'] = $slug;
		}

		$date = WordPressActionHelper::str( $config, 'date' );
		if ( '' !== $date ) {
			$postData['post_date'] = $date;
		}

		$dateGmt = WordPressActionHelper::str( $config, 'date_gmt' );
		if ( '' !== $dateGmt ) {
			$postData['post_date_gmt'] = $dateGmt;
		}

		$parentId = WordPressActionHelper::int( $config, 'parent_id' );
		if ( $parentId > 0 ) {
			$postData['post_parent'] = $parentId;
		}

		$password = WordPressActionHelper::str( $config, 'post_password' );
		if ( '' !== $password ) {
			$postData['post_password'] = $password;
		}

		$author = WordPressActionHelper::int( $config, 'post_author' );
		if ( $author > 0 ) {
			$postData['post_author'] = $author;
		}

		$postId = wp_insert_post( $postData, true );

		if ( is_wp_error( $postId ) ) {
			return WordPressActionHelper::fail( $postId->get_error_message() );
		}

		update_post_meta( $postId, WordPressActionHelper::AUTOMATED_META_KEY, '1' );

		$customFields = WordPressActionHelper::keyValue( $config, 'custom_fields' );
		foreach ( $customFields as $k => $v ) {
			update_post_meta( $postId, $k, $v );
		}

		$this->applyPostTaxonomies( $postId, $config, false );
		WordPressActionHelper::attachFeaturedImage( $postId, $config );

		$post = get_post( $postId );

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'post'    => $post ? WordPressActionHelper::serializePost( $post, true ) : array(),
			)
		);
	}

	public function updateExistingPost( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$post = get_post( $postId );

		if ( ! $post ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		$postData = array( 'ID' => $postId );

		$title = WordPressActionHelper::str( $config, 'title' );
		if ( '' !== $title ) {
			$postData['post_title'] = $title;
		}

		$hasDesignSections = isset( $config['design_sections'] ) && '' !== trim( (string) $config['design_sections'] );
		$hasContent        = isset( $config['content'] ) && '' !== (string) $config['content'];

		if ( $hasDesignSections || $hasContent ) {
			$postData['post_content'] = WordPressActionHelper::resolvePostContent( $config );
		}

		if ( isset( $config['excerpt'] ) ) {
			$postData['post_excerpt'] = WordPressActionHelper::str( $config, 'excerpt' );
		}

		$postType = WordPressActionHelper::str( $config, 'post_type' );
		if ( '' !== $postType ) {
			$postData['post_type'] = $postType;
		}

		$status = WordPressActionHelper::str( $config, 'post_status' );
		if ( '' !== $status ) {
			$postData['post_status'] = $status;
		}

		$slug = WordPressActionHelper::str( $config, 'slug' );
		if ( '' !== $slug ) {
			$postData['post_name'] = $slug;
		}

		$date = WordPressActionHelper::str( $config, 'date' );
		if ( '' !== $date ) {
			$postData['post_date'] = $date;
		}

		$dateGmt = WordPressActionHelper::str( $config, 'date_gmt' );
		if ( '' !== $dateGmt ) {
			$postData['post_date_gmt'] = $dateGmt;
		}

		$parentId = WordPressActionHelper::int( $config, 'parent_id' );
		if ( $parentId > 0 ) {
			$postData['post_parent'] = $parentId;
		}

		$password = WordPressActionHelper::str( $config, 'post_password' );
		if ( '' !== $password ) {
			$postData['post_password'] = $password;
		}

		$author = WordPressActionHelper::int( $config, 'post_author' );
		if ( $author > 0 ) {
			$postData['post_author'] = $author;
		}

		$res = wp_update_post( $postData, true );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		update_post_meta( $postId, WordPressActionHelper::AUTOMATED_META_KEY, '1' );

		$customFields = WordPressActionHelper::keyValue( $config, 'custom_fields' );
		foreach ( $customFields as $k => $v ) {
			update_post_meta( $postId, $k, $v );
		}

		$this->applyPostTaxonomies( $postId, $config, true );
		WordPressActionHelper::attachFeaturedImage( $postId, $config );

		$updatedPost = get_post( $postId );

		return WordPressActionHelper::ok(
			array(
				'post_id' => $postId,
				'post'    => $updatedPost ? WordPressActionHelper::serializePost( $updatedPost, true ) : array(),
			)
		);
	}

	public function updatePostStatus( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );
		$status = WordPressActionHelper::str( $config, 'post_status' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $status ) {
			return WordPressActionHelper::fail( __( 'Post status is required.', 'dragwyb-agentflow' ) );
		}

		if ( ! get_post( $postId ) ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		$res = wp_update_post(
			array(
				'ID'          => $postId,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'post_id'     => $postId,
				'post_status' => $status,
			)
		);
	}

	public function deleteExistingPost( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		if ( ! get_post( $postId ) ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		$force = WordPressActionHelper::bool( $config, 'force_delete' );
		$res   = wp_delete_post( $postId, $force );

		if ( ! $res ) {
			return WordPressActionHelper::fail( __( 'Failed to delete post.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'post_id' => $postId ) );
	}

	public function getAllPostTypes(): array {
		$types = get_post_types( array(), 'objects' );
		$data  = array();

		foreach ( $types as $slug => $obj ) {
			$data[] = array(
				'slug'           => $slug,
				'label'          => $obj->label,
				'singular_label' => $obj->labels->singular_name ?? $obj->label,
				'public'         => $obj->public,
				'hierarchical'   => $obj->hierarchical,
			);
		}

		return WordPressActionHelper::ok( $data );
	}

	public function getPostType( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$type = get_post_type( $postId );

		if ( false === $type ) {
			return WordPressActionHelper::fail( __( 'Post not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'post_type' => $type ) );
	}

	public function registerPostType( array $config ): array {
		$key   = WordPressActionHelper::str( $config, 'key' );
		$label = WordPressActionHelper::str( $config, 'label' );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $label ) {
			return WordPressActionHelper::fail( __( 'Label is required.', 'dragwyb-agentflow' ) );
		}

		$supports = WordPressActionHelper::parseList( $config['supports'] ?? array( 'title', 'editor' ) );

		$args = array(
			'label'             => $label,
			'public'            => WordPressActionHelper::bool( $config, 'public', true ),
			'hierarchical'      => WordPressActionHelper::bool( $config, 'hierarchy', false ),
			'show_ui'           => WordPressActionHelper::bool( $config, 'show_ui', true ),
			'show_in_menu'      => WordPressActionHelper::bool( $config, 'show_in_menu', true ),
			'show_in_nav_menus' => WordPressActionHelper::bool( $config, 'show_in_nav_menu', true ),
			'show_in_admin_bar' => WordPressActionHelper::bool( $config, 'show_in_admin_bar', true ),
			'supports'          => $supports,
			'description'       => WordPressActionHelper::str( $config, 'description' ),
		);

		$pos = WordPressActionHelper::int( $config, 'menu_position' );
		if ( $pos > 0 ) {
			$args['menu_position'] = $pos;
		}

		$rewrite = WordPressActionHelper::str( $config, 'rewrite_slug' );
		if ( '' !== $rewrite ) {
			$args['rewrite'] = array( 'slug' => $rewrite );
		}

		$res = register_post_type( $key, $args );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok(
			array(
				'key'   => $key,
				'label' => $label,
			)
		);
	}

	public function unregisterPostType( array $config ): array {
		$key = WordPressActionHelper::str( $config, 'key' );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'dragwyb-agentflow' ) );
		}

		if ( ! post_type_exists( $key ) ) {
			return WordPressActionHelper::fail( __( 'Post type not found.', 'dragwyb-agentflow' ) );
		}

		$res = unregister_post_type( $key );

		if ( is_wp_error( $res ) ) {
			return WordPressActionHelper::fail( $res->get_error_message() );
		}

		return WordPressActionHelper::ok( array( 'key' => $key ) );
	}

	public function addPostTypeFeatures( array $config ): array {
		$key = WordPressActionHelper::str( $config, 'key' );

		if ( '' === $key ) {
			return WordPressActionHelper::fail( __( 'Post type key is required.', 'dragwyb-agentflow' ) );
		}

		if ( ! post_type_exists( $key ) ) {
			return WordPressActionHelper::fail( __( 'Post type not found.', 'dragwyb-agentflow' ) );
		}

		$supports = WordPressActionHelper::parseList( $config['supports'] ?? array() );

		if ( array() === $supports ) {
			return WordPressActionHelper::fail( __( 'Features (supports) are required.', 'dragwyb-agentflow' ) );
		}

		foreach ( $supports as $feature ) {
			add_post_type_support( $key, $feature );
		}

		return WordPressActionHelper::ok(
			array(
				'key'      => $key,
				'supports' => $supports,
			)
		);
	}
}
