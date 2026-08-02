<?php
/**
 * Built-in "Send Email" action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use WP_Error;
use AIAWA\Plugin\Domain\Contracts\ActionInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends an email via WordPress core's own `wp_mail()`.
 *
 * Deliberately delegates to `wp_mail()` rather than talking to a
 * transactional-email provider's API directly: `wp_mail()` already sends
 * through whatever mail transport the site has configured (PHP `mail()`,
 * or any SMTP/API-based mailer plugin already active) — see
 * `docs/integrations.md`. This means this action stores and manages *no*
 * credentials of its own (unlike HttpRequestAction's optional connection),
 * which is itself worth documenting explicitly per
 * CURSOR_INSTRUCTIONS.md's "how credentials for each are stored and used"
 * requirement: the answer here is "not applicable."
 */
class SendEmailAction implements ActionInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'send_email_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Send Email', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends an email using the site\'s configured mail delivery method.', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'to'      => array(
				'type'     => 'string',
				'label'    => __( 'To (comma-separated for multiple recipients)', 'dragwyb-agentflow' ),
				'required' => true,
			),
			'subject' => array(
				'type'     => 'string',
				'label'    => __( 'Subject', 'dragwyb-agentflow' ),
				'required' => true,
			),
			'message' => array(
				'type'     => 'string',
				'label'    => __( 'Message', 'dragwyb-agentflow' ),
				'required' => true,
			),
			'headers' => array(
				'type'    => 'object',
				'label'   => __( 'Additional headers (e.g. From, Reply-To, Content-Type)', 'dragwyb-agentflow' ),
				'default' => array(),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		$recipients = $this->parseRecipients( isset( $config['to'] ) ? (string) $config['to'] : '' );

		if ( array() === $recipients ) {
			return array(
				'success' => false,
				'error'   => __( 'No valid recipient address configured.', 'dragwyb-agentflow' ),
			);
		}

		$subject = isset( $config['subject'] ) ? trim( (string) $config['subject'] ) : '';
		$message = isset( $config['message'] ) ? (string) $config['message'] : '';
		$headers = $this->buildHeaders( isset( $config['headers'] ) && is_array( $config['headers'] ) ? $config['headers'] : array() );

		// wp_mail() only ever returns true/false; the actual reason for a
		// failure (an invalid header, PHPMailer throwing, etc.) is only
		// available via this action, fired by wp-includes/pluggable.php
		// before it returns false. Capturing it here is what turns "it
		// failed" into a message an operator can actually act on.
		$captured_error = null;
		$capture        = static function ( $wp_error ) use ( &$captured_error ): void {
			$captured_error = $wp_error;
		};

		add_action( 'wp_mail_failed', $capture );
		$sent = wp_mail( $recipients, $subject, $message, $headers );
		remove_action( 'wp_mail_failed', $capture );

		if ( ! $sent ) {
			return array(
				'success' => false,
				'error'   => $captured_error instanceof WP_Error
					? $captured_error->get_error_message()
					: __( 'wp_mail() reported failure for an unknown reason.', 'dragwyb-agentflow' ),
			);
		}

		return array(
			'success'    => true,
			'recipients' => $recipients,
		);
	}

	/**
	 * Splits a comma-separated recipient string and drops anything that
	 * isn't a valid email address, rather than passing a partially-invalid
	 * string straight to `wp_mail()` and letting it silently drop entries.
	 *
	 * @param string $raw Raw "to" field value.
	 *
	 * @return string[] Valid, deduplicated recipient addresses.
	 */
	private function parseRecipients( string $raw ): array {
		$candidates = array_map( 'trim', explode( ',', $raw ) );
		$valid      = array();

		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}

			$sanitized = sanitize_email( $candidate );

			if ( '' !== $sanitized && is_email( $sanitized ) ) {
				$valid[ $sanitized ] = true;
			}
		}

		return array_keys( $valid );
	}

	/**
	 * Converts the configured headers object into the "Name: Value" line
	 * array `wp_mail()` expects, sanitizing each piece defensively since
	 * this ultimately reaches PHPMailer.
	 *
	 * @param array<mixed, mixed> $headers Configured header name => value pairs.
	 *
	 * @return string[]
	 */
	private function buildHeaders( array $headers ): array {
		$lines = array();

		foreach ( $headers as $name => $value ) {
			$name = trim( (string) $name );

			if ( '' === $name || is_int( $name ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$lines[] = sprintf( '%s: %s', str_replace( array( "\r", "\n" ), '', $name ), str_replace( array( "\r", "\n" ), '', $value ) );
		}

		return $lines;
	}
}
