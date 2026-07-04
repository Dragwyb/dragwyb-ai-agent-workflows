<?php
/**
 * Connection domain entity.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Domain;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A stored third-party credential (roadmap item 11). Deliberately never
 * exposes decrypted credential values itself — it only carries whatever
 * `credentials_json` held (each value still individually encrypted); only
 * `Service\ConnectionService`, which owns the `Core\Encryption` dependency,
 * is allowed to decrypt.
 */
class Connection {

	public const STATUS_PENDING = 0;

	public const STATUS_VERIFIED = 1;

	public const STATUS_FAILED = 2;

	/**
	 * @var int[]
	 */
	public const VALID_STATUSES = array( self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_FAILED );

	private int $id;

	private string $integrationSlug;

	private string $authType;

	private string $label;

	/**
	 * @var array<string, string> Field name => base64 ciphertext (or '' for an unset field).
	 */
	private array $encryptedCredentials;

	private int $status;

	private string $createdAt;

	private string $updatedAt;

	/**
	 * @param array<string, string> $encryptedCredentials Field name => base64 ciphertext.
	 */
	public function __construct(
		int $id,
		string $integrationSlug,
		string $authType,
		string $label,
		array $encryptedCredentials,
		int $status,
		string $createdAt,
		string $updatedAt
	) {
		$this->id = $id;
		$this->integrationSlug = $integrationSlug;
		$this->authType = $authType;
		$this->label = $label;
		$this->encryptedCredentials = $encryptedCredentials;
		$this->status = $status;
		$this->createdAt = $createdAt;
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Builds an instance from a raw `$wpdb` result row.
	 *
	 * @param object $row Row object as returned by `$wpdb->get_row()`.
	 *
	 * @return self
	 */
	public static function fromRow( object $row ): self {
		$decoded = json_decode( (string) $row->credentials_json, true );

		return new self(
			(int) $row->id,
			(string) $row->integration_slug,
			(string) $row->auth_type,
			(string) $row->label,
			is_array( $decoded ) ? $decoded : array(),
			(int) $row->status,
			(string) $row->created_at,
			(string) $row->updated_at
		);
	}

	public function id(): int {
		return $this->id;
	}

	public function integrationSlug(): string {
		return $this->integrationSlug;
	}

	public function authType(): string {
		return $this->authType;
	}

	public function label(): string {
		return $this->label;
	}

	/**
	 * @return array<string, string>
	 */
	public function encryptedCredentials(): array {
		return $this->encryptedCredentials;
	}

	public function status(): int {
		return $this->status;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}

	public function updatedAt(): string {
		return $this->updatedAt;
	}
}
