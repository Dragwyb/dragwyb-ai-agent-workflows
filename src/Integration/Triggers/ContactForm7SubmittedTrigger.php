<?php
/**
 * Contact Form 7 submission trigger.
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
 * Starts a workflow when Contact Form 7 successfully sends mail
 * (`wpcf7_mail_sent`). Registered only when CF7 is active.
 */
class ContactForm7SubmittedTrigger implements TriggerInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'contact_form_7_submitted_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Contact Form 7 Submitted', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when a Contact Form 7 form is submitted successfully.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'form_id' => array(
				'type' => 'string',
				'label' => __( 'Form ID (optional — leave empty for all forms)', 'workflow-automate' ),
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
	 * @param mixed $contact_form CF7 form object.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function buildPayload( $contact_form ): ?array {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return null;
		}

		$form_id = (int) $contact_form->id();
		$title   = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';

		$fields = array();

		if ( class_exists( '\WPCF7_Submission', false ) ) {
			$submission = \WPCF7_Submission::get_instance();

			if ( is_object( $submission ) && method_exists( $submission, 'get_posted_data' ) ) {
				$posted = $submission->get_posted_data();

				if ( is_array( $posted ) ) {
					foreach ( $posted as $key => $value ) {
						if ( ! is_string( $key ) && ! is_int( $key ) ) {
							continue;
						}

						// CF7 internal keys.
						if ( is_string( $key ) && 0 === strpos( $key, '_wpcf7' ) ) {
							continue;
						}

						if ( is_array( $value ) ) {
							$value = implode( ', ', array_map( 'strval', $value ) );
						}

						$fields[ (string) $key ] = (string) $value;
					}
				}
			}
		}

		return array(
			'source' => 'contact_form_7',
			'event' => 'form_submitted',
			'form_id' => $form_id,
			'form_title' => $title,
			'fields' => $fields,
		);
	}
}
