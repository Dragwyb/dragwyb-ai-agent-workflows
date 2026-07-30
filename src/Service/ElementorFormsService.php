<?php
/**
 * Lists Elementor Pro forms for builder config fields.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service;

use AIAWAB\Plugin\Integration\IntegrationTriggerCatalog;

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
	 * @return array<int, array{value: string, label: string, url?: string, pages?: array<int, array{label: string, url: string}>}>
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
	 * @return array<int, array{value: string, label: string, url?: string, pages?: array<int, array{label: string, url: string}>}>
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
				'error'   => __( 'Elementor Pro atomic forms are not available.', 'workflow-automate' ),
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
				'error'   => __( 'Elementor Pro is not active.', 'workflow-automate' ),
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
	 * Builds a sample trigger payload (field keys with empty values) for the
	 * variable picker — no Listen / form submit required.
	 *
	 * @param string $form_id Elementor form element id (empty = first form found).
	 * @param bool   $atomic  True for atomic e-form elements.
	 *
	 * @return array{success: bool, payload?: array<string, mixed>, error?: string}
	 */
	public function samplePayloadForForm( string $form_id = '', bool $atomic = false ): array {
		$form = $this->findForm( $form_id, $atomic );

		if ( null === $form ) {
			return array(
				'success' => false,
				'error'   => $atomic
					? __( 'No Elementor atomic form was found for the variable picker.', 'workflow-automate' )
					: __( 'No Elementor form was found for the variable picker. Select a form on the trigger, or create one in Elementor.', 'workflow-automate' ),
			);
		}

		$fields = array();

		foreach ( $form['field_ids'] as $field_id ) {
			$fields[ $field_id ] = '';
		}

		$source = $atomic ? 'elementor-atomic' : 'elementor';

		$payload = array(
			'source'       => $source,
			'event'        => 'form_submitted',
			'form_name'    => $form['form_name'],
			'form_id'      => $form['form_id'],
			'form_post_id' => (string) $form['post_id'],
			'fields'       => $fields,
		);

		if ( $atomic ) {
			$payload['fields_by_label'] = array();

			foreach ( $form['field_labels'] as $label => $field_id ) {
				$payload['fields_by_label'][ $label ] = '';
			}
		}

		return array(
			'success' => true,
			'payload' => $payload,
		);
	}

	/**
	 * @param string $form_id Preferred form id (empty = first match).
	 * @param bool   $atomic  Atomic form mode.
	 *
	 * @return array{form_id: string, form_name: string, post_id: int, field_ids: array<int, string>, field_labels: array<string, string>}|null
	 */
	private function findForm( string $form_id, bool $atomic ): ?array {
		$matches_element = $atomic
			? array( $this, 'isAtomicFormElement' )
			: static function ( array $element ): bool {
				return 'widget' === ( $element['elType'] ?? '' )
					&& 'form' === ( $element['widgetType'] ?? '' );
			};

		$resolve_form_name = $atomic
			? array( $this, 'resolveAtomicFormName' )
			: static function ( array $settings ): string {
				return trim( (string) ( $settings['form_name'] ?? '' ) );
			};

		$found = null;

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

			$elements = $this->readElementsForPost( $post_id );

			if ( null === $elements ) {
				continue;
			}

			$match = $this->findFormInElements(
				$elements,
				$post_id,
				$form_id,
				$matches_element,
				$resolve_form_name,
				$atomic
			);

			if ( null === $match ) {
				continue;
			}

			if ( '' !== $form_id ) {
				if ( $match['form_id'] === $form_id ) {
					return $match;
				}

				continue;
			}

			return $match;
		}

		return null;
	}

	/**
	 * @param array<int|string, mixed>               $elements
	 * @param int                                    $post_id
	 * @param string                                 $preferred_form_id
	 * @param callable(array<string, mixed>): bool   $matches_element
	 * @param callable(array<string, mixed>): string $resolve_form_name
	 * @param bool                                   $atomic
	 *
	 * @return array{form_id: string, form_name: string, post_id: int, field_ids: array<int, string>, field_labels: array<string, string>}|null
	 */
	private function findFormInElements(
		array $elements,
		int $post_id,
		string $preferred_form_id,
		callable $matches_element,
		callable $resolve_form_name,
		bool $atomic
	): ?array {
		$fallback = null;

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( $matches_element( $element ) ) {
				$element_id = trim( (string) ( $element['id'] ?? '' ) );

				if ( '' === $element_id ) {
					continue;
				}

				$settings  = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
				$form_name = $resolve_form_name( $settings );

				if ( '' === $form_name ) {
					$form_name = __( 'Untitled Form', 'workflow-automate' );
				}

				$parsed = $atomic
					? $this->extractAtomicFieldIds( $element )
					: $this->extractClassicFieldIds( $settings );

				$candidate = array(
					'form_id'      => $element_id,
					'form_name'    => $form_name,
					'post_id'      => $post_id,
					'field_ids'    => $parsed['ids'],
					'field_labels' => $parsed['labels'],
				);

				if ( '' !== $preferred_form_id && $element_id === $preferred_form_id ) {
					return $candidate;
				}

				if ( null === $fallback ) {
					$fallback = $candidate;
				}
			}

			$children = $this->elementChildrenForScan( $element );

			if ( array() !== $children ) {
				$nested = $this->findFormInElements(
					$children,
					$post_id,
					$preferred_form_id,
					$matches_element,
					$resolve_form_name,
					$atomic
				);

				if ( null !== $nested ) {
					if ( '' !== $preferred_form_id ) {
						return $nested;
					}

					if ( null === $fallback ) {
						$fallback = $nested;
					}
				}
			}
		}

		if ( '' !== $preferred_form_id ) {
			return null;
		}

		return $fallback;
	}

	/**
	 * @param array<string, mixed> $settings Classic form widget settings.
	 *
	 * @return array{ids: array<int, string>, labels: array<string, string>}
	 */
	private function extractClassicFieldIds( array $settings ): array {
		$ids    = array();
		$labels = array();
		$raw    = $settings['form_fields'] ?? array();

		if ( ! is_array( $raw ) ) {
			return array(
				'ids'    => $ids,
				'labels' => $labels,
			);
		}

		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = (string) ( $field['field_type'] ?? 'text' );

			if ( in_array( $type, array( 'html', 'step', 'honeypot', 'recaptcha', 'recaptcha_v3' ), true ) ) {
				continue;
			}

			$custom_id = trim( (string) ( $field['custom_id'] ?? '' ) );

			if ( '' === $custom_id ) {
				$custom_id = trim( (string) ( $field['_id'] ?? '' ) );
			}

			if ( '' === $custom_id ) {
				continue;
			}

			$ids[] = $custom_id;

			$label = trim( (string) ( $field['field_label'] ?? '' ) );

			if ( '' !== $label ) {
				$labels[ $label ] = $custom_id;
			}
		}

		return array(
			'ids'    => array_values( array_unique( $ids ) ),
			'labels' => $labels,
		);
	}

	/**
	 * @param array<string, mixed> $form_element Atomic form element.
	 *
	 * @return array{ids: array<int, string>, labels: array<string, string>}
	 */
	private function extractAtomicFieldIds( array $form_element ): array {
		$ids    = array();
		$labels = array();
		$stack  = $this->elementChildrenForScan( $form_element );

		while ( array() !== $stack ) {
			$element = array_shift( $stack );

			if ( ! is_array( $element ) ) {
				continue;
			}

			$children = $this->elementChildrenForScan( $element );

			if ( array() !== $children ) {
				foreach ( $children as $child ) {
					$stack[] = $child;
				}
			}

			$widget_type = (string) ( $element['widgetType'] ?? '' );
			$el_type     = (string) ( $element['elType'] ?? '' );

			if ( 'widget' !== $el_type && '' === $widget_type ) {
				continue;
			}

			if ( ! preg_match( '/field|input|textarea|select|checkbox|radio|email|tel|url|number|date/i', $widget_type . ' ' . $el_type ) ) {
				continue;
			}

			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			$field_id = $this->resolveSettingValue( $settings, 'custom_id' );

			if ( '' === $field_id ) {
				$field_id = $this->resolveSettingValue( $settings, 'field_name' );
			}

			if ( '' === $field_id ) {
				$field_id = trim( (string) ( $element['id'] ?? '' ) );
			}

			if ( '' === $field_id ) {
				continue;
			}

			$ids[] = $field_id;

			$label = $this->resolveSettingValue( $settings, 'label' );

			if ( '' === $label ) {
				$label = $this->resolveSettingValue( $settings, 'field_label' );
			}

			if ( '' !== $label ) {
				$labels[ $label ] = $field_id;
			}
		}

		return array(
			'ids'    => array_values( array_unique( $ids ) ),
			'labels' => $labels,
		);
	}

	/**
	 * @param callable(array<string, mixed>): bool   $matches_element
	 * @param callable(array<string, mixed>): string $resolve_form_name
	 *
	 * @return array{options: array<int, array{value: string, label: string, url?: string, pages?: array<int, array{label: string, url: string}>}>, error: string|null}
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

		/** @var array<string, array{form_name: string, label: string, pages: array<int, array{title: string, url: string, post_id: int}>}> $forms_by_id */
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

			$this->collectFormsFromElements(
				$elements,
				$post_id,
				$post_title,
				$forms_by_id,
				$matches_element,
				$resolve_form_name
			);
		}

		$options = array();

		foreach ( $forms_by_id as $form_id => $entry ) {
			$page_titles = array_map(
				static function ( array $page ): string {
					return (string) ( $page['title'] ?? '' );
				},
				$entry['pages']
			);
			$page_titles = array_values( array_filter( $page_titles ) );

			$label = $entry['form_name'];

			if ( count( $page_titles ) > 1 ) {
				$label = sprintf(
					/* translators: 1: form name, 2: comma-separated page titles */
					__( '%1$s (%2$s)', 'workflow-automate' ),
					$entry['form_name'],
					implode( ', ', $page_titles )
				);
			} elseif ( 1 === count( $page_titles ) ) {
				$label = sprintf(
					/* translators: 1: form name, 2: page title */
					__( '%1$s — %2$s', 'workflow-automate' ),
					$entry['form_name'],
					$page_titles[0]
				);
			}

			$page_links = array();

			foreach ( $entry['pages'] as $page ) {
				$url = (string) ( $page['url'] ?? '' );

				if ( '' === $url ) {
					continue;
				}

				$page_links[] = array(
					'label' => (string) ( $page['title'] ?? '' ),
					'url'   => $url,
				);
			}

			$option = array(
				'value' => $form_id,
				'label' => $label,
			);

			if ( array() !== $page_links ) {
				$option['url']   = $page_links[0]['url'];
				$option['pages'] = $page_links;
			}

			$options[] = $option;
		}

		usort(
			$options,
			static function ( array $left, array $right ): int {
				return strcasecmp( $left['label'], $right['label'] );
			}
		);

		return array(
			'options' => $options,
			'error'   => null,
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

		$settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
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
	 * @param array<int|string, mixed>                                                                                                   $elements
	 * @param int                                                                                                                        $post_id
	 * @param string                                                                                                                     $post_title
	 * @param array<string, array{form_name: string, label: string, pages: array<int, array{title: string, url: string, post_id: int}>}> $forms_by_id
	 * @param callable(array<string, mixed>): bool                                                                                       $matches_element
	 * @param callable(array<string, mixed>): string                                                                                     $resolve_form_name
	 *
	 * @return void
	 */
	private function collectFormsFromElements(
		array $elements,
		int $post_id,
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
						'label'     => $form_name,
						'pages'     => array(),
					);
				}

				$already_listed = false;

				foreach ( $forms_by_id[ $form_id ]['pages'] as $page ) {
					if ( (int) ( $page['post_id'] ?? 0 ) === $post_id ) {
						$already_listed = true;
						break;
					}
				}

				if ( ! $already_listed ) {
					$page_url                           = get_permalink( $post_id );
					$forms_by_id[ $form_id ]['pages'][] = array(
						'title'   => $post_title,
						'url'     => is_string( $page_url ) ? $page_url : '',
						'post_id' => $post_id,
					);
				}
			}

			$children = $this->elementChildrenForScan( $element );

			if ( array() !== $children ) {
				$this->collectFormsFromElements(
					$children,
					$post_id,
					$post_title,
					$forms_by_id,
					$matches_element,
					$resolve_form_name
				);
			}
		}
	}
}
