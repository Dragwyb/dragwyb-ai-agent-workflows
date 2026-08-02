<?php
/**
 * Contact Form 7 submission trigger.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\Triggers;

use DragwybAgentFlow\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when Contact Form 7 sends mail (`wpcf7_mail_sent`).
 * Registered only when Contact Form 7 is active.
 */
class ContactForm7SubmittedTrigger implements TriggerInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'contact_form7_submitted_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Contact Form 7 Submitted', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when a Contact Form 7 form is submitted.', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type'    => 'string',
				'label'   => __( 'Form ID (optional — leave empty for all forms)', 'dragwyb-agentflow' ),
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
			'wpcf7_mail_sent',
			static function ( $contact_form ) use ( $on_fire, $config, $expected_form_id ): void {
				$payload = self::buildPayload( $contact_form );

				if ( null === $payload ) {
					return;
				}

				if ( '' !== $expected_form_id && (string) $payload['form_id'] !== $expected_form_id ) {
					return;
				}

				$on_fire( $payload, $config );
			},
			10,
			1
		);
	}

	/**
	 * @param mixed $contact_form WPCF7_ContactForm instance.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function buildPayload( $contact_form ): ?array {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return null;
		}

		$form_id    = (int) $contact_form->id();
		$form_title = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';
		$fields     = array();

		if ( class_exists( '\WPCF7_Submission', false ) ) {
			$submission = \WPCF7_Submission::get_instance();

			if ( is_object( $submission ) && method_exists( $submission, 'get_posted_data' ) ) {
				$posted = $submission->get_posted_data();

				if ( is_array( $posted ) ) {
					foreach ( $posted as $key => $value ) {
						if ( ! is_string( $key ) || '' === $key ) {
							continue;
						}

						if ( is_array( $value ) ) {
							$value = implode( ', ', array_map( 'strval', $value ) );
						} else {
							$value = (string) $value;
						}

						$fields[ $key ] = $value;
					}
				}
			}
		}

		return array(
			'source'     => 'contact-form-7',
			'event'      => 'form_submitted',
			'form_id'    => $form_id,
			'form_title' => $form_title,
			'fields'     => $fields,
		);
	}
}
