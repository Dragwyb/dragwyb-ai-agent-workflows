<?php
/**
 * Elementor Pro form submission trigger.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Triggers;

use AIAWA\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when an Elementor Pro form is submitted
 * (`elementor_pro/forms/new_record`).
 *
 * Only registered when Elementor Pro is active (see BuiltInNodeTypes). Form
 * widgets live in Elementor Pro, not Elementor Free — sites without Pro
 * never see this node in the palette.
 *
 * Independently designed: listens to Elementor's documented public form
 * hook and builds a plain array payload (form name/id + field values). No
 * third-party plugin code is reused.
 *
 * Optional `form_id` config limits the trigger to one form widget; leave
 * empty to run for every Elementor Pro form on the site.
 */
class ElementorFormSubmittedTrigger implements TriggerInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'elementor_form_submitted_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Elementor Form Submitted', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when an Elementor Pro form is submitted.', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type'    => 'select',
				'label'   => __( 'Form (optional — leave empty for all forms)', 'ai-agent-workflow-automation' ),
				'default' => '',
				'options' => array(
					array(
						'value' => '',
						'label' => __( 'All forms', 'ai-agent-workflow-automation' ),
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$expected_form_id   = isset( $config['form_id'] ) ? trim( (string) $config['form_id'] ) : '';
		$expected_form_name = isset( $config['form_name'] ) ? trim( (string) $config['form_name'] ) : '';

		add_action(
			'elementor_pro/forms/new_record',
			static function ( $record, $handler = null ) use ( $on_fire, $config, $expected_form_id, $expected_form_name ): void {
				unset( $handler );

				$payload = self::buildPayload( $record );

				if ( null === $payload ) {
					return;
				}

				if ( ! self::payloadMatchesConfiguredForm( $payload, $expected_form_id, $expected_form_name ) ) {
					return;
				}

				$on_fire( $payload, $config );
			},
			10,
			2
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string               $expected_form_id
	 * @param string               $expected_form_name Legacy config key.
	 *
	 * @return bool
	 */
	private static function payloadMatchesConfiguredForm( array $payload, string $expected_form_id, string $expected_form_name ): bool {
		$payload_form_id   = trim( (string) ( $payload['form_id'] ?? '' ) );
		$payload_form_name = trim( (string) ( $payload['form_name'] ?? '' ) );

		if ( '' !== $expected_form_id ) {
			return $expected_form_id === $payload_form_id;
		}

		if ( '' !== $expected_form_name ) {
			return 0 === strcasecmp( $expected_form_name, $payload_form_name );
		}

		return true;
	}

	/**
	 * @param mixed $record Elementor form record object.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function buildPayload( $record ): ?array {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) || ! method_exists( $record, 'get' ) ) {
			return null;
		}

		$form_name    = trim( (string) $record->get_form_settings( 'form_name' ) );
		$form_id      = trim( (string) $record->get_form_settings( 'id' ) );
		$form_post_id = (string) $record->get_form_settings( 'form_post_id' );
		$raw_fields   = $record->get( 'fields' );

		$fields = array();

		if ( is_array( $raw_fields ) ) {
			foreach ( $raw_fields as $field_id => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$id    = is_string( $field_id ) || is_int( $field_id ) ? (string) $field_id : '';
				$value = isset( $field['value'] ) ? $field['value'] : '';

				if ( is_array( $value ) ) {
					$value = implode( ', ', array_map( 'strval', $value ) );
				} else {
					$value = (string) $value;
				}

				if ( '' !== $id ) {
					$fields[ $id ] = $value;
				}
			}
		}

		return array(
			'source'       => 'elementor',
			'event'        => 'form_submitted',
			'form_name'    => $form_name,
			'form_id'      => $form_id,
			'form_post_id' => $form_post_id,
			'fields'       => $fields,
		);
	}
}
