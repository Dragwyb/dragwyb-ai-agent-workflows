<?php
/**
 * Connection application service.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Core\Encryption;
use WorkflowAutomate\Plugin\Domain\Connection;
use WorkflowAutomate\Plugin\Persistence\ConnectionRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the full credential lifecycle: creation, rotation, deletion, and
 * both the plaintext (for a future real integration to actually use, item
 * 12) and masked (for display) views of a connection's stored fields. Only
 * this class ever calls `Core\Encryption::decrypt()` — no controller,
 * admin page, or repository does so directly, keeping "who is allowed to
 * see a decrypted secret" answerable from one place.
 */
class ConnectionService {

	private ConnectionRepository $connections;

	public function __construct( ConnectionRepository $connections ) {
		$this->connections = $connections;
	}

	/**
	 * Creates a new connection. Every field defined for the auth type must
	 * be present and non-empty — unlike update(), there is no "existing
	 * value" a blank submission could fall back to.
	 *
	 * @param string               $integration_slug Free-form slug identifying which integration this connection is for.
	 * @param string               $auth_type        One of ConnectionAuthTypes::VALID.
	 * @param string               $label            Human-readable name shown in the admin list.
	 * @param array<string,string> $field_values     Raw (plaintext) field name => value, per ConnectionAuthTypes::fields().
	 *
	 * @throws InvalidArgumentException When required input is missing/invalid.
	 * @throws RuntimeException         When the underlying insert fails.
	 *
	 * @return Connection
	 */
	public function create( string $integration_slug, string $auth_type, string $label, array $field_values ): Connection {
		$integration_slug = sanitize_key( $integration_slug );
		$label = trim( sanitize_text_field( $label ) );

		if ( ! in_array( $auth_type, ConnectionAuthTypes::VALID, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Unrecognized authentication type.', 'workflow-automate' ) );
		}

		if ( '' === $integration_slug ) {
			throw new InvalidArgumentException( esc_html__( 'An integration is required.', 'workflow-automate' ) );
		}

		if ( '' === $label ) {
			throw new InvalidArgumentException( esc_html__( 'A connection label is required.', 'workflow-automate' ) );
		}

		$encrypted = array();

		foreach ( ConnectionAuthTypes::fields( $auth_type ) as $field => $meta ) {
			$value = isset( $field_values[ $field ] ) ? trim( (string) $field_values[ $field ] ) : '';

			if ( '' === $value ) {
				throw new InvalidArgumentException( esc_html__( 'All fields are required to create a connection.', 'workflow-automate' ) );
			}

			$encrypted[ $field ] = Encryption::encrypt( $value );
		}

		$connection = $this->connections->insert(
			array(
				'integration_slug' => $integration_slug,
				'auth_type' => $auth_type,
				'label' => $label,
				'credentials' => $encrypted,
				'status' => Connection::STATUS_PENDING,
			)
		);

		if ( null === $connection ) {
			throw new RuntimeException( esc_html__( 'Failed to create the connection.', 'workflow-automate' ) );
		}

		return $connection;
	}

	/**
	 * Updates a connection's label and/or rotates its credential fields.
	 *
	 * A blank (or absent) entry in $field_values leaves that field's
	 * currently stored value untouched — this is what makes "rotate just
	 * the API key without re-typing everything else" possible, and matches
	 * the CURSOR_INSTRUCTIONS.md requirement to support credential
	 * rotation. There is deliberately no way to blank out a field back to
	 * empty through this method; deleting and recreating the connection
	 * covers that rare case without this form needing an extra "clear
	 * this field" control per field.
	 *
	 * @param int                  $id           Connection id.
	 * @param string               $label        New label.
	 * @param array<string,string> $field_values Raw (plaintext) field name => new value; blank/absent = keep existing.
	 *
	 * @throws InvalidArgumentException When the connection does not exist or the label is empty.
	 * @throws RuntimeException         When the underlying update fails.
	 *
	 * @return Connection
	 */
	public function update( int $id, string $label, array $field_values ): Connection {
		$connection = $this->connections->find( $id );

		if ( null === $connection ) {
			throw new InvalidArgumentException( esc_html__( 'The specified connection does not exist.', 'workflow-automate' ) );
		}

		$label = trim( sanitize_text_field( $label ) );

		if ( '' === $label ) {
			throw new InvalidArgumentException( esc_html__( 'A connection label is required.', 'workflow-automate' ) );
		}

		$encrypted = $connection->encryptedCredentials();

		foreach ( ConnectionAuthTypes::fields( $connection->authType() ) as $field => $meta ) {
			$value = isset( $field_values[ $field ] ) ? trim( (string) $field_values[ $field ] ) : '';

			if ( '' !== $value ) {
				$encrypted[ $field ] = Encryption::encrypt( $value );
			}
		}

		$updated = $this->connections->update(
			$id,
			array(
				'label' => $label,
				'credentials' => $encrypted,
			)
		);

		if ( null === $updated ) {
			throw new RuntimeException( esc_html__( 'Failed to update the connection.', 'workflow-automate' ) );
		}

		return $updated;
	}

	/**
	 * Finds a connection by id.
	 *
	 * @param int $id Connection id.
	 *
	 * @return Connection|null
	 */
	public function find( int $id ): ?Connection {
		return $this->connections->find( $id );
	}

	/**
	 * Lists connections with optional filtering and pagination.
	 *
	 * @param array<string, mixed> $args See ConnectionRepository::paginate().
	 *
	 * @return array{items: Connection[], total: int, page: int, per_page: int}
	 */
	public function list( array $args = array() ): array {
		return $this->connections->paginate( $args );
	}

	/**
	 * Permanently deletes a connection.
	 *
	 * @param int $id Connection id.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->connections->delete( $id );
	}

	/**
	 * Decrypts every credential field for actual use (e.g. an action node
	 * executing an authenticated outbound request in a future increment).
	 * Never call this to render anything back to the browser — use
	 * displayCredentials() for that.
	 *
	 * @param Connection $connection Connection to decrypt.
	 *
	 * @return array<string, string|null> Field name => plaintext, or null for a field that failed to decrypt.
	 */
	public function credentials( Connection $connection ): array {
		$plain = array();

		foreach ( $connection->encryptedCredentials() as $field => $ciphertext ) {
			$plain[ $field ] = Encryption::decrypt( (string) $ciphertext );
		}

		return $plain;
	}

	/**
	 * Builds a UI-safe view of a connection's fields: secret fields are
	 * masked to their last 4 characters (or a placeholder if fewer than 4
	 * long), non-secret fields (e.g. a username) are shown in full. A
	 * decrypted secret value never leaves this method's local scope.
	 *
	 * @param Connection $connection Connection to describe.
	 *
	 * @return array<string, array{label: string, secret: bool, configured: bool, display: string}>
	 */
	public function displayCredentials( Connection $connection ): array {
		$encrypted = $connection->encryptedCredentials();
		$result = array();

		foreach ( ConnectionAuthTypes::fields( $connection->authType() ) as $field => $meta ) {
			$ciphertext = isset( $encrypted[ $field ] ) ? (string) $encrypted[ $field ] : '';
			$configured = '' !== $ciphertext;
			$display = '';

			if ( $configured ) {
				$plaintext = Encryption::decrypt( $ciphertext );

				if ( null === $plaintext ) {
					$display = __( '(unable to decrypt — please re-enter this value)', 'workflow-automate' );
				} elseif ( ! empty( $meta['secret'] ) ) {
					$display = self::mask( $plaintext );
				} else {
					$display = $plaintext;
				}
			}

			$result[ $field ] = array(
				'label' => $meta['label'],
				'secret' => ! empty( $meta['secret'] ),
				'configured' => $configured,
				'display' => $display,
			);
		}

		return $result;
	}

	/**
	 * Masks a secret value to its last 4 characters, e.g. "sk_live_abcd1234"
	 * becomes "••••••••••••1234". Values of 4 characters or fewer are
	 * masked entirely, so a very short secret's full value is never shown.
	 *
	 * @param string $value Plaintext secret value.
	 *
	 * @return string
	 */
	private static function mask( string $value ): string {
		$length = strlen( $value );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		return str_repeat( '•', $length - 4 ) . substr( $value, -4 );
	}
}
