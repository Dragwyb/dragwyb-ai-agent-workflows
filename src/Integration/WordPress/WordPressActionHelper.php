<?php
/**
 * Shared helpers for WordPress workflow actions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers shared by every WordPress action (response shaping, WP
 * data lookups, and config-to-WordPress-field mapping).
 */
final class WordPressActionHelper {

	/**
	 * Post meta key stamped on posts this plugin creates, so save_post
	 * triggers do not re-fire on the automation's own output (e.g. a
	 * translated post created by an agent).
	 */
	public const AUTOMATED_META_KEY = '_wfa_automated';

	/**
	 * Builds a successful action result.
	 *
	 * @param mixed                $data  Primary result payload.
	 * @param array<string, mixed> $extra Additional top-level keys to merge in.
	 *
	 * @return array{success: bool, data: mixed}
	 */
	public static function ok( $data = array(), array $extra = array() ): array {
		$response = array(
			'success' => true,
			'data'    => $data,
		);

		return array() === $extra ? $response : array_merge( $response, $extra );
	}

	/**
	 * Builds a failed action result.
	 *
	 * @param string $error Human-readable error message.
	 *
	 * @return array{success: bool, error: string}
	 */
	public static function fail( string $error ): array {
		return array(
			'success' => false,
			'error'   => $error,
		);
	}

	/**
	 * @return array<string, array<string, mixed>> Role slug => role data.
	 */
	public static function getWpRoles(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		return $wp_roles->roles ?? array();
	}

	/**
	 * Default cap for list queries returned to agents / workflows.
	 */
	public const DEFAULT_LIST_LIMIT = 50;

	/**
	 * Hard ceiling for list queries.
	 */
	public const MAX_LIST_LIMIT = 200;

	/**
	 * Resolves a safe list limit (filterable).
	 *
	 * @param int|null $requested Optional requested limit from action config.
	 */
	public static function resolveListLimit( ?int $requested = null ): int {
		$limit = null !== $requested && $requested > 0 ? $requested : self::DEFAULT_LIST_LIMIT;

		/**
		 * Filters WordPress list action result size (posts, media, comments, users).
		 *
		 * @param int $limit Requested limit.
		 */
		$limit = (int) apply_filters( 'wfa_wp_list_limit', $limit );

		if ( $limit < 1 ) {
			$limit = self::DEFAULT_LIST_LIMIT;
		}

		return min( $limit, self::MAX_LIST_LIMIT );
	}

	/**
	 * @param null|string            $postType Post type slug, or null for any.
	 * @param null|array<int, mixed> $metaQuery WP_Query meta_query clauses.
	 * @param null|string            $search    Free-text search term.
	 * @param string                 $status    Post status (default 'any').
	 * @param int|null               $limit     Max posts (default DEFAULT_LIST_LIMIT).
	 *
	 * @return \WP_Post[]
	 */
	public static function getPosts( ?string $postType = null, ?array $metaQuery = null, ?string $search = null, string $status = 'any', ?int $limit = null ): array {
		$args = array(
			'posts_per_page' => self::resolveListLimit( $limit ),
			'post_status'    => $status,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $postType ) ) {
			$args['post_type'] = $postType;
		}

