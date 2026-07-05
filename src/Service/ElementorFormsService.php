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
 * Scans Elementor page data for form widgets.
 */
final class ElementorFormsService {

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
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	public function listForms(): array {
		if ( ! IntegrationTriggerCatalog::isElementorProActive() ) {
			return array(
				'options' => array(),
				'error' => __( 'Elementor Pro is not active.', 'workflow-automate' ),
			);
		}

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

			$this->collectFormsFromElements( $elements, $post_title, $forms_by_id );
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
			'error' => array() === $options
				? __( 'No Elementor forms were found on this site.', 'workflow-automate' )
				: null,
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
						return $elements;
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

		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param array<int|string, mixed>                                                         $elements
	 * @param string                                                                           $post_title
	 * @param array<string, array{form_name: string, label: string, pages: array<int, string>}> $forms_by_id
	 *
	 * @return void
	 */
	private function collectFormsFromElements( array $elements, string $post_title, array &$forms_by_id ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( 'form' === (string) ( $element['widgetType'] ?? '' ) ) {
				$form_id = trim( (string) ( $element['id'] ?? '' ) );

				if ( '' === $form_id ) {
					continue;
				}

				$settings  = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
				$form_name = trim( (string) ( $settings['form_name'] ?? '' ) );

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

			$children = $element['elements'] ?? null;

			if ( is_array( $children ) && array() !== $children ) {
				$this->collectFormsFromElements( $children, $post_title, $forms_by_id );
			}
		}
	}
}
