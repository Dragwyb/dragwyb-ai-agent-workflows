<?php
/**
 * WPForms submission trigger.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\Triggers;

use AIAWAB\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when a WPForms entry is processed
 * (`wpforms_process_complete`). Registered only when WPForms is active.
 */
class WpFormsSubmittedTrigger implements TriggerInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'wpforms_submitted_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'WPForms Submitted', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when a WPForms form is submitted.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type'    => 'string',
				'label'   => __( 'Form ID (optional — leave empty for all forms)', 'workflow-automate' ),
				'default' => '',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$expected_form_id = isset( $config['form_id'] ) ? trim( (string) $config['form_id'] ) : '';

		add_action(
			'wpforms_process_complete',
			static function ( $fields, $entry, $form_data, $entry_id ) use ( $on_fire, $config, $expected_form_id ): void {
				$payload = self::buildPayload( $fields, $form_data, $entry_id );

				if ( null === $payload ) {
					return;
				}

				if ( '' !== $expected_form_id && (string) $payload['form_id'] !== $expected_form_id ) {
					return;
				}

				$on_fire( $payload, $config );
			},
			10,
			4
		);
	}

	/**
	 * @param mixed $fields    WPForms field values.
	 * @param mixed $form_data Form settings array.
	 * @param mixed $entry_id  Entry id.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function buildPayload( $fields, $form_data, $entry_id ): ?array {
		$form_id    = 0;
		$form_title = '';

		if ( is_array( $form_data ) ) {
			$form_id    = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
			$form_title = isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '';
		}

		$field_map       = array();
		$fields_by_label = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$id    = isset( $field['id'] ) ? (string) $field['id'] : '';
				$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
				$value = isset( $field['value'] ) ? $field['value'] : '';

				if ( is_array( $value ) ) {
					$value = implode( ', ', array_map( 'strval', $value ) );
				} else {
					$value = (string) $value;
				}

				if ( '' !== $id ) {
					$field_map[ $id ] = $value;
				}

				if ( '' !== $name ) {
					$fields_by_label[ $name ] = $value;
				}
			}
		}

		return array(
			'source'          => 'wpforms',
			'event'           => 'form_submitted',
			'form_id'         => $form_id,
			'form_title'      => $form_title,
			'entry_id'        => is_scalar( $entry_id ) ? (int) $entry_id : 0,
			'fields'          => $field_map,
			'fields_by_label' => $fields_by_label,
		);
	}
}
