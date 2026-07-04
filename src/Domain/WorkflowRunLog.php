<?php
/**
 * WorkflowRunLog domain entity.
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
 * A plain, persistence-agnostic representation of a single node's outcome
 * within a workflow run.
 */
class WorkflowRunLog {

	public const STATUS_SUCCESS = 'success';

	public const STATUS_ERROR = 'error';

	public const STATUS_SKIPPED = 'skipped';

	private int $id;

	private int $runId;

	private ?int $nodeId;

	private ?string $nodeType;

	private ?string $nodeLabel;

	private string $status;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $input;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $output;

	private ?string $message;

	private ?int $durationMs;

	private string $createdAt;

	/**
	 * @param array<string, mixed>|null $input  The node's configuration at the time it ran.
	 * @param array<string, mixed>|null $output The node's raw execution result.
	 */
	public function __construct(
		int $id,
		int $runId,
		?int $nodeId,
		?string $nodeType,
		?string $nodeLabel,
		string $status,
		?array $input,
		?array $output,
		?string $message,
		?int $durationMs,
		string $createdAt
	) {
		$this->id = $id;
		$this->runId = $runId;
		$this->nodeId = $nodeId;
		$this->nodeType = $nodeType;
		$this->nodeLabel = $nodeLabel;
		$this->status = $status;
		$this->input = $input;
		$this->output = $output;
		$this->message = $message;
		$this->durationMs = $durationMs;
		$this->createdAt = $createdAt;
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
			(int) $row->run_id,
			null !== $row->node_id ? (int) $row->node_id : null,
			null !== $row->node_type ? (string) $row->node_type : null,
			null !== $row->node_label ? (string) $row->node_label : null,
			(string) $row->status,
			self::decodeJsonObjectOrNull( $row->input_json ),
			self::decodeJsonObjectOrNull( $row->output_json ),
			null !== $row->message ? (string) $row->message : null,
			null !== $row->duration_ms ? (int) $row->duration_ms : null,
			(string) $row->created_at
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

	public function runId(): int {
		return $this->runId;
	}

	public function nodeId(): ?int {
		return $this->nodeId;
	}

	/**
	 * The node type slug this entry ran as, snapshotted at execution time
	 * (see `Migrations\AddNodeSnapshotColumnsToWorkflowRunLogsTable`) so it
	 * remains accurate even if `nodeId()` no longer resolves to a live
	 * node. Null for entries logged before that migration ran.
	 *
	 * @return string|null
	 */
	public function nodeType(): ?string {
		return $this->nodeType;
	}

	/**
	 * The node's label at the time it ran, if it had one. Same
	 * snapshot-at-execution-time reasoning as nodeType().
	 *
	 * @return string|null
	 */
	public function nodeLabel(): ?string {
		return $this->nodeLabel;
	}

	public function status(): string {
		return $this->status;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function input(): ?array {
		return $this->input;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function output(): ?array {
		return $this->output;
	}

	public function message(): ?string {
		return $this->message;
	}

	public function durationMs(): ?int {
		return $this->durationMs;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}
}
