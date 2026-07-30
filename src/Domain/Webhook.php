<?php
/**
 * Webhook domain entity.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Domain;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An inbound webhook endpoint (roadmap item 13). Deliberately never
 * exposes a decrypted signing secret itself — it only carries whatever
 * `signing_secret` held (still encrypted, or '' when unset); only
 * `Service\WebhookService`, which owns the `Core\Encryption` dependency,
 * is allowed to decrypt.
 */
class Webhook {

	private int $id;

	private ?int $workflowId;

	private string $publicId;

	/**
	 * Base64 ciphertext, or '' when signature verification is not configured.
	 */
	private string $encryptedSigningSecret;

	/**
	 * @var string[] Exact IP addresses (and/or CIDR ranges) allowed to call this webhook; empty = any IP.
	 */
	private array $ipAllowList;

	private string $createdAt;

	/**
	 * @param string[] $ipAllowList Exact IPs and/or CIDR ranges.
	 */
	public function __construct(
		int $id,
		?int $workflowId,
		string $publicId,
		string $encryptedSigningSecret,
		array $ipAllowList,
		string $createdAt
	) {
		$this->id                     = $id;
		$this->workflowId             = $workflowId;
		$this->publicId               = $publicId;
		$this->encryptedSigningSecret = $encryptedSigningSecret;
		$this->ipAllowList            = $ipAllowList;
		$this->createdAt              = $createdAt;
	}

	/**
	 * Builds an instance from a raw `$wpdb` result row.
	 *
	 * @param object $row Row object as returned by `$wpdb->get_row()`.
	 *
	 * @return self
	 */
	public static function fromRow( object $row ): self {
		$workflow_id = null !== $row->workflow_id && '' !== $row->workflow_id
			? (int) $row->workflow_id
			: null;

		$decoded       = json_decode( (string) $row->ip_allow_list_json, true );
		$ip_allow_list = array();

		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $entry ) {
				if ( is_string( $entry ) && '' !== $entry ) {
					$ip_allow_list[] = $entry;
				}
			}
		}

		return new self(
			(int) $row->id,
			$workflow_id,
			(string) $row->public_id,
			(string) $row->signing_secret,
			$ip_allow_list,
			(string) $row->created_at
		);
	}

	public function id(): int {
		return $this->id;
	}

	public function workflowId(): ?int {
		return $this->workflowId;
	}

	public function publicId(): string {
		return $this->publicId;
	}

	public function encryptedSigningSecret(): string {
		return $this->encryptedSigningSecret;
	}

	public function hasSigningSecret(): bool {
		return '' !== $this->encryptedSigningSecret;
	}

	/**
	 * @return string[]
	 */
	public function ipAllowList(): array {
		return $this->ipAllowList;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}
}
