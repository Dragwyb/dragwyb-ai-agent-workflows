<?php
/**
 * Lists Elementor Pro forms for builder config fields.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Integration\IntegrationTriggerCatalog;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans Elementor page data for classic and atomic form widgets.
 */
final class ElementorFormsService {

	private const ATOMIC_FORM_EL_TYPE = 'e-form';

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	public function formSelectOptions(): array {
		$result  = $this->listForms();
		$options = array(
			array(
				'value' => '',
				'label' => __( 'All forms', 'workflow-automate' ),
			),
		);

		foreach ( $result['options'] as $option ) {
			$options[] = $option;
		}

		return $options;
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	public function atomicFormSelectOptions(): array {
		$result  = $this->listAtomicForms();
		$options = array(
			array(
				'value' => '',
				'label' => __( 'All forms', 'workflow-automate' ),
			),
		);

		foreach ( $result['options'] as $option ) {
			$options[] = $option;
		}

		return $options;
	}

	/**
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	public function listAtomicForms(): array {
		if ( ! IntegrationTriggerCatalog::isElementorAtomicFormsActive() ) {
			return array(
				'options' => array(),
				'error' => __( 'Elementor Pro atomic forms are not available.', 'workflow-automate' ),
			);
		}

		$result = $this->scanForms(
			array( $this, 'isAtomicFormElement' ),
			array( $this, 'resolveAtomicFormName' )
		);

		if ( array() === $result['options'] ) {
			$result['error'] = __( 'No Elementor atomic forms were found on this site.', 'workflow-automate' );
		}

		return $result;
	}

	/**
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	public function listForms(): array {
		if ( ! IntegrationTriggerCatalog::isElementorProActive() ) {
			return array(
				'options' => array(),
				'error' => __( 'Elementor Pro is not active.', 'workflow-automate' ),
			);
		}

		$result = $this->scanForms(
			static function ( array $element ): bool {
				return 'widget' === ( $element['elType'] ?? '' )
					&& 'form' === ( $element['widgetType'] ?? '' );
			},
			static function ( array $settings ): string {
				return trim( (string) ( $settings['form_name'] ?? '' ) );
			}
		);

		if ( array() === $result['options'] ) {
			$result['error'] = __( 'No Elementor forms were found on this site.', 'workflow-automate' );
		}

		return $result;
	}

	/**
	 * @param callable(array<string, mixed>): bool $matches_element
	 * @param callable(array<string, mixed>): string $resolve_form_name
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function scanForms( callable $matches_element, callable $resolve_form_name ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' AND meta_value <> %s",
				'_elementor_data',
				'[]'
			)
		);

		if ( ! is_array( $post_ids ) ) {
			$post_ids = array();
		}

		/** @var array<string, array{form_name: string, label: string, pages: array<int, string>}> $forms_by_id */
		$forms_by_id = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( $post_id <= 0 ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
				continue;
			}

			$post_title = get_the_title( $post_id );
			$post_title = is_string( $post_title ) ? trim( $post_title ) : '';

			$elements = $this->readElementsForPost( $post_id );

			if ( null === $elements ) {
				continue;
			}

			$this->collectFormsFromElements( $elements, $post_title, $forms_by_id, $matches_element, $resolve_form_name );
		}

		$options = array();

		foreach ( $forms_by_id as $form_id => $entry ) {
			$label = $entry['form_name'];

			if ( count( $entry['pages'] ) > 1 ) {
				$label = sprintf(
					/* translators: 1: form name, 2: comma-separated page titles */
					__( '%1$s (%2$s)', 'workflow-automate' ),
					$entry['form_name'],
					implode( ', ', $entry['pages'] )
				);
			} elseif ( 1 === count( $entry['pages'] ) && '' !== $entry['pages'][0] ) {
				$label = sprintf(
					/* translators: 1: form name, 2: page title */
					__( '%1$s — %2$s', 'workflow-automate' ),
					$entry['form_name'],
					$entry['pages'][0]
				);
			}

			$options[] = array(
				'value' => $form_id,
				'label' => $label,
			);
		}

		usort(
			$options,
			static function ( array $left, array $right ): int {
				return strcasecmp( $left['label'], $right['label'] );
			}
		);

