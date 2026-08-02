<?php
/**
 * Static catalog of every built-in WordPress workflow action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\WordPress;

use AIAWA\Plugin\Integration\WordPress\Catalog\PluginActionCatalog;
use AIAWA\Plugin\Integration\WordPress\Catalog\PostActionCatalog;
use AIAWA\Plugin\Integration\WordPress\Catalog\TaxonomyActionCatalog;
use AIAWA\Plugin\Integration\WordPress\Catalog\UserActionCatalog;

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
	public static function field( string $type, string $label, array $extra = array() ): array {
		return array_merge(
			array(
				'type'  => $type,
				'label' => $label,
			),
			$extra
		);
	}

	/**
	 * @return array{value: string, label: string}
	 */
	public static function option( string $value, string $label ): array {
		return array(
			'value' => $value,
			'label' => $label,
		);
	}

	/**
	 * @return array<int, array{slug: string, label: string, description: string, group: string, group_label: string, method: string, method_args: array<int, mixed>, config_schema: array<string, mixed>}>
	 */
	public static function definitions(): array {
		$groups = array(
			'user'             => __( 'User Management', 'ai-agent-workflow-automation' ),
			'user_retrieval'   => __( 'User Retrieval', 'ai-agent-workflow-automation' ),
			'user_metadata'    => __( 'User Metadata', 'ai-agent-workflow-automation' ),
			'role'             => __( 'Role Management', 'ai-agent-workflow-automation' ),
			'capabilities'     => __( 'Capabilities Management', 'ai-agent-workflow-automation' ),
			'post'             => __( 'Post Management', 'ai-agent-workflow-automation' ),
			'comment'          => __( 'Comment Management', 'ai-agent-workflow-automation' ),
			'post_type'        => __( 'Post Type Management', 'ai-agent-workflow-automation' ),
			'post_tag'         => __( 'Post Tag Management', 'ai-agent-workflow-automation' ),
			'media'            => __( 'Media Management', 'ai-agent-workflow-automation' ),
			'term'             => __( 'Term Management', 'ai-agent-workflow-automation' ),
			'taxonomy'         => __( 'Taxonomy Management', 'ai-agent-workflow-automation' ),
			'category'         => __( 'Category Management', 'ai-agent-workflow-automation' ),
			'product_tag'      => __( 'Product Tag Management', 'ai-agent-workflow-automation' ),
			'product_category' => __( 'Product Category Management', 'ai-agent-workflow-automation' ),
			'product_type'     => __( 'Product Type Management', 'ai-agent-workflow-automation' ),
			'plugin'           => __( 'Plugin Management', 'ai-agent-workflow-automation' ),
		);

		$field_fn  = array( self::class, 'field' );
		$option_fn = array( self::class, 'option' );

		return array_merge(
			UserActionCatalog::definitions( $field_fn, $option_fn, $groups ),
			PostActionCatalog::definitions( $field_fn, $option_fn, $groups ),
			TaxonomyActionCatalog::definitions( $field_fn, $option_fn, $groups ),
			PluginActionCatalog::definitions( $field_fn, $option_fn, $groups )
		);
	}
}
