<?php
/**
 * Elementor Pro form submission trigger.
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
 * Optional `form_name` config limits the trigger to one form; leave empty
 * to run for every Elementor Pro form on the site.
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
		return __( 'Elementor Form Submitted', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when an Elementor Pro form is submitted.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_name' => array(
				'type' => 'string',
				'label' => __( 'Form name (optional — leave empty for all forms)', 'workflow-automate' ),
				'default' => '',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$expected_form_name = isset( $config['form_name'] ) ? trim( (string) $config['form_name'] ) : '';

		add_action(
			'elementor_pro/forms/new_record',
			static function ( $record, $handler = null ) use ( $on_fire, $config, $expected_form_name ): void {
				unset( $handler );

				$payload = self::buildPayload( $record );

				if ( null === $payload ) {
					return;
				}

				if ( '' !== $expected_form_name && $expected_form_name !== $payload['form_name'] ) {
					return;
				}

				$on_fire( $payload, $config );
			},
			10,
			2
		);
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

		$form_name     = (string) $record->get_form_settings( 'form_name' );
		$form_id       = (string) $record->get_form_settings( 'id' );
		$form_post_id  = (string) $record->get_form_settings( 'form_post_id' );
		$raw_fields    = $record->get( 'fields' );

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
			'source' => 'elementor',
			'event' => 'form_submitted',
			'form_name' => $form_name,
			'form_id' => $form_id,
			'form_post_id' => $form_post_id,
			'fields' => $fields,
		);
	}
}