		return array(
			'options' => $options,
			'error' => null,
		);
	}

	/**
	 * @param int $post_id Post id.
	 *
	 * @return array<int|string, mixed>|null
	 */
	private function readElementsForPost( int $post_id ): ?array {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;

			if ( isset( $plugin->documents ) && is_object( $plugin->documents ) && method_exists( $plugin->documents, 'get' ) ) {
				$document = $plugin->documents->get( $post_id, false );

				if ( is_object( $document ) && method_exists( $document, 'get_elements_data' ) ) {
					$elements = $document->get_elements_data();

					if ( is_array( $elements ) ) {
						return $this->normalizeElementsTree( $elements );
					}
				}
			}
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! is_string( $raw ) || '' === $raw || '[]' === $raw ) {
			return null;
		}

		$data = json_decode( wp_unslash( $raw ), true );

		if ( ! is_array( $data ) ) {
			$data = json_decode( $raw, true );
		}

		return $this->normalizeElementsTree( is_array( $data ) ? $data : null );
	}

	/**
	 * @param array<int|string, mixed>|null $data Raw or document-wrapped element tree.
	 *
	 * @return array<int|string, mixed>|null
	 */
	private function normalizeElementsTree( ?array $data ): ?array {
		if ( null === $data ) {
			return null;
		}

		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			return $data['content'];
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $element Elementor element data.
	 *
	 * @return array<int|string, mixed>
	 */
	private function elementChildrenForScan( array $element ): array {
		$children = $element['elements'] ?? array();

		if ( ! is_array( $children ) ) {
			$children = array();
		}

		$component_children = $this->componentInnerElements( $element );

		if ( array() !== $component_children ) {
			$children = array_merge( $children, $component_children );
		}

		return $children;
	}

	/**
	 * @param array<string, mixed> $element Elementor element data.
	 *
	 * @return array<int|string, mixed>
	 */
	private function componentInnerElements( array $element ): array {
		if ( ! class_exists( '\Elementor\Modules\Components\Repository\Components_Repository', false ) ) {
			return array();
		}

		$el_type = (string) ( $element['elType'] ?? '' );

		if ( 'e-component' !== $el_type && 'component' !== $el_type ) {
			return array();
		}

		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$component_id = 0;

		if ( isset( $settings['component_instance']['value']['component_id']['value'] ) ) {
			$component_id = (int) $settings['component_instance']['value']['component_id']['value'];
		}

		if ( $component_id <= 0 ) {
			$component_id = (int) $this->resolveSettingValue( $settings, 'component_id' );
		}

		if ( $component_id <= 0 ) {
			return array();
		}

		$repository = new \Elementor\Modules\Components\Repository\Components_Repository();
		$component  = $repository->get( $component_id );

		if ( ! is_object( $component ) || ! method_exists( $component, 'get_elements_data' ) ) {
			return array();
		}

		$elements = $component->get_elements_data();

		if ( ! is_array( $elements ) ) {
			return array();
		}

		if ( class_exists( '\Elementor\Modules\Components\Utils\Format_Component_Elements_Id', false ) ) {
			return \Elementor\Modules\Components\Utils\Format_Component_Elements_Id::format(
				$elements,
				array( (string) ( $element['id'] ?? '' ) )
			);
		}

		return $elements;
	}

	/**
	 * Atomic forms are layout elements (`elType: e-form`), not classic widgets.
	 *
	 * @param array<string, mixed> $element Elementor element data.
	 *
	 * @return bool
	 */
	private function isAtomicFormElement( array $element ): bool {
		$el_type     = (string) ( $element['elType'] ?? '' );
		$widget_type = (string) ( $element['widgetType'] ?? '' );

		return self::ATOMIC_FORM_EL_TYPE === $el_type
			|| self::ATOMIC_FORM_EL_TYPE === $widget_type;
	}

	/**
	 * @param array<string, mixed> $settings Element settings.
	 *
	 * @return string
	 */
	private function resolveAtomicFormName( array $settings ): string {
		foreach ( array( 'form-name', 'form_name', 'name', '_cssid' ) as $key ) {
			$value = $this->resolveSettingValue( $settings, $key );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $settings Element settings.
	 * @param string               $key      Setting key.
	 *
	 * @return string
	 */
	private function resolveSettingValue( array $settings, string $key ): string {
		if ( ! array_key_exists( $key, $settings ) ) {
			return '';
		}

		$value = $settings[ $key ];

		if ( is_string( $value ) || is_numeric( $value ) ) {
			return trim( (string) $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		if ( ! empty( $value['disabled'] ) ) {
			return '';
		}

		if ( isset( $value['value'] ) && ( is_string( $value['value'] ) || is_numeric( $value['value'] ) ) ) {
			return trim( (string) $value['value'] );
		}

		return '';
	}

	/**
	 * @param array<int|string, mixed>                                                         $elements
	 * @param string                                                                           $post_title
	 * @param array<string, array{form_name: string, label: string, pages: array<int, string>}> $forms_by_id
	 * @param callable(array<string, mixed>): bool                                             $matches_element
	 * @param callable(array<string, mixed>): string                                           $resolve_form_name
	 *
	 * @return void
	 */
	private function collectFormsFromElements(
		array $elements,
		string $post_title,
		array &$forms_by_id,
		callable $matches_element,
		callable $resolve_form_name
	): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( $matches_element( $element ) ) {
				$form_id = trim( (string) ( $element['id'] ?? '' ) );

				if ( '' === $form_id ) {
					continue;
				}

				$settings  = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
				$form_name = $resolve_form_name( $settings );

				if ( '' === $form_name ) {
					$form_name = __( 'Untitled Form', 'workflow-automate' );
				}

				if ( ! isset( $forms_by_id[ $form_id ] ) ) {
					$forms_by_id[ $form_id ] = array(
						'form_name' => $form_name,
						'label' => $form_name,
						'pages' => array(),
					);
				}

				if ( '' !== $post_title && ! in_array( $post_title, $forms_by_id[ $form_id ]['pages'], true ) ) {
					$forms_by_id[ $form_id ]['pages'][] = $post_title;
				}
			}

			$children = $this->elementChildrenForScan( $element );

			if ( array() !== $children ) {
				$this->collectFormsFromElements( $children, $post_title, $forms_by_id, $matches_element, $resolve_form_name );
			}
		}
	}
}
