<?php
/**
 * Optional co-plugin triggers — catalog + availability checks.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration;

use DragwybAgentFlow\Plugin\Integration\Triggers\ContactForm7SubmittedTrigger;
use DragwybAgentFlow\Plugin\Integration\Triggers\ElementorAtomicFormSubmittedTrigger;
use DragwybAgentFlow\Plugin\Integration\Triggers\ElementorFormSubmittedTrigger;
use DragwybAgentFlow\Plugin\Integration\Triggers\WooCommerceCatalogTrigger;
use DragwybAgentFlow\Plugin\Integration\Triggers\WpFormsSubmittedTrigger;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source for optional integration triggers. Used by BuiltInNodeTypes
 * (register when active) and NodeTypesController (expose unavailable entries
 * to the builder with `available: false`).
 */
class IntegrationTriggerCatalog {

	/**
	 * @return array<int, array{
	 *     slug: string,
	 *     app: string,
	 *     requires_plugin: string,
	 *     class: class-string,
	 *     active: bool
	 * }>
	 */
	public static function definitions(): array {
		$entries = array(
			array(
				'slug'            => 'elementor_form_submitted_trigger',
				'app'             => 'elementor',
				'requires_plugin' => 'Elementor Pro',
				'class'           => ElementorFormSubmittedTrigger::class,
				'active'          => self::isElementorProActive(),
			),
			array(
				'slug'            => 'elementor_atomic_form_submitted_trigger',
				'app'             => 'elementor',
				'requires_plugin' => 'Elementor Pro',
				'class'           => ElementorAtomicFormSubmittedTrigger::class,
				'active'          => self::isElementorAtomicFormsActive(),
			),
			array(
				'slug'            => 'contact_form7_submitted_trigger',
				'app'             => 'contact-form-7',
				'requires_plugin' => 'Contact Form 7',
				'class'           => ContactForm7SubmittedTrigger::class,
				'active'          => self::isContactForm7Active(),
			),
			array(
				'slug'            => 'wpforms_submitted_trigger',
				'app'             => 'wpforms',
				'requires_plugin' => 'WPForms',
				'class'           => WpFormsSubmittedTrigger::class,
				'active'          => self::isWpFormsActive(),
			),
		);

		foreach ( WooCommerceTriggerCatalog::definitions() as $wc_definition ) {
			$entries[] = array(
				'slug'            => (string) $wc_definition['slug'],
				'app'             => 'woocommerce',
				'requires_plugin' => 'WooCommerce',
				'class'           => WooCommerceCatalogTrigger::class,
				'definition'      => $wc_definition,
				'active'          => self::isWooCommerceActive(),
			);
		}

		return array_map(
			static function ( array $entry ): array {
				$entry['requires_plugin'] = $entry['requires_plugin'];

				return $entry;
			},
			$entries
		);
	}

	/**
	 * @return bool
	 */
	public static function isWooCommerceActive(): bool {
		return class_exists( '\WooCommerce', false ) && function_exists( 'WC' );
	}

	/**
	 * @return bool
	 */
	public static function isElementorProActive(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin', false );
	}

	/**
	 * @return bool
	 */
	public static function isElementorAtomicFormsActive(): bool {
		return self::isElementorProActive()
			&& class_exists( '\ElementorPro\Modules\AtomicForm\Atomic_Form_Controller', false );
	}

	/**
	 * @return bool
	 */
	public static function isContactForm7Active(): bool {
		return defined( 'WPCF7_VERSION' ) || class_exists( '\WPCF7_ContactForm', false );
	}

	/**
	 * @return bool
	 */
	public static function isWpFormsActive(): bool {
		return function_exists( 'wpforms' ) || defined( 'WPFORMS_VERSION' );
	}
}
