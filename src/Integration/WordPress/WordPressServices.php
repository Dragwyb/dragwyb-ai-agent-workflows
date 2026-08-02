<?php
/**
 * Implements every WordPress workflow action's business logic.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\WordPress;

use DragwybAgentFlow\Plugin\Integration\WordPress\Service\CommentWordPressService;
use DragwybAgentFlow\Plugin\Integration\WordPress\Service\PluginWordPressService;
use DragwybAgentFlow\Plugin\Integration\WordPress\Service\PostWordPressService;
use DragwybAgentFlow\Plugin\Integration\WordPress\Service\TaxonomyWordPressService;
use DragwybAgentFlow\Plugin\Integration\WordPress\Service\UserWordPressService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One method per WordPress action, each taking the action's config array
 * and returning `array{success: bool, error?: string, ...}`. Never throws
 * for expected failures (missing id, not found, WP_Error, ...).
 */
final class WordPressServices {

	private UserWordPressService $users;
	private PostWordPressService $posts;
	private CommentWordPressService $comments;
	private TaxonomyWordPressService $taxonomies;
	private PluginWordPressService $plugins;

	public function __construct(
		?UserWordPressService $users = null,
		?PostWordPressService $posts = null,
		?CommentWordPressService $comments = null,
		?TaxonomyWordPressService $taxonomies = null,
		?PluginWordPressService $plugins = null
	) {
		$this->users      = $users ?? new UserWordPressService();
		$this->posts      = $posts ?? new PostWordPressService();
		$this->comments   = $comments ?? new CommentWordPressService();
		$this->taxonomies = $taxonomies ?? new TaxonomyWordPressService();
		$this->plugins    = $plugins ?? new PluginWordPressService();
	}

	// -----------------------------------------------------------------
	// Users.
	// -----------------------------------------------------------------

	public function createUser( array $config ): array {
		return $this->users->createUser( $config );
	}

	public function updateUser( array $config ): array {
		return $this->users->updateUser( $config );
	}

	public function deleteUser( array $config ): array {
		return $this->users->deleteUser( $config );
	}

	public function getAllUsers( array $config ): array {
		return $this->users->getAllUsers( $config );
	}

	public function getAllUsersByRole( array $config ): array {
		return $this->users->getAllUsersByRole( $config );
	}

	public function getUserById( array $config ): array {
		return $this->users->getUserById( $config );
	}

	public function getUserByEmail( array $config ): array {
		return $this->users->getUserByEmail( $config );
	}

	public function getUserByField( array $config ): array {
		return $this->users->getUserByField( $config );
	}

	public function getUserMetadata( array $config ): array {
		return $this->users->getUserMetadata( $config );
	}

	public function getUserMetadataByMetaKey( array $config ): array {
		return $this->users->getUserMetadataByMetaKey( $config );
	}

	public function updateUserMetadata( array $config ): array {
		return $this->users->updateUserMetadata( $config );
	}

	public function createRole( array $config ): array {
		return $this->users->createRole( $config );
	}

	public function deleteRole( array $config ): array {
		return $this->users->deleteRole( $config );
	}

	public function manageUserRole( array $config, bool $remove = false, bool $update = false ): array {
		return $this->users->manageUserRole( $config, $remove, $update );
	}

	public function getAllRoles(): array {
		return $this->users->getAllRoles();
	}

	public function getAllCapabilities(): array {
		return $this->users->getAllCapabilities();
	}

	public function getRoleCapabilities( array $config ): array {
		return $this->users->getRoleCapabilities( $config );
	}

	public function manageRoleCapabilities( array $config, bool $remove = false ): array {
		return $this->users->manageRoleCapabilities( $config, $remove );
	}

	public function getUserCapabilities( array $config ): array {
		return $this->users->getUserCapabilities( $config );
	}

	public function manageUserCapabilities( array $config, bool $remove = false ): array {
		return $this->users->manageUserCapabilities( $config, $remove );
	}

	// -----------------------------------------------------------------
	// Posts & Post Types.
	// -----------------------------------------------------------------

	public function getAllPosts( array $config ): array {
		return $this->posts->getAllPosts( $config );
	}

	public function getPostById( array $config ): array {
		return $this->posts->getPostById( $config );
	}

	public function getPostsByPostType( array $config ): array {
		return $this->posts->getPostsByPostType( $config );
	}

	public function getPostsByMetadata( array $config ): array {
		return $this->posts->getPostsByMetadata( $config );
	}

	public function getPostMetadata( array $config ): array {
		return $this->posts->getPostMetadata( $config );
	}

	public function getPostMetadataByMetaKey( array $config ): array {
		return $this->posts->getPostMetadataByMetaKey( $config );
	}

	public function getPostPermalink( array $config ): array {
		return $this->posts->getPostPermalink( $config );
	}

	public function getPostContent( array $config ): array {
		return $this->posts->getPostContent( $config );
	}

	public function getPostExcerpt( array $config ): array {
		return $this->posts->getPostExcerpt( $config );
	}

	public function getPostStatus( array $config ): array {
		return $this->posts->getPostStatus( $config );
	}

	public function createNewPost( array $config ): array {
		return $this->posts->createNewPost( $config );
	}

	public function updateExistingPost( array $config ): array {
		return $this->posts->updateExistingPost( $config );
	}

