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
			'user'             => __( 'User Management', 'dragwyb-agentflow' ),
			'user_retrieval'   => __( 'User Retrieval', 'dragwyb-agentflow' ),
			'user_metadata'    => __( 'User Metadata', 'dragwyb-agentflow' ),
			'role'             => __( 'Role Management', 'dragwyb-agentflow' ),
			'capabilities'     => __( 'Capabilities Management', 'dragwyb-agentflow' ),
			'post'             => __( 'Post Management', 'dragwyb-agentflow' ),
			'comment'          => __( 'Comment Management', 'dragwyb-agentflow' ),
			'post_type'        => __( 'Post Type Management', 'dragwyb-agentflow' ),
			'post_tag'         => __( 'Post Tag Management', 'dragwyb-agentflow' ),
			'media'            => __( 'Media Management', 'dragwyb-agentflow' ),
			'term'             => __( 'Term Management', 'dragwyb-agentflow' ),
			'taxonomy'         => __( 'Taxonomy Management', 'dragwyb-agentflow' ),
			'category'         => __( 'Category Management', 'dragwyb-agentflow' ),
			'product_tag'      => __( 'Product Tag Management', 'dragwyb-agentflow' ),
			'product_category' => __( 'Product Category Management', 'dragwyb-agentflow' ),
			'product_type'     => __( 'Product Type Management', 'dragwyb-agentflow' ),
			'plugin'           => __( 'Plugin Management', 'dragwyb-agentflow' ),
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
