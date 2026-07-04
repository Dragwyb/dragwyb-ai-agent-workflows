<?php
/**
 * WorkflowRun domain entity.
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

	private ?string $startedAt;

	private ?string $finishedAt;

	private string $createdAt;

	public function __construct(
		int $id,
		int $workflowId,
		?int $parentRunId,
		string $status,
		?string $startedAt,
		?string $finishedAt,
		string $createdAt
	) {
		$this->id = $id;
		$this->workflowId = $workflowId;
		$this->parentRunId = $parentRunId;
		$this->status = $status;
		$this->startedAt = $startedAt;
		$this->finishedAt = $finishedAt;
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
			(int) $row->workflow_id,
			null !== $row->parent_run_id ? (int) $row->parent_run_id : null,
			(string) $row->status,
			$row->started_at,
			$row->finished_at,
			(string) $row->created_at
		);
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