	public function updatePostStatus( array $config ): array {
		return $this->posts->updatePostStatus( $config );
	}

	public function deleteExistingPost( array $config ): array {
		return $this->posts->deleteExistingPost( $config );
	}

	public function getAllPostTypes(): array {
		return $this->posts->getAllPostTypes();
	}

	public function getPostType( array $config ): array {
		return $this->posts->getPostType( $config );
	}

	public function registerPostType( array $config ): array {
		return $this->posts->registerPostType( $config );
	}

	public function unregisterPostType( array $config ): array {
		return $this->posts->unregisterPostType( $config );
	}

	public function addPostTypeFeatures( array $config ): array {
		return $this->posts->addPostTypeFeatures( $config );
	}

	// -----------------------------------------------------------------
	// Comments.
	// -----------------------------------------------------------------

	public function getAllPostComments(): array {
		return $this->comments->getAllPostComments();
	}

	public function getPostComments( array $config ): array {
		return $this->comments->getPostComments( $config );
	}

	public function getUserComments( array $config ): array {
		return $this->comments->getUserComments( $config );
	}

	public function getUserCommentsByEmail( array $config ): array {
		return $this->comments->getUserCommentsByEmail( $config );
	}

	public function getCommentMetadata( array $config ): array {
		return $this->comments->getCommentMetadata( $config );
	}

	public function getCommentMetadataByMetaKey( array $config ): array {
		return $this->comments->getCommentMetadataByMetaKey( $config );
	}

	public function createNewComment( array $config ): array {
		return $this->comments->createNewComment( $config );
	}

	public function replyToComment( array $config ): array {
		return $this->comments->replyToComment( $config );
	}

	public function deleteExistingComment( array $config ): array {
		return $this->comments->deleteExistingComment( $config );
	}

	// -----------------------------------------------------------------
	// Taxonomies & Media.
	// -----------------------------------------------------------------

	public function createTermByTax( array $config, string $taxonomy ): array {
		return $this->taxonomies->createTermByTax( $config, $taxonomy );
	}

	public function updateTermByTax( array $config, string $taxonomy ): array {
		return $this->taxonomies->updateTermByTax( $config, $taxonomy );
	}

	public function deleteTermByTax( array $config, string $taxonomy ): array {
		return $this->taxonomies->deleteTermByTax( $config, $taxonomy );
	}

	public function getAllTerms( array $config, string $forcedTaxonomy = '' ): array {
		return $this->taxonomies->getAllTerms( $config, $forcedTaxonomy );
	}

	public function getTermById( array $config, string $forcedTaxonomy = '' ): array {
		return $this->taxonomies->getTermById( $config, $forcedTaxonomy );
	}

	public function getTerm( array $config ): array {
		return $this->taxonomies->getTerm( $config );
	}

	public function getTermByField( array $config ): array {
		return $this->taxonomies->getTermByField( $config );
	}

	public function getTermByTaxonomy( array $config ): array {
		return $this->taxonomies->getTermByTaxonomy( $config );
	}

	public function createNewTerm( array $config ): array {
		return $this->taxonomies->createNewTerm( $config );
	}

	public function updateTerm( array $config ): array {
		return $this->taxonomies->updateTerm( $config );
	}

	public function deleteTerm( array $config ): array {
		return $this->taxonomies->deleteTerm( $config );
	}

	public function addTagsToPost( array $config ): array {
		return $this->taxonomies->addTagsToPost( $config );
	}

	public function removeTagsFromPost( array $config ): array {
		return $this->taxonomies->removeTagsFromPost( $config );
	}

	public function addCategoryToPost( array $config ): array {
		return $this->taxonomies->addCategoryToPost( $config );
	}

	public function getAllTaxonomies(): array {
		return $this->taxonomies->getAllTaxonomies();
	}

	public function getTaxonomy( array $config ): array {
		return $this->taxonomies->getTaxonomy( $config );
	}

	public function registerTaxonomy( array $config ): array {
		return $this->taxonomies->registerTaxonomy( $config );
	}

	public function unregisterTaxonomy( array $config ): array {
		return $this->taxonomies->unregisterTaxonomy( $config );
	}

	public function addTaxonomyToPost( array $config ): array {
		return $this->taxonomies->addTaxonomyToPost( $config );
	}

	public function removeTaxonomyFromPost( array $config ): array {
		return $this->taxonomies->removeTaxonomyFromPost( $config );
	}

	public function addNewImage( array $config ): array {
		return $this->taxonomies->addNewImage( $config );
	}

	public function deleteMedia( array $config ): array {
		return $this->taxonomies->deleteMedia( $config );
	}

	public function renameMedia( array $config ): array {
		return $this->taxonomies->renameMedia( $config );
	}

	public function getAllMedia( array $config ): array {
		return $this->taxonomies->getAllMedia( $config );
	}

	public function getMediaByTitle( array $config ): array {
		return $this->taxonomies->getMediaByTitle( $config );
	}

	public function getMediaById( array $config ): array {
		return $this->taxonomies->getMediaById( $config );
	}

	// -----------------------------------------------------------------
	// Plugins.
	// -----------------------------------------------------------------

	public function checkPluginActivationStatus( array $config ): array {
		return $this->plugins->checkPluginActivationStatus( $config );
	}

	public function activatePlugin( array $config ): array {
		return $this->plugins->activatePlugin( $config );
	}
}
