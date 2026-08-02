<?php
/**
 * WorkflowRun domain entity.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Domain;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A plain, persistence-agnostic representation of a single execution of a
 * workflow (one "run").
 */
class WorkflowRun {

	public const STATUS_QUEUED = 'queued';

	public const STATUS_RUNNING = 'running';

	public const STATUS_SUCCESS = 'success';

	public const STATUS_FAILED = 'failed';

	public const STATUS_PARTIAL = 'partial';

	/**
	 * @var string[]
	 */
	public const VALID_STATUSES = array(
		self::STATUS_QUEUED,
		self::STATUS_RUNNING,
		self::STATUS_SUCCESS,
		self::STATUS_FAILED,
		self::STATUS_PARTIAL,
	);

	private int $id;

	private int $workflowId;

	private ?int $parentRunId;

	private string $status;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $triggerPayload;

	private int $attempts;

	private ?string $nextAttemptAt;

	private ?string $claimToken;

	private ?string $startedAt;

	private ?string $finishedAt;

	private string $createdAt;

	/**
	 * @param array<string, mixed>|null $triggerPayload Data the triggering event provided; null/empty for a manual run.
	 */
	public function __construct(
		int $id,
		int $workflowId,
		?int $parentRunId,
		string $status,
		?array $triggerPayload,
		int $attempts,
		?string $nextAttemptAt,
		?string $claimToken,
		?string $startedAt,
		?string $finishedAt,
		string $createdAt
	) {
		$this->id             = $id;
		$this->workflowId     = $workflowId;
		$this->parentRunId    = $parentRunId;
		$this->status         = $status;
		$this->triggerPayload = $triggerPayload;
		$this->attempts       = $attempts;
		$this->nextAttemptAt  = $nextAttemptAt;
		$this->claimToken     = $claimToken;
		$this->startedAt      = $startedAt;
		$this->finishedAt     = $finishedAt;
		$this->createdAt      = $createdAt;
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
			null !== $row->parent_run_id ? (int) $row->parent_run_id : null,
			(string) $row->status,
			self::decodeJsonObjectOrNull( $row->trigger_payload_json ),
			(int) $row->attempts,
			$row->next_attempt_at,
			null !== $row->claim_token ? (string) $row->claim_token : null,
			$row->started_at,
			$row->finished_at,
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

	public function workflowId(): int {
		return $this->workflowId;
	}

	public function parentRunId(): ?int {
		return $this->parentRunId;
	}

	public function status(): string {
		return $this->status;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function triggerPayload(): array {
		return $this->triggerPayload ?? array();
	}

	/**
	 * 1-indexed: this run is "attempt N" of its retry chain (see
	 * `parentRunId()` — each retry is a new row, not a mutation of the
	 * original).
	 *
	 * @return int
	 */
	public function attempts(): int {
		return $this->attempts;
	}

	public function nextAttemptAt(): ?string {
		return $this->nextAttemptAt;
	}

	/**
	 * Set only for a run that went through
	 * `WorkflowRunRepository::claimBatch()` (the background/queued path);
	 * always null for a synchronous `WorkflowExecutionService::run()` call.
	 *
	 * @return string|null
	 */
	public function claimToken(): ?string {
		return $this->claimToken;
	}

	public function startedAt(): ?string {
		return $this->startedAt;
	}

	public function finishedAt(): ?string {
		return $this->finishedAt;
	}

	public function createdAt(): string {
		return $this->createdAt;
	}
}
