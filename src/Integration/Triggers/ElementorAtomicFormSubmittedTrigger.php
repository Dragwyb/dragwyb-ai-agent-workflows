<?php
/**
 * Elementor Pro atomic form submission trigger.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\Triggers;

use DragwybVisualAutomation\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when an Elementor Pro atomic form is submitted.
 *
 * Listens to Elementor Pro's existing AJAX action name (third-party hook),
 * verifies Elementor's request nonce first, then builds a sanitized payload.
 */
class ElementorAtomicFormSubmittedTrigger implements TriggerInterface {

	/**
	 * Elementor Pro's own AJAX action — we listen, we do not register this name.
	 */
	private const ELEMENTOR_AJAX_ACTION = 'elementor_pro_atomic_forms_send_form';

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'elementor_atomic_form_submitted_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Elementor Atomic Form Submitted', 'dragwyb-visual-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when an Elementor Pro atomic form is submitted.', 'dragwyb-visual-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type'    => 'select',
				'label'   => __( 'Form (optional — leave empty for all forms)', 'dragwyb-visual-automation' ),
				'default' => '',
				'options' => array(
					array(
						'value' => '',
						'label' => __( 'All forms', 'dragwyb-visual-automation' ),
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$expected_form_id = isset( $config['form_id'] ) ? trim( (string) $config['form_id'] ) : '';

		$handler = static function () use ( $on_fire, $config, $expected_form_id ): void {
			if ( ! self::isAtomicFormSubmissionRequest() ) {
				return;
			}

			if ( ! self::verifyElementorRequest() ) {
				return;
			}

			$payload = self::buildPayloadFromPost();

			if ( null === $payload ) {
				return;
			}

			if ( ! self::payloadMatchesConfiguredForm( $payload, $expected_form_id ) ) {
				return;
			}

			$on_fire( $payload, $config );
		};

		// Priority 20 runs after Elementor's own handler (default 10) has had a
		// chance to validate the request. We still verify nonce ourselves.
		add_action( 'wp_ajax_' . self::ELEMENTOR_AJAX_ACTION, $handler, 20 );
		add_action( 'wp_ajax_nopriv_' . self::ELEMENTOR_AJAX_ACTION, $handler, 20 );
	}

	/**
	 * @return bool
	 */
	private static function isAtomicFormSubmissionRequest(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in verifyElementorRequest().
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		return wp_doing_ajax() && self::ELEMENTOR_AJAX_ACTION === $action;
	}

	/**
	 * Confirms Elementor's form nonce before workflow execution.
	 *
	 * @return bool
	 */
	private static function verifyElementorRequest(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verifying here.
		$nonce = isset( $_POST['_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) )
			: ( isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '' );

		if ( '' === $nonce ) {
			return false;
		}

		$actions = array(
			'elementor_send_form',
			'elementor-pro-frontend',
			'elementor_pro_atomic_forms_send_form',
		);

		foreach ( $actions as $action ) {
			if ( false !== wp_verify_nonce( $nonce, $action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $key POST key.
	 *
	 * @return mixed
	 */
	private static function getPostValue( string $key ) {
		// Always sanitize locally — do not trust third-party helpers alone.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in verifyElementorRequest().
		if ( ! isset( $_POST[ $key ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below by type.
		$raw = wp_unslash( $_POST[ $key ] );

		if ( is_string( $raw ) ) {
			return sanitize_text_field( $raw );
		}

		if ( is_array( $raw ) ) {
			return self::sanitizeArray( $raw );
		}

		if ( is_numeric( $raw ) ) {
			return $raw;
		}

		return null;
	}

	/**
	 * @param array<mixed> $value Raw array.
	 *
	 * @return array<mixed>
	 */
	private static function sanitizeArray( array $value ): array {
		$clean = array();

		foreach ( $value as $k => $v ) {
			$key = is_string( $k ) ? sanitize_text_field( $k ) : $k;

			if ( is_array( $v ) ) {
				$clean[ $key ] = self::sanitizeArray( $v );
			} elseif ( is_string( $v ) ) {
				$clean[ $key ] = sanitize_text_field( $v );
			} elseif ( is_numeric( $v ) || is_bool( $v ) ) {
				$clean[ $key ] = $v;
			}
		}

		return $clean;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function buildPayloadFromPost(): ?array {
		$post_id     = absint( self::getPostValue( 'post_id' ) ?? 0 );
		$form_id     = sanitize_text_field( (string) ( self::getPostValue( 'form_id' ) ?? '' ) );
		$form_name   = sanitize_text_field( (string) ( self::getPostValue( 'form_name' ) ?? '' ) );
		$form_fields = self::getPostValue( 'form_fields' );

		if ( ! is_array( $form_fields ) || array() === $form_fields || '' === $form_id ) {
			return null;
		}

		$fields          = array();
		$fields_by_label = array();
		$field_metadata  = array();

		foreach ( $form_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id = sanitize_text_field( (string) ( $field['id'] ?? '' ) );

			if ( '' === $id ) {
				continue;
			}

			$value = $field['value'] ?? '';
			$type  = sanitize_text_field( (string) ( $field['type'] ?? 'text' ) );

			if ( is_array( $value ) ) {
				$sanitized     = array_map( 'sanitize_text_field', $value );
				$fields[ $id ] = implode( ', ', $sanitized );
			} elseif ( 'textarea' === $type ) {
				$fields[ $id ] = sanitize_textarea_field( (string) $value );
			} else {
				$fields[ $id ] = sanitize_text_field( (string) $value );
			}

			$label = sanitize_text_field( (string) ( $field['label'] ?? '' ) );

			if ( '' !== $label ) {
				$fields_by_label[ $label ] = $fields[ $id ];
			}

			$options = isset( $field['options'] ) && is_string( $field['options'] )
				? json_decode( $field['options'], true )
				: null;

			$field_metadata[ $id ] = array(
				'label'   => $label,
				'type'    => $type,
				'options' => is_array( $options ) ? $options : null,
			);
		}

		if ( array() === $fields ) {
			return null;
		}

		$referer_title = sanitize_text_field( (string) ( self::getPostValue( 'referer_title' ) ?? '' ) );
		$referrer      = esc_url_raw( (string) ( self::getPostValue( 'referrer' ) ?? '' ) );

		if ( '' === $form_name ) {
			$form_name = $form_id;
		}

		return array(
			'source'          => 'elementor-atomic',
			'event'           => 'atomic_form_submitted',
			'form_name'       => $form_name,
			'form_id'         => $form_id,
			'form_post_id'    => (string) $post_id,
			'fields'          => $fields,
			'fields_by_label' => $fields_by_label,
			'field_metadata'  => $field_metadata,
			'referer_title'   => $referer_title,
			'referrer'        => $referrer,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string               $expected_form_id
	 *
	 * @return bool
	 */
	private static function payloadMatchesConfiguredForm( array $payload, string $expected_form_id ): bool {
		if ( '' === $expected_form_id ) {
			return true;
		}

		$payload_form_id = trim( (string) ( $payload['form_id'] ?? '' ) );

		return $expected_form_id === $payload_form_id;
	}
}
