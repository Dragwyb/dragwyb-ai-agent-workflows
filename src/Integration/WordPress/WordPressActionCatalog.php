<?php
/**
 * Static catalog of every built-in WordPress workflow action.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\WordPress;

use DragwybVisualAutomation\Plugin\Integration\WordPress\Catalog\PluginActionCatalog;
use DragwybVisualAutomation\Plugin\Integration\WordPress\Catalog\PostActionCatalog;
use DragwybVisualAutomation\Plugin\Integration\WordPress\Catalog\TaxonomyActionCatalog;
use DragwybVisualAutomation\Plugin\Integration\WordPress\Catalog\UserActionCatalog;

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
			'user'             => __( 'User Management', 'dragwyb-visual-automation' ),
			'user_retrieval'   => __( 'User Retrieval', 'dragwyb-visual-automation' ),
			'user_metadata'    => __( 'User Metadata', 'dragwyb-visual-automation' ),
			'role'             => __( 'Role Management', 'dragwyb-visual-automation' ),
			'capabilities'     => __( 'Capabilities Management', 'dragwyb-visual-automation' ),
			'post'             => __( 'Post Management', 'dragwyb-visual-automation' ),
			'comment'          => __( 'Comment Management', 'dragwyb-visual-automation' ),
			'post_type'        => __( 'Post Type Management', 'dragwyb-visual-automation' ),
			'post_tag'         => __( 'Post Tag Management', 'dragwyb-visual-automation' ),
			'media'            => __( 'Media Management', 'dragwyb-visual-automation' ),
			'term'             => __( 'Term Management', 'dragwyb-visual-automation' ),
			'taxonomy'         => __( 'Taxonomy Management', 'dragwyb-visual-automation' ),
			'category'         => __( 'Category Management', 'dragwyb-visual-automation' ),
			'product_tag'      => __( 'Product Tag Management', 'dragwyb-visual-automation' ),
			'product_category' => __( 'Product Category Management', 'dragwyb-visual-automation' ),
			'product_type'     => __( 'Product Type Management', 'dragwyb-visual-automation' ),
			'plugin'           => __( 'Plugin Management', 'dragwyb-visual-automation' ),
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
