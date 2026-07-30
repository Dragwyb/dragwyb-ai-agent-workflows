<?php
/**
 * Workflow domain entity.
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
 * A plain, persistence-agnostic representation of a workflow.
 *
 * Instances are immutable snapshots: the persistence layer builds a new
 * instance from a database row rather than mutating an existing one, so a
 * `Workflow` object can always be trusted to reflect a single, consistent
 * point in time.
 */
class Workflow {

	public const STATUS_DRAFT = 0;

	public const STATUS_ACTIVE = 1;

	public const STATUS_PAUSED = 2;

	/**
	 * @var int[]
	 */
	public const VALID_STATUSES = array( self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_PAUSED );

	private int $id;

	private string $title;

	private int $status;

	private int $definitionVersion;

	/**
	 * @var array<string, mixed>
	 */
	private array $graph;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $settings;

	private int $runCount;

	private ?string $deletedAt;

	private string $createdAt;

	private string $updatedAt;

	/**
	 * @param array<string, mixed>      $graph    Builder graph (nodes/edges).
	 * @param array<string, mixed>|null $settings Per-workflow settings.
	 */
	public function __construct(
		int $id,
		string $title,
		int $status,
		int $definitionVersion,
		array $graph,
		?array $settings,
		int $runCount,
		?string $deletedAt,
		string $createdAt,
		string $updatedAt
	) {
		$this->id                = $id;
		$this->title             = $title;
		$this->status            = $status;
		$this->definitionVersion = $definitionVersion;
		$this->graph             = $graph;
		$this->settings          = $settings;
		$this->runCount          = $runCount;
		$this->deletedAt         = $deletedAt;
		$this->createdAt         = $createdAt;
		$this->updatedAt         = $updatedAt;
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
			(string) $row->title,
			(int) $row->status,
			(int) $row->definition_version,
			self::decodeJsonObject( $row->graph_json ),
			self::decodeJsonObjectOrNull( $row->settings_json ),
			(int) $row->run_count,
			$row->deleted_at,
			(string) $row->created_at,
			(string) $row->updated_at
		);
	}

	/**
	 * @param null|string $json Raw JSON string, possibly null.
	 *
	 * @return array<string, mixed>
	 */
	private static function decodeJsonObject( ?string $json ): array {
		if ( null === $json || '' === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : array();
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

	public function title(): string {
		return $this->title;
	}

	public function status(): int {
		return $this->status;
	}

	public function definitionVersion(): int {
		return $this->definitionVersion;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function graph(): array {
		return $this->graph;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function settings(): ?array {
		return $this->settings;
	}

	public function runCount(): int {
		return $this->runCount;
	}

	public function isTrashed(): bool {
		return null !== $this->deletedAt;
	}

	public function deletedAt(): ?string {
		return $this->deletedAt;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}

	public function updatedAt(): string {
		return $this->updatedAt;
	}
}
