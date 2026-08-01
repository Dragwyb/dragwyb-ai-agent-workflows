<?php
/**
 * WorkflowNode domain entity.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Domain;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A plain, persistence-agnostic representation of a single node within a
 * workflow's graph (a trigger, action, or logic step).
 */
class WorkflowNode {

	private int $id;

	private int $workflowId;

	private string $clientNodeId;

	private string $nodeType;

	private ?string $label;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $config;

	private string $createdAt;

	private string $updatedAt;

	/**
	 * @param array<string, mixed>|null $config Node configuration/field mapping.
	 */
	public function __construct(
		int $id,
		int $workflowId,
		string $clientNodeId,
		string $nodeType,
		?string $label,
		?array $config,
		string $createdAt,
		string $updatedAt
	) {
		$this->id           = $id;
		$this->workflowId   = $workflowId;
		$this->clientNodeId = $clientNodeId;
		$this->nodeType     = $nodeType;
		$this->label        = $label;
		$this->config       = $config;
		$this->createdAt    = $createdAt;
		$this->updatedAt    = $updatedAt;
	}

	/**
	 * Builds an instance from a raw `$wpdb` result row.
	 *
	 * @param object $row Row object as returned by `$wpdb->get_row()`.
	 *
	 * @return self
	 */
	public static function fromRow( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->workflow_id,
			(string) $row->client_node_id,
			(string) $row->node_type,
			null !== $row->label ? (string) $row->label : null,
			self::decodeJsonObjectOrNull( $row->config_json ),
			(string) $row->created_at,
			(string) $row->updated_at
		);
	}

	/**
	 * @param null|string $json Raw JSON string, possibly null.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function decodeJsonObjectOrNull( ?string $json ): ?array {
		if ( null === $json || '' === $json ) {
			return null;
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	public function id(): int {
		return $this->id;
	}

	public function workflowId(): int {
		return $this->workflowId;
	}

	public function clientNodeId(): string {
		return $this->clientNodeId;
	}

	public function nodeType(): string {
		return $this->nodeType;
	}

	public function label(): ?string {
		return $this->label;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function config(): ?array {
		return $this->config;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}

	public function updatedAt(): string {
		return $this->updatedAt;
	}
}
