<?php
/**
 * Elementor Pro atomic form submission trigger.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Triggers;

use WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when an Elementor Pro atomic form is submitted via the
 * `elementor_pro_atomic_forms_send_form` AJAX action.
 *
 * Hooks at priority 1 so field data is captured before Elementor's own
 * handler runs at the default priority.
 *
 * Optional `form_id` config limits the trigger to one form widget; leave
 * empty to run for every atomic form on the site.
 */
class ElementorAtomicFormSubmittedTrigger implements TriggerInterface {

	private const AJAX_ACTION = 'elementor_pro_atomic_forms_send_form';

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
		return __( 'Elementor Atomic Form Submitted', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when an Elementor Pro atomic form is submitted.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type' => 'select',
				'label' => __( 'Form (optional — leave empty for all forms)', 'workflow-automate' ),
				'default' => '',
				'options' => array(
					array(
						'value' => '',
						'label' => __( 'All forms', 'workflow-automate' ),
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

			$payload = self::buildPayloadFromPost();

			if ( null === $payload ) {
				return;
			}

			if ( ! self::payloadMatchesConfiguredForm( $payload, $expected_form_id ) ) {
				return;
			}

			$on_fire( $payload, $config );
		};

		add_action( 'wp_ajax_' . self::AJAX_ACTION, $handler, 1 );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, $handler, 1 );
	}

	/**
	 * @return bool
	 */
	private static function isAtomicFormSubmissionRequest(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only; Elementor validates the nonce later.
		return wp_doing_ajax()
			&& self::AJAX_ACTION === (string) self::getPostValue( 'action' );
	}

	/**
	 * @param string $key POST key.
	 *
	 * @return mixed
	 */
	private static function getPostValue( string $key ) {
		if ( class_exists( '\Elementor\Utils', false ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return \Elementor\Utils::get_super_global_value( $_POST, $key );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $_POST[ $key ] ?? null;
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

		$fields           = array();
		$fields_by_label  = array();
		$field_metadata   = array();

		foreach ( $form_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id = sanitize_text_field( $field['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$value = $field['value'] ?? '';
			$type  = sanitize_text_field( $field['type'] ?? 'text' );

			if ( is_array( $value ) ) {
				$sanitized = array_map( 'sanitize_text_field', $value );
				$fields[ $id ] = implode( ', ', $sanitized );
			} elseif ( 'textarea' === $type ) {
				$fields[ $id ] = sanitize_textarea_field( (string) $value );
			} else {
				$fields[ $id ] = sanitize_text_field( (string) $value );
			}

			$label = sanitize_text_field( $field['label'] ?? '' );

			if ( '' !== $label ) {
				$fields_by_label[ $label ] = $fields[ $id ];
			}

			$options = isset( $field['options'] ) && is_string( $field['options'] )
				? json_decode( $field['options'], true )
				: null;

			$field_metadata[ $id ] = array(
				'label' => $label,
				'type' => $type,
				'options' => is_array( $options ) ? $options : null,
			);
		}

		if ( array() === $fields ) {
			return null;
		}

		$referer_title = self::getPostValue( 'referer_title' ) ?? '';
		$referer_title = is_string( $referer_title ) ? sanitize_text_field( wp_unslash( $referer_title ) ) : '';

		$referrer = self::getPostValue( 'referrer' ) ?? '';
		$referrer = is_string( $referrer ) ? esc_url_raw( wp_unslash( $referrer ) ) : '';

		if ( '' === $form_name ) {
			$form_name = $form_id;
		}

		return array(
			'source' => 'elementor-atomic',
			'event' => 'atomic_form_submitted',
			'form_name' => $form_name,
			'form_id' => $form_id,
			'form_post_id' => (string) $post_id,
			'fields' => $fields,
			'fields_by_label' => $fields_by_label,
			'field_metadata' => $field_metadata,
			'referer_title' => $referer_title,
			'referrer' => $referrer,
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