		if ( ! empty( $metaQuery ) ) {
			$args['meta_query'] = $metaQuery; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Author-configured filter, not a raw query.
		}

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		return get_posts( $args );
	}

	/**
	 * @param null|int    $postId Restrict to this post, or null for any.
	 * @param null|int    $userId Restrict to this comment author user id, or null for any.
	 * @param null|string $email  Restrict to this comment author email, or null for any.
	 * @param int|null    $limit  Max comments.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function getComments( ?int $postId = null, ?int $userId = null, ?string $email = null, ?int $limit = null ): array {
		$args = array(
			'order'  => 'ASC',
			'number' => self::resolveListLimit( $limit ),
			'status' => 'approve',
		);

		if ( ! empty( $postId ) ) {
			$args['post_id'] = $postId;
		}

		if ( ! empty( $userId ) ) {
			$args['user_id'] = $userId;
		}

		if ( ! empty( $email ) ) {
			$args['author_email'] = $email;
		}

		$comments = get_comments( $args );
		if ( ! is_array( $comments ) ) {
			return array();
		}

		return array_map(
			static fn( $comment ): array => self::serializeComment( $comment ),
			$comments
		);
	}

	/**
	 * @param array<string, mixed> $args wp_insert_term() args (slug, description, ...).
	 *
	 * @return array{success: bool, data?: array<string, mixed>, error?: string}
	 */
	public static function insertTerm( string $name, string $taxonomy, array $args = array() ): array {
		if ( '' === $name ) {
			return self::fail( __( 'Term name is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return self::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		$term = wp_insert_term( $name, $taxonomy, array_filter( $args, static fn( $value ) => null !== $value && '' !== $value ) );

		if ( is_wp_error( $term ) ) {
			return self::fail( $term->get_error_message() );
		}

		$term_id = isset( $term['term_id'] ) ? (int) $term['term_id'] : 0;
		self::markAutomatedTerm( $term_id );

		return self::ok( (array) $term );
	}

	/**
	 * @param array<string, mixed> $args wp_update_term() args (name, slug, description, ...).
	 *
	 * @return array{success: bool, data?: array<string, mixed>, error?: string}
	 */
	public static function updateTerm( int $termId, string $taxonomy, array $args ): array {
		if ( $termId <= 0 ) {
			return self::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return self::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( ! get_term( $termId, $taxonomy ) ) {
			return self::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		$args = array_filter( $args, static fn( $value ) => null !== $value && '' !== $value );

		if ( array() === $args ) {
			return self::fail( __( 'Nothing to update.', 'workflow-automate' ) );
		}

		$term = wp_update_term( $termId, $taxonomy, $args );

		if ( is_wp_error( $term ) ) {
			return self::fail( $term->get_error_message() );
		}

		return self::ok( (array) $term );
	}

	/**
	 * @param array<string, mixed> $args wp_delete_term() args.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, error?: string}
	 */
	public static function deleteTerm( int $termId, string $taxonomy, array $args = array() ): array {
		if ( $termId <= 0 ) {
			return self::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return self::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		if ( ! get_term( $termId, $taxonomy ) ) {
			return self::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		$result = wp_delete_term( $termId, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return self::fail( $result->get_error_message() );
		}

		if ( ! $result ) {
			return self::fail( __( 'Failed to delete term.', 'workflow-automate' ) );
		}

		return self::ok( array( 'term_id' => $termId ) );
	}

	/**
	 * @return array{success: bool, data?: array<string, mixed>, error?: string}
	 */
	public static function getTerm( int $termId, string $taxonomy ): array {
		if ( $termId <= 0 ) {
			return self::fail( __( 'Term id is required.', 'workflow-automate' ) );
		}

		if ( '' === $taxonomy ) {
			return self::fail( __( 'Taxonomy is required.', 'workflow-automate' ) );
		}

		$term = get_term( $termId, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return self::fail( __( 'Term not found.', 'workflow-automate' ) );
		}

		return self::ok( (array) $term );
	}

	/**
	 * @param null|string $taxonomy Restrict to this taxonomy, or null for every public taxonomy.
	 *
	 * @return array{success: bool, data?: array<int, array<string, mixed>>, error?: string}
	 */
	public static function getTerms( ?string $taxonomy = null ): array {
		$args = array(
			'taxonomy'   => empty( $taxonomy ) ? get_taxonomies( array( 'public' => true ) ) : $taxonomy,
			'orderby'    => 'term_id',
			'hide_empty' => false,
		);

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return self::fail( $terms->get_error_message() );
		}

		return self::ok(
			array_map(
				static fn( $term ) => (array) $term,
				$terms
			)
		);
	}

	/**
	 * Maps snake_case config keys to `wp_insert_user()`/`wp_update_user()` fields.
	 *
	 * @param array<string, mixed> $config Action config.
	 *
	 * @return array<string, mixed>
	 */
	public static function mapUserFields( array $config ): array {
		$fields = array(
			'username'     => 'user_login',
			'email'        => 'user_email',
			'nickname'     => 'nickname',
			'display_name' => 'display_name',
			'first_name'   => 'first_name',
			'last_name'    => 'last_name',
			'user_url'     => 'user_url',
			'description'  => 'description',
		);

		$mapped = array();

		foreach ( $fields as $configKey => $wpKey ) {
			if ( isset( $config[ $configKey ] ) && '' !== $config[ $configKey ] ) {
				$mapped[ $wpKey ] = $config[ $configKey ];
			}
		}

		return $mapped;
	}

	/**
	 * Maps snake_case config keys to `wp_insert_post()`/`wp_update_post()` fields.
	 *
	 * @param array<string, mixed> $config Action config.
	 *
	 * @return array<string, mixed>
	 */
	public static function mapPostFields( array $config ): array {
		$fields = array(
			'post_id'       => 'ID',
			'title'         => 'post_title',
			'content'       => 'post_content',
			'excerpt'       => 'post_excerpt',
			'date'          => 'post_date',
			'date_gmt'      => 'post_date_gmt',
			'slug'          => 'post_name',
			'parent_id'     => 'post_parent',
			'post_password' => 'post_password',
			'post_type'     => 'post_type',
			'post_status'   => 'post_status',
			'post_author'   => 'post_author',
		);

		$mapped = array();

		foreach ( $fields as $configKey => $wpKey ) {
			if ( isset( $config[ $configKey ] ) && '' !== $config[ $configKey ] ) {
				$mapped[ $wpKey ] = $config[ $configKey ];
			}
		}

		if ( ! empty( $config['categories'] ) ) {
			$mapped['post_category'] = array_map( 'intval', self::parseList( $config['categories'] ) );
		}

		return $mapped;
	}

	/**
	 * Maps snake_case config keys to `wp_new_comment()` fields.
	 *
	 * @param array<string, mixed> $config Action config.
	 *
	 * @return array<string, mixed>
	 */
	public static function mapCommentFields( array $config ): array {
		return array(
			'comment_post_ID'      => isset( $config['post_id'] ) ? (int) $config['post_id'] : 0,
			'comment_author'       => $config['author_name'] ?? '',
			'comment_author_email' => $config['author_email'] ?? '',
			'comment_author_url'   => $config['author_url'] ?? '',
			'comment_content'      => $config['comment'] ?? '',
			'comment_type'         => 'comment',
			'comment_parent'       => isset( $config['parent_id'] ) ? (int) $config['parent_id'] : 0,
			'comment_author_IP'    => '',
			'comment_agent'        => 'Workflow Automate',
			'comment_date'         => gmdate( 'Y-m-d H:i:s' ),
			'comment_approved'     => 1,
		);
	}

	/**
	 * Sets a post's featured image from either an attachment id or a
	 * remote image URL (sideloaded into the media library).
	 *
	 * Invalid / failed images are skipped with a warning — they must not
	 * abort the surrounding create/update post call.
	 *
	 * @param int                  $postId Target post id.
	 * @param array<string, mixed> $config Action config (`featured_image_id` or `featured_image`).
	 *
	 * @return null|array{warning: string} Null on success/noop; warning when image is skipped.
	 */
	public static function setPostFeaturedImage( int $postId, array $config ): ?array {
		$imageId  = isset( $config['featured_image_id'] ) ? (int) $config['featured_image_id'] : 0;
		$imageUrl = isset( $config['featured_image'] ) ? trim( (string) $config['featured_image'] ) : '';

		if ( $imageId <= 0 && '' === $imageUrl ) {
			return null;
		}

		self::ensureMediaIncludes();

		if ( $imageId <= 0 ) {
			if ( ! filter_var( $imageUrl, FILTER_VALIDATE_URL ) ) {
				return array(
					'warning' => __( 'Featured image skipped: URL is not valid. Omit featured_image unless you have a real direct image URL.', 'workflow-automate' ),
				);
			}

			$sanitizedUrl = filter_var( $imageUrl, FILTER_SANITIZE_URL );
			$sideloaded   = media_sideload_image( $sanitizedUrl, $postId, null, 'id' );

			if ( is_wp_error( $sideloaded ) ) {
				return array(
					'warning' => sprintf(
						/* translators: %s: error message */
						__( 'Featured image skipped: %s', 'workflow-automate' ),
						$sideloaded->get_error_message()
					),
				);
			}

			$imageId = (int) $sideloaded;
		}

		if ( $imageId <= 0 || ! wp_attachment_is_image( $imageId ) ) {
			return array(
				'warning' => __( 'Featured image skipped: invalid attachment.', 'workflow-automate' ),
			);
		}

		if ( ! set_post_thumbnail( $postId, $imageId ) ) {
			return array(
				'warning' => __( 'Featured image skipped: could not set thumbnail.', 'workflow-automate' ),
			);
		}

		return null;
	}
	/**
	 * Normalizes a comma-separated string or array into a list of unique,
	 * non-empty, trimmed string values.
	 *
	 * @param mixed $value CSV string or array.
	 *
	 * @return array<int, string>
	 */
	public static function parseList( $value ): array {
		if ( empty( $value ) ) {
			return array();
		}

		$items = is_array( $value ) ? $value : explode( ',', (string) $value );

		$items = array_map(
			static fn( $item ): string => trim( (string) $item ),
			$items
		);

		$items = array_filter(
			$items,
			static fn( string $item ): bool => '' !== $item
		);

		return array_values( array_unique( $items ) );
	}

	/**
	 * @param \WP_User|object|array<string, mixed> $user
	 *
	 * @return array<string, mixed>
	 */
	public static function serializeUser( $user ): array {
		if ( is_array( $user ) ) {
			return $user;
		}

		if ( ! is_object( $user ) ) {
			return array();
		}

		$data = $user->data ?? $user;
		$id   = isset( $user->ID ) ? (int) $user->ID : 0;

		return array(
			'id'           => $id,
			'username'     => isset( $data->user_login ) ? (string) $data->user_login : '',
			'email'        => isset( $data->user_email ) ? (string) $data->user_email : '',
			'display_name' => isset( $data->display_name ) ? (string) $data->display_name : '',
			'nickname'     => isset( $user->nickname ) ? (string) $user->nickname : '',
			'first_name'   => isset( $user->first_name ) ? (string) $user->first_name : '',
			'last_name'    => isset( $user->last_name ) ? (string) $user->last_name : '',
			'user_url'     => isset( $data->user_url ) ? (string) $data->user_url : '',
			'description'  => isset( $user->description ) ? (string) $user->description : '',
			'registered'   => isset( $data->user_registered ) ? (string) $data->user_registered : '',
			'roles'        => isset( $user->roles ) && is_array( $user->roles ) ? array_values( $user->roles ) : array(),
			'avatar_url'   => $id > 0 ? (string) get_avatar_url( $id ) : '',
		);
	}

	/**
	 * @param \WP_Post $post         Post object.
	 * @param bool     $full_content When false, truncate content for list payloads.
	 *
	 * @return array<string, mixed>
	 */
	public static function serializePost( \WP_Post $post, bool $full_content = true ): array {
		$content           = (string) $post->post_content;
		$content_truncated = false;

		if ( ! $full_content && strlen( $content ) > 800 ) {
			$content           = substr( $content, 0, 800 ) . '…';
			$content_truncated = true;
		}

		$thumb_id = (int) get_post_thumbnail_id( $post );

		return array(
			'id'                 => (int) $post->ID,
			'title'              => (string) $post->post_title,
			'content'            => $content,
			'content_truncated'  => $content_truncated,
			'excerpt'            => (string) $post->post_excerpt,
			'status'             => (string) $post->post_status,
			'type'               => (string) $post->post_type,
			'slug'               => (string) $post->post_name,
			'date'               => (string) $post->post_date,
			'date_gmt'           => (string) $post->post_date_gmt,
			'author'             => (int) $post->post_author,
			'parent_id'          => (int) $post->post_parent,
			'permalink'          => (string) get_permalink( $post ),
			'featured_image_id'  => $thumb_id,
			'featured_image_url' => $thumb_id > 0 ? (string) wp_get_attachment_url( $thumb_id ) : '',
			'categories'         => wp_get_post_categories( (int) $post->ID ),
			'tags'               => wp_get_post_tags( (int) $post->ID, array( 'fields' => 'names' ) ),
		);
	}

	/**
	 * @param \WP_Comment|object $comment Comment object.
	 *
	 * @return array<string, mixed>
	 */
	public static function serializeComment( $comment ): array {
		if ( ! is_object( $comment ) ) {
			return array();
		}

		return array(
			'id'           => isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0,
			'post_id'      => isset( $comment->comment_post_ID ) ? (int) $comment->comment_post_ID : 0,
			'author'       => isset( $comment->comment_author ) ? (string) $comment->comment_author : '',
			'author_email' => isset( $comment->comment_author_email ) ? (string) $comment->comment_author_email : '',
			'content'      => isset( $comment->comment_content ) ? (string) $comment->comment_content : '',
			'date'         => isset( $comment->comment_date ) ? (string) $comment->comment_date : '',
			'status'       => isset( $comment->comment_approved ) ? (string) $comment->comment_approved : '',
			'parent_id'    => isset( $comment->comment_parent ) ? (int) $comment->comment_parent : 0,
			'user_id'      => isset( $comment->user_id ) ? (int) $comment->user_id : 0,
		);
	}

	/**
	 * Marks a post as created/updated by Workflow Automate.
	 *
	 * Stores a unix timestamp so triggers only suppress the brief follow-up
	 * saves right after our write — not forever. Permanent suppression blocked
	 * human edits of agent-created pages (e.g. Save Post never fired for page).
	 *
	 * Mid-write loops are still prevented by TriggerReentrancyGuard::isWriting().
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public static function markAutomatedPost( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		update_post_meta( $post_id, self::AUTOMATED_META_KEY, (string) time() );
	}

	/**
	 * True when this post was just written by a WFA action (short grace window).
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function isAutomatedPost( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$value = get_post_meta( $post_id, self::AUTOMATED_META_KEY, true );

		if ( '' === (string) $value || false === $value || null === $value ) {
			return false;
		}

		/**
		 * Seconds after a WFA create/update during which save_post is ignored
		 * for that post (prevents immediate echo triggers). After this window,
		 * human edits of the post may start workflows again.
		 *
		 * @param int $seconds Grace period in seconds.
		 * @param int $post_id Post id.
		 */
		$grace = (int) apply_filters( 'wfa_automated_post_grace_seconds', 45, $post_id );

		if ( $grace < 1 ) {
			$grace = 45;
		}

		// New format: unix timestamp.
		if ( is_numeric( $value ) ) {
			$age = time() - (int) $value;

			return $age >= 0 && $age < $grace;
		}

		// Legacy permanent "1" marks: do not block human edits anymore.
		// Mid-write protection remains via TriggerReentrancyGuard::isWriting().
		return false;
	}

	/**
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public static function markAutomatedUser( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::AUTOMATED_META_KEY, '1' );
	}

	/**
	 * @param int $user_id User id.
	 *
	 * @return bool
	 */
	public static function isAutomatedUser( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return '' !== (string) get_user_meta( $user_id, self::AUTOMATED_META_KEY, true );
	}

	/**
	 * @param int $comment_id Comment id.
	 *
	 * @return void
	 */
	public static function markAutomatedComment( int $comment_id ): void {
		if ( $comment_id <= 0 ) {
			return;
		}

		add_comment_meta( $comment_id, self::AUTOMATED_META_KEY, '1', true );
	}

	/**
	 * @param int $comment_id Comment id.
	 *
	 * @return bool
	 */
	public static function isAutomatedComment( int $comment_id ): bool {
		if ( $comment_id <= 0 ) {
			return false;
		}

		return '' !== (string) get_comment_meta( $comment_id, self::AUTOMATED_META_KEY, true );
	}

	/**
	 * @param int $term_id Term id.
	 *
	 * @return void
	 */
	public static function markAutomatedTerm( int $term_id ): void {
		if ( $term_id <= 0 || ! function_exists( 'update_term_meta' ) ) {
			return;
		}

		update_term_meta( $term_id, self::AUTOMATED_META_KEY, '1' );
	}

	/**
	 * @param int $term_id Term id.
	 *
	 * @return bool
	 */
	public static function isAutomatedTerm( int $term_id ): bool {
		if ( $term_id <= 0 || ! function_exists( 'get_term_meta' ) ) {
			return false;
		}

		return '' !== (string) get_term_meta( $term_id, self::AUTOMATED_META_KEY, true );
	}

	/**
	 * True when a trigger payload refers to an entity this plugin created.
	 *
	 * @param array<string, mixed> $payload Trigger payload.
	 *
	 * @return bool
	 */
	public static function isAutomatedPayload( array $payload ): bool {
		$post_id = 0;

		foreach ( array( 'post_id', 'product_id', 'image_id', 'media_id', 'ID' ) as $key ) {
			if ( isset( $payload[ $key ] ) && (int) $payload[ $key ] > 0 ) {
				$post_id = (int) $payload[ $key ];
				break;
			}
		}

		if ( $post_id > 0 && self::isAutomatedPost( $post_id ) ) {
			return true;
		}

		$user_id = 0;

		foreach ( array( 'user_id', 'customer_id' ) as $key ) {
			if ( isset( $payload[ $key ] ) && (int) $payload[ $key ] > 0 ) {
				$user_id = (int) $payload[ $key ];
				break;
			}
		}

		if ( $user_id > 0 && self::isAutomatedUser( $user_id ) ) {
			return true;
		}

		$comment_id = isset( $payload['comment_id'] ) ? (int) $payload['comment_id'] : 0;

		if ( $comment_id > 0 && self::isAutomatedComment( $comment_id ) ) {
			return true;
		}

		$term_id = isset( $payload['term_id'] ) ? (int) $payload['term_id'] : 0;

		if ( $term_id > 0 && self::isAutomatedTerm( $term_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Loads the wp-admin includes needed by media, plugin, and upload
	 * helpers when running outside the admin (e.g. during a workflow run).
	 *
	 * @return void
	 */
	public static function ensureMediaIncludes(): void {
		if ( ! function_exists( 'wp_insert_attachment' ) || ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
	}
}
