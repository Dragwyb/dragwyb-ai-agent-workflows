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
 * Tracks workflows currently executing in this PHP request.
 *
 * WordPress hooks fire synchronously, so an action which writes content can
 * trigger the workflow that is still performing that write. Tracking a depth
 * per workflow lets the trigger binder reject that self-reentrant event while
 * still allowing other workflows to react to it.
 */
class TriggerReentrancyGuard {

	/**
	 * Short debounce so Gutenberg/REST double-saves of the same post do not
	 * start two runs (and burn API quota) within the same few seconds.
	 */
	private const DEBOUNCE_SECONDS = 5;

	/** @var array<int, int> */
	private array $active_workflows = array();

	/** @var array<string, true> */
	private array $claimed_triggers = array();

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
	 * Claims a trigger firing for this request / short debounce window.
	 *
	 * Returns false when the same workflow+key was already claimed (duplicate
	 * save_post / queued twin). Returns true on the first claim.
	 *
	 * @param int    $workflow_id Workflow id.
	 * @param string $dedupe_key  Stable key, e.g. "post:70".
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
