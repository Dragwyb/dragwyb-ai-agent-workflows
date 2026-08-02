<?php
/**
 * Test-flow listener: capture trigger payloads while building a workflow.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

use DragwybAgentFlow\Plugin\Domain\Workflow;

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
				'test_listen_active'     => true,
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
	 * @param string|null          $trigger_type Slug of the trigger node that fired.
	 *
	 * @return void
	 */
	public function capturePayload( int $workflow_id, array $payload, ?string $trigger_type = null ): void {
		$patch = array(
			'sample_payload'             => $payload,
			'sample_payload_captured_at' => gmdate( 'Y-m-d H:i:s' ),
			'test_listen_active'         => false,
		);

		if ( null !== $trigger_type && '' !== $trigger_type ) {
			$patch['sample_payload_trigger_type'] = $trigger_type;
		}

		$this->patchSettings( $workflow_id, $patch );
	}

	/**
	 * Removes saved test sample data (e.g. after the builder trigger type changes).
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function clearSample( int $workflow_id ): void {
		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			return;
		}

		$settings = $workflow->settings();

		if ( ! is_array( $settings ) ) {
			return;
		}

		unset(
			$settings['sample_payload'],
			$settings['sample_payload_captured_at'],
			$settings['sample_payload_trigger_type']
		);

		$this->workflows->update(
			$workflow_id,
			array(
				'settings' => $settings,
			)
		);
	}

	/**
	 * Returns the saved sample only when it belongs to the given trigger type.
	 *
	 * @param int         $workflow_id  Workflow id.
	 * @param string|null $trigger_type Expected trigger slug; null skips the check.
	 *
	 * @return array<string, mixed>
	 */
	public function samplePayloadForTrigger( int $workflow_id, ?string $trigger_type = null ): array {
		$settings = $this->settingsFor( $workflow_id );
		$payload  = $settings['sample_payload'] ?? array();

		if ( ! is_array( $payload ) || array() === $payload ) {
			return array();
		}

		if ( null === $trigger_type || '' === $trigger_type ) {
			return $payload;
		}

		$stored_type = $settings['sample_payload_trigger_type'] ?? null;

		if ( is_string( $stored_type ) && '' !== $stored_type ) {
			return $stored_type === $trigger_type ? $payload : array();
		}

		return $this->legacyPayloadMatchesTrigger( $trigger_type, $payload ) ? $payload : array();
	}

	/**
	 * @param string               $trigger_type Expected trigger slug.
	 * @param array<string, mixed> $payload      Saved sample payload.
	 *
	 * @return bool
	 */
	private function legacyPayloadMatchesTrigger( string $trigger_type, array $payload ): bool {
		$source = isset( $payload['source'] ) ? (string) $payload['source'] : '';

		if ( 'elementor_form_submitted_trigger' === $trigger_type ) {
			return 'elementor' === $source;
		}

		if ( 'elementor_atomic_form_submitted_trigger' === $trigger_type ) {
			return 'elementor-atomic' === $source;
		}

		if ( function_exists( 'str_starts_with' ) && str_starts_with( $trigger_type, 'woocommerce_' ) && 'woocommerce' === $source ) {
			return true;
		}

		if ( 'contact_form7_submitted_trigger' === $trigger_type ) {
			return 'contact-form-7' === $source;
		}

		if ( 'wpforms_submitted_trigger' === $trigger_type ) {
			return 'wpforms' === $source;
		}

		if ( function_exists( 'str_starts_with' ) && str_starts_with( $trigger_type, 'wp_' ) && 'WordPress' === $source ) {
			return true;
		}

		return false;
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return array<string, mixed>
	 */
	public function status( int $workflow_id ): array {
		$settings = $this->settingsFor( $workflow_id );

		return array(
			'listening'                   => ! empty( $settings['test_listen_active'] ),
			'has_sample'                  => ! empty( $settings['sample_payload'] ),
			'sample_payload'              => $settings['sample_payload'] ?? null,
			'sample_payload_trigger_type' => $settings['sample_payload_trigger_type'] ?? null,
			'captured_at'                 => $settings['sample_payload_captured_at'] ?? null,
			'started_at'                  => $settings['test_listen_started_at'] ?? null,
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
