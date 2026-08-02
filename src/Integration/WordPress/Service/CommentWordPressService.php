<?php
/**
 * Business logic for WordPress Comment actions.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\WordPress\Service;

use DragwybAgentFlow\Plugin\Integration\WordPress\WordPressActionHelper;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comment management domain service.
 */
final class CommentWordPressService {

	public function getAllPostComments(): array {
		$comments = get_comments();

		return WordPressActionHelper::ok(
			array_map(
				function( $c ): array {
					return WordPressActionHelper::serializeComment( $c );
				},
				$comments
			)
		);
	}

	public function getPostComments( array $config ): array {
		$postId = WordPressActionHelper::int( $config, 'post_id' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		$comments = get_comments( array( 'post_id' => $postId ) );

		return WordPressActionHelper::ok(
			array_map(
				function( $c ): array {
					return WordPressActionHelper::serializeComment( $c );
				},
				$comments
			)
		);
	}

	public function getUserComments( array $config ): array {
		$userId = WordPressActionHelper::int( $config, 'user_id' );

		if ( $userId <= 0 ) {
			return WordPressActionHelper::fail( __( 'User id is required.', 'dragwyb-agentflow' ) );
		}

		$comments = get_comments( array( 'user_id' => $userId ) );

		return WordPressActionHelper::ok(
			array_map(
				function( $c ): array {
					return WordPressActionHelper::serializeComment( $c );
				},
				$comments
			)
		);
	}

	public function getUserCommentsByEmail( array $config ): array {
		$email = WordPressActionHelper::str( $config, 'user_email' );

		if ( '' === $email ) {
			return WordPressActionHelper::fail( __( 'User email is required.', 'dragwyb-agentflow' ) );
		}

		$comments = get_comments( array( 'author_email' => $email ) );

		return WordPressActionHelper::ok(
			array_map(
				function( $c ): array {
					return WordPressActionHelper::serializeComment( $c );
				},
				$comments
			)
		);
	}

	public function getCommentMetadata( array $config ): array {
		$commentId = WordPressActionHelper::int( $config, 'comment_id' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'dragwyb-agentflow' ) );
		}

		$meta = get_comment_meta( $commentId );

		if ( empty( $meta ) ) {
			return WordPressActionHelper::fail( __( 'Comment metadata not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( $meta );
	}

	public function getCommentMetadataByMetaKey( array $config ): array {
		$commentId = WordPressActionHelper::int( $config, 'comment_id' );
		$metaKey   = WordPressActionHelper::str( $config, 'meta_key' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $metaKey ) {
			return WordPressActionHelper::fail( __( 'Meta key is required.', 'dragwyb-agentflow' ) );
		}

		$val = get_comment_meta( $commentId, $metaKey, true );

		if ( '' === $val ) {
			return WordPressActionHelper::fail( __( 'Comment metadata not found.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( $metaKey => $val ) );
	}

	public function createNewComment( array $config ): array {
		$postId  = WordPressActionHelper::int( $config, 'post_id' );
		$content = WordPressActionHelper::str( $config, 'comment' );
		$author  = WordPressActionHelper::str( $config, 'author_name' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $content ) {
			return WordPressActionHelper::fail( __( 'Comment content is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $author ) {
			return WordPressActionHelper::fail( __( 'Author name is required.', 'dragwyb-agentflow' ) );
		}

		$commentData = array(
			'comment_post_ID'      => $postId,
			'comment_content'      => $content,
			'comment_author'       => $author,
			'comment_author_email' => WordPressActionHelper::str( $config, 'author_email' ),
			'comment_author_url'   => WordPressActionHelper::str( $config, 'author_url' ),
		);

		$commentId = wp_insert_comment( $commentData );

		if ( ! $commentId ) {
			return WordPressActionHelper::fail( __( 'Failed to create comment.', 'dragwyb-agentflow' ) );
		}

		$comment = get_comment( $commentId );

		return WordPressActionHelper::ok(
			array(
				'comment_id' => $commentId,
				'comment'    => $comment ? WordPressActionHelper::serializeComment( $comment ) : array(),
			)
		);
	}

	public function replyToComment( array $config ): array {
		$postId   = WordPressActionHelper::int( $config, 'post_id' );
		$parentId = WordPressActionHelper::int( $config, 'parent_id' );
		$content  = WordPressActionHelper::str( $config, 'comment' );
		$author   = WordPressActionHelper::str( $config, 'author_name' );

		if ( $postId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Post id is required.', 'dragwyb-agentflow' ) );
		}

		if ( $parentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Parent comment id is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $content ) {
			return WordPressActionHelper::fail( __( 'Comment content is required.', 'dragwyb-agentflow' ) );
		}

		if ( '' === $author ) {
			return WordPressActionHelper::fail( __( 'Author name is required.', 'dragwyb-agentflow' ) );
		}

		$commentData = array(
			'comment_post_ID'      => $postId,
			'comment_parent'       => $parentId,
			'comment_content'      => $content,
			'comment_author'       => $author,
			'comment_author_email' => WordPressActionHelper::str( $config, 'author_email' ),
			'comment_author_url'   => WordPressActionHelper::str( $config, 'author_url' ),
		);

		$commentId = wp_insert_comment( $commentData );

		if ( ! $commentId ) {
			return WordPressActionHelper::fail( __( 'Failed to create comment reply.', 'dragwyb-agentflow' ) );
		}

		$comment = get_comment( $commentId );

		return WordPressActionHelper::ok(
			array(
				'comment_id' => $commentId,
				'comment'    => $comment ? WordPressActionHelper::serializeComment( $comment ) : array(),
			)
		);
	}

	public function deleteExistingComment( array $config ): array {
		$commentId = WordPressActionHelper::int( $config, 'comment_id' );

		if ( $commentId <= 0 ) {
			return WordPressActionHelper::fail( __( 'Comment id is required.', 'dragwyb-agentflow' ) );
		}

		if ( ! get_comment( $commentId ) ) {
			return WordPressActionHelper::fail( __( 'Comment not found.', 'dragwyb-agentflow' ) );
		}

		$force = WordPressActionHelper::bool( $config, 'force_delete' );
		$res   = wp_delete_comment( $commentId, $force );

		if ( ! $res ) {
			return WordPressActionHelper::fail( __( 'Failed to delete comment.', 'dragwyb-agentflow' ) );
		}

		return WordPressActionHelper::ok( array( 'comment_id' => $commentId ) );
	}
}
