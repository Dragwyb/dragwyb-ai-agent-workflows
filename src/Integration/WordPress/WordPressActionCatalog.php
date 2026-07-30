<?php
/**
 * Static catalog of every built-in WordPress workflow action.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress;

use AIAWAB\Plugin\Integration\WordPress\Catalog\PluginActionCatalog;
use AIAWAB\Plugin\Integration\WordPress\Catalog\PostActionCatalog;
use AIAWAB\Plugin\Integration\WordPress\Catalog\TaxonomyActionCatalog;
use AIAWAB\Plugin\Integration\WordPress\Catalog\UserActionCatalog;

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
			'user'             => __( 'User Management', 'workflow-automate' ),
			'user_retrieval'   => __( 'User Retrieval', 'workflow-automate' ),
			'user_metadata'    => __( 'User Metadata', 'workflow-automate' ),
			'role'             => __( 'Role Management', 'workflow-automate' ),
			'capabilities'     => __( 'Capabilities Management', 'workflow-automate' ),
			'post'             => __( 'Post Management', 'workflow-automate' ),
			'comment'          => __( 'Comment Management', 'workflow-automate' ),
			'post_type'        => __( 'Post Type Management', 'workflow-automate' ),
			'post_tag'         => __( 'Post Tag Management', 'workflow-automate' ),
			'media'            => __( 'Media Management', 'workflow-automate' ),
			'term'             => __( 'Term Management', 'workflow-automate' ),
			'taxonomy'         => __( 'Taxonomy Management', 'workflow-automate' ),
			'category'         => __( 'Category Management', 'workflow-automate' ),
			'product_tag'      => __( 'Product Tag Management', 'workflow-automate' ),
			'product_category' => __( 'Product Category Management', 'workflow-automate' ),
			'product_type'     => __( 'Product Type Management', 'workflow-automate' ),
			'plugin'           => __( 'Plugin Management', 'workflow-automate' ),
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
