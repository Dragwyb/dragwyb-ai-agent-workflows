<?php
/**
 * Request-scoped workflow trigger reentrancy guard.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevents workflow ↔ WordPress feedback loops.
 *
 * Three layers:
 *
 * 1. Per-workflow depth while `executeNodes()` runs — the same workflow cannot
 *    queue/run again from a hook it just caused.
 * 2. Global write depth while a WordPress action mutates content — no workflow
 *    (including a different one) can start mid-write, which stops A↔B ping-pong.
 * 3. Short claim/debounce for the same workflow+entity key so Gutenberg/REST
 *    double-fires of one editor click only start one run.
 */
class TriggerReentrancyGuard {

	/**
	 * Short debounce so Gutenberg/REST double-saves of the same post do not
	 * start two runs (and burn API quota) within the same few seconds.
	 */
	private const DEBOUNCE_SECONDS = 5;

	/**
	 * Request-scoped instance so WordPress action adapters (built outside the
	 * container) can still enter/leave the write suppressor.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/** @var array<int, int> */
	private array $active_workflows = array();

	/** @var int */
	private int $write_depth = 0;

	/** @var array<string, true> */
	private array $claimed_triggers = array();

	/**
	 * Remembers the container singleton for this request.
	 *
	 * @param self $instance Guard instance.
	 *
	 * @return void
	 */
	public static function bindInstance( self $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * @return self|null
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Marks a workflow as executing.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function enter( int $workflow_id ): void {
		if ( $workflow_id <= 0 ) {
			return;
		}

		$this->active_workflows[ $workflow_id ] = ( $this->active_workflows[ $workflow_id ] ?? 0 ) + 1;
	}

	/**
	 * Removes one execution depth for a workflow.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function leave( int $workflow_id ): void {
		if ( ! isset( $this->active_workflows[ $workflow_id ] ) ) {
			return;
		}

		--$this->active_workflows[ $workflow_id ];

		if ( $this->active_workflows[ $workflow_id ] <= 0 ) {
			unset( $this->active_workflows[ $workflow_id ] );
		}
	}

	/**
	 * Reports whether a workflow is currently executing.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return bool
	 */
	public function isActive( int $workflow_id ): bool {
		return isset( $this->active_workflows[ $workflow_id ] ) && $this->active_workflows[ $workflow_id ] > 0;
	}

	/**
	 * Marks that a Workflow Automate action is mutating WordPress state.
	 *
	 * @return void
	 */
	public function beginWrite(): void {
		++$this->write_depth;
	}

	/**
	 * Ends one mutation depth.
	 *
	 * @return void
	 */
	public function endWrite(): void {
		if ( $this->write_depth > 0 ) {
			--$this->write_depth;
		}
	}

	/**
	 * True while any WFA WordPress action is writing (create/update/delete).
	 *
	 * @return bool
	 */
	public function isWriting(): bool {
		return $this->write_depth > 0;
	}

	/**
	 * Claims a trigger firing for this request / short debounce window.
	 *
	 * Returns false when the same workflow+key was already claimed (duplicate
	 * save_post / queued twin). Returns true on the first claim.
	 *
	 * @param int    $workflow_id Workflow id.
	 * @param string $dedupe_key  Stable key, e.g. `"post_id":70`.
	 *
	 * @return bool
	 */
	public function claim( int $workflow_id, string $dedupe_key ): bool {
		if ( $workflow_id <= 0 || '' === $dedupe_key ) {
			return true;
		}

		$memory_key = $workflow_id . ':' . $dedupe_key;

		if ( isset( $this->claimed_triggers[ $memory_key ] ) ) {
			return false;
		}

		$transient_key = 'wfa_trig_' . md5( $memory_key );

		if ( false !== get_transient( $transient_key ) ) {
			$this->claimed_triggers[ $memory_key ] = true;
			return false;
		}

		$this->claimed_triggers[ $memory_key ] = true;
		set_transient( $transient_key, '1', self::DEBOUNCE_SECONDS );

		return true;
	}
}
