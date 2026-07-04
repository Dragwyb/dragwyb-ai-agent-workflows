<?php
/**
 * Test-flow listener: capture trigger payloads while building a workflow.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Domain\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores per-workflow test-listen state inside workflow settings.
 */
class WorkflowTestListenerService {

	private WorkflowService $workflows;

	public function __construct( WorkflowService $workflows ) {
		$this->workflows = $workflows;
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return bool
	 */
	public function isListening( int $workflow_id ): bool {
		$settings = $this->settingsFor( $workflow_id );

		return ! empty( $settings['test_listen_active'] );
	}

	/**
	 * @return array<int>
	 */
	public function listeningWorkflowIds(): array {
		$result = $this->workflows->list(
			array(
				'per_page' => 100,
			)
		);

		$ids = array();

		foreach ( $result['items'] as $workflow ) {
			$settings = $workflow->settings();

			if ( is_array( $settings ) && ! empty( $settings['test_listen_active'] ) ) {
				$ids[] = $workflow->id();
			}
		}

		return $ids;
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function startListening( int $workflow_id ): void {
		$this->patchSettings(
			$workflow_id,
			array(
				'test_listen_active' => true,
				'test_listen_started_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function stopListening( int $workflow_id ): void {
		$this->patchSettings(
			$workflow_id,
			array(
				'test_listen_active' => false,
			)
		);
	}

	/**
	 * @param int                  $workflow_id Workflow id.
	 * @param array<string, mixed> $payload     Raw trigger payload.
	 *
	 * @return void
	 */
	public function capturePayload( int $workflow_id, array $payload ): void {
		$this->patchSettings(
			$workflow_id,
			array(
				'sample_payload' => $payload,
				'sample_payload_captured_at' => gmdate( 'Y-m-d H:i:s' ),
				'test_listen_active' => false,
			)
		);
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return array<string, mixed>
	 */
	public function status( int $workflow_id ): array {
		$settings = $this->settingsFor( $workflow_id );

		return array(
			'listening' => ! empty( $settings['test_listen_active'] ),
			'has_sample' => ! empty( $settings['sample_payload'] ),
			'sample_payload' => $settings['sample_payload'] ?? null,
			'captured_at' => $settings['sample_payload_captured_at'] ?? null,
			'started_at' => $settings['test_listen_started_at'] ?? null,
		);
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return array<string, mixed>
	 */
	public function samplePayload( int $workflow_id ): array {
		$settings = $this->settingsFor( $workflow_id );
		$payload  = $settings['sample_payload'] ?? array();

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * @param int   $workflow_id Workflow id.
	 * @param array $patch       Settings keys to merge.
	 *
	 * @return void
	 */
	private function patchSettings( int $workflow_id, array $patch ): void {
		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			return;
		}

		$settings = $workflow->settings();

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$this->workflows->update(
			$workflow_id,
			array(
				'settings' => array_merge( $settings, $patch ),
			)
		);
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return array<string, mixed>
	 */
	private function settingsFor( int $workflow_id ): array {
		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			return array();
		}

		$settings = $workflow->settings();

		return is_array( $settings ) ? $settings : array();
	}
}
