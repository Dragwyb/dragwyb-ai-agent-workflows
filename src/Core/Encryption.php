<?php
/**
 * Symmetric encryption helper for credential-at-rest storage.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Core;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts/decrypts individual credential field values for
 * `Service\ConnectionService` (roadmap item 11).
 *
 * Key derivation is the concrete fix for `bitpi-analysis.md` opportunity
 * #2 ("weak encryption key material" — the reference product derives its
 * key from `prefix + time()`, stored as the *only* secret in a plaintext
 * option). This class instead combines two independent pieces of secret
 * material, neither of which alone is guessable from the database dump an
 * attacker most commonly gets:
 *
 * - `wp_salt( 'auth' )` — WordPress's own site-wide secret, normally
 *   sourced from `AUTH_KEY`/`AUTH_SALT` in `wp-config.php`, which lives
 *   outside the database entirely (a SQL dump alone does not leak it).
 * - `encryption_key_id` — a random, plugin-generated value stored in
 *   `wp_options` (see Options::PREFIX), generated once on first use.
 *
 * Neither value is used as the AES key directly; both are mixed via
 * `hash()` into a fixed-length 256-bit key. Losing either input alone
 * (e.g. a leaked options table without wp-config.php, or vice versa) is
 * not sufficient to decrypt stored credentials.
 *
 * Ciphertext format (base64 of the concatenation, so a single TEXT/VARCHAR
 * column can hold it): `iv (16 raw bytes) . ciphertext`. A fresh random IV
 * is generated per encryption call — required for CBC mode to be
 * semantically secure when the same key encrypts many values (every
 * credential field on every connection shares one derived key).
 */
class Encryption {

	private const CIPHER = 'aes-256-cbc';

	private const KEY_ID_OPTION = 'encryption_key_id';

	/**
	 * Whether the runtime has everything this class needs. Checked by
	 * `Requirements::check()` at activation time so a missing `openssl`
	 * extension is reported as a clear activation error rather than a
	 * fatal error the first time a connection is saved.
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool {
		return extension_loaded( 'openssl' ) && in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypts a single plaintext value.
	 *
	 * An empty string is treated as "no value" and passed through
	 * unencrypted: encrypting emptiness protects nothing and would just
	 * make "is this field configured?" checks elsewhere have to decrypt
	 * first to find out.
	 *
	 * @param string $plaintext Value to encrypt.
	 *
	 * @return string Base64-encoded ciphertext, or '' if $plaintext was ''.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$iv_length  = openssl_cipher_iv_length( self::CIPHER );
		$iv         = random_bytes( $iv_length );
		$key        = self::key();
		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

		return base64_encode( $mac . $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding binary ciphertext for TEXT column storage, not obfuscating code.
	}

	/**
	 * Decrypts a value previously produced by encrypt().
	 *
	 * @param string $encoded Base64-encoded ciphertext, or '' for "no value".
	 *
	 * @return string|null The plaintext, '' if $encoded was '', or null if
	 *                      $encoded is non-empty but cannot be decrypted
	 *                      (corrupted data, or the key material changed) —
	 *                      deliberately distinct from '' so callers never
	 *                      mistake "undecryptable" for "empty and valid".
	 */
	public static function decrypt( string $encoded ): ?string {
		if ( '' === $encoded ) {
			return '';
		}

		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding binary ciphertext, not obfuscating code.

		if ( false === $raw ) {
			return null;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$key       = self::key();

		// Authenticated cipher format: MAC (32 bytes) + IV + ciphertext.
		if ( strlen( $raw ) > 32 + $iv_length ) {
			$mac     = substr( $raw, 0, 32 );
			$payload = substr( $raw, 32 );

			if ( hash_equals( $mac, hash_hmac( 'sha256', $payload, $key, true ) ) ) {
				$iv         = substr( $payload, 0, $iv_length );
				$ciphertext = substr( $payload, $iv_length );

				$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
				return false === $plaintext ? null : $plaintext;
			}
		}

		// Legacy unauthenticated format fallback.
		if ( strlen( $raw ) <= $iv_length ) {
			return null;
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? null : $plaintext;
	}

	/**
	 * Derives the raw 256-bit AES key from wp_salt() material plus the
	 * stored per-site key id, generating the key id on first use.
	 *
	 * @return string Raw (binary) 32-byte key.
	 */
	private static function key(): string {
		$key_id = Options::get( self::KEY_ID_OPTION, '' );

		if ( ! is_string( $key_id ) || '' === $key_id ) {
			$key_id = wp_generate_password( 64, false );

			// add() can lose a race under concurrent requests; re-read
			// afterwards so every process ends up using whichever value
			// actually won, rather than each encrypting with a different key.
			if ( ! Options::add( self::KEY_ID_OPTION, $key_id, true ) ) {
				$existing = Options::get( self::KEY_ID_OPTION, '' );

				if ( is_string( $existing ) && '' !== $existing ) {
					$key_id = $existing;
				}
			}
		}

		return hash( 'sha256', wp_salt( 'auth' ) . '|' . $key_id, true );
	}
}
