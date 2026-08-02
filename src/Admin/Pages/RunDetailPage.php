<?php
/**
 * Run detail admin page.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin\Pages;

use AIAWA\Plugin\Admin\AdminPage;
use AIAWA\Plugin\Admin\RunDuration;
use AIAWA\Plugin\Admin\RunStatusBadge;
use AIAWA\Plugin\Admin\RunTimestamp;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Domain\WorkflowRun;
use AIAWA\Plugin\Domain\WorkflowRunLog;
use AIAWA\Plugin\Persistence\WorkflowRepository;
use AIAWA\Plugin\Persistence\WorkflowRunRepository;
use AIAWA\Plugin\Service\SettingsService;
use AIAWA\Plugin\Service\WorkflowExecutionService;

// BuilderPage and RunsPage live in this same namespace (AIAWA\Plugin\Admin\Pages), so no `use` import is needed to reference BuilderPage::SLUG/RunsPage::SLUG below.

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a single run's metadata and its full, ordered list of per-node log
 * entries (roadmap item 9), plus a "Re-run" action. Reached only via a link
 * from RunsListTable or another run's "Re-run" redirect — never shown in
 * the menu (see showInMenu()), same "hidden screen" pattern as BuilderPage.
 */
class RunDetailPage implements AdminPage {

	public const SLUG = 'aiawa-run-detail';

	/**
	 * Statuses that make sense to re-run — see RunsListTable's own
	 * RERUNNABLE_STATUSES for the same reasoning (never queued/running).
	 */
	private const RERUNNABLE_STATUSES = array(
		WorkflowRun::STATUS_SUCCESS,
		WorkflowRun::STATUS_FAILED,
		WorkflowRun::STATUS_PARTIAL,
	);

	private WorkflowRunRepository $runs;

	private WorkflowRepository $workflows;

	private WorkflowExecutionService $executor;

	private SettingsService $settings;

	public function __construct( WorkflowRunRepository $runs, WorkflowRepository $workflows, WorkflowExecutionService $executor, SettingsService $settings ) {
		$this->runs      = $runs;
		$this->workflows = $workflows;
		$this->executor  = $executor;
		$this->settings  = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pageTitle(): string {
		return __( 'Run Details', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Run Details', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_RUNS;
	}

	/**
	 * {@inheritDoc}
	 */
	public function showInMenu(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueueAssets(): void {
		wp_enqueue_style(
			'aiawa-admin',
			AIAWA_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			AIAWA_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-agentflow' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which run to view.
		$run_id = isset( $_GET['run_id'] ) ? absint( wp_unslash( $_GET['run_id'] ) ) : 0;
		$run    = $run_id > 0 ? $this->runs->find( $run_id ) : null;

		echo '<div class="wrap aiawa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		if ( null === $run ) {
			$this->renderNotFound();
			echo '</div>';

			return;
		}

		$this->renderNotice();
		$this->renderMeta( $run );
		$this->renderTriggerPayload( $run );
		$this->renderLogs( $run );

		echo '</div>';
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return string
	 */
	private function builderUrl( int $workflow_id ): string {
		return add_query_arg(
			array(
				'page'     => BuilderPage::SLUG,
				'workflow' => $workflow_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function detailUrl( int $run_id ): string {
		return add_query_arg(
			array(
				'page'   => self::SLUG,
				'run_id' => $run_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @return void
	 */
	private function renderNotFound(): void {
		printf(
			'<p>%1$s</p><p><a href="%2$s">%3$s</a></p>',
			esc_html__( 'That run could not be found.', 'dragwyb-agentflow' ),
			esc_url( admin_url( 'admin.php?page=' . RunsPage::SLUG ) ),
			esc_html__( 'Back to Runs', 'dragwyb-agentflow' )
		);
	}

	/**
	 * @param WorkflowRun $run The run.
	 *
	 * @return void
	 */
	private function renderMeta( WorkflowRun $run ): void {
		$workflow = $this->workflows->find( $run->workflowId(), true );

		echo '<table class="widefat fixed striped aiawa-run-meta"><tbody>';

		$this->renderMetaRow(
			__( 'Workflow', 'dragwyb-agentflow' ),
			$workflow
				? sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $this->builderUrl( $run->workflowId() ) ),
					esc_html( $workflow->title() )
				)
				: esc_html__( '(deleted workflow)', 'dragwyb-agentflow' )
		);

		$this->renderMetaRow( __( 'Status', 'dragwyb-agentflow' ), RunStatusBadge::render( $run->status() ) );
		$this->renderMetaRow( __( 'Attempt', 'dragwyb-agentflow' ), esc_html( (string) $run->attempts() ) );

		if ( null !== $run->parentRunId() ) {
			$this->renderMetaRow(
				__( 'Re-run of', 'dragwyb-agentflow' ),
				sprintf(
					'<a href="%1$s">#%2$d</a>',
					esc_url( $this->detailUrl( $run->parentRunId() ) ),
					$run->parentRunId()
				)
			);
		}

		$this->renderMetaRow(
			__( 'Started', 'dragwyb-agentflow' ),
			$run->startedAt() ? esc_html( RunTimestamp::format( $run->startedAt(), $this->settings->displayTimestampsInUtc() ) ) : esc_html__( 'Not started yet', 'dragwyb-agentflow' )
		);
		$this->renderMetaRow(
			__( 'Finished', 'dragwyb-agentflow' ),
			$run->finishedAt() ? esc_html( RunTimestamp::format( $run->finishedAt(), $this->settings->displayTimestampsInUtc() ) ) : esc_html__( 'Not finished yet', 'dragwyb-agentflow' )
		);
		$this->renderMetaRow( __( 'Duration', 'dragwyb-agentflow' ), esc_html( RunDuration::forRun( $run ) ) );

		if ( in_array( $run->status(), self::RERUNNABLE_STATUSES, true ) ) {
			$this->renderMetaRow(
				__( 'Actions', 'dragwyb-agentflow' ),
				$this->rerunForm( $run->id() ) . ' ' . $this->deleteForm( $run->id() )
			);
		} else {
			$this->renderMetaRow( __( 'Actions', 'dragwyb-agentflow' ), $this->deleteForm( $run->id() ) );
		}

		echo '</tbody></table>';
	}

	/**
	 * @param string $label Row label.
	 * @param string $value Already-escaped/safe HTML value.
	 *
	 * @return void
	 */
	private function renderMetaRow( string $label, string $value ): void {
		printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), wp_kses_post( $value ) );
	}

	/**
	 * @param WorkflowRun $run The run.
	 *
	 * @return void
	 */
	private function renderTriggerPayload( WorkflowRun $run ): void {
		$payload = $run->triggerPayload();

		if ( array() === $payload ) {
			return;
		}

		printf(
			'<details class="aiawa-run-details-block"><summary>%1$s</summary><pre>%2$s</pre></details>',
			esc_html__( 'Trigger payload', 'dragwyb-agentflow' ),
			esc_html( (string) wp_json_encode( $payload, JSON_PRETTY_PRINT ) )
		);
	}

	/**
	 * @param WorkflowRun $run The run.
	 *
	 * @return void
	 */
	private function renderLogs( WorkflowRun $run ): void {
		$logs = $this->executor->logsFor( $run->id() );

		echo '<h2>' . esc_html__( 'Node log', 'dragwyb-agentflow' ) . '</h2>';

		if ( array() === $logs ) {
			echo '<p>' . esc_html__( 'This run has no node log entries yet.', 'dragwyb-agentflow' ) . '</p>';

			return;
		}

		echo '<table class="widefat fixed striped aiawa-run-logs"><thead><tr>';
		echo '<th>' . esc_html__( 'Node', 'dragwyb-agentflow' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'dragwyb-agentflow' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'dragwyb-agentflow' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'dragwyb-agentflow' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'dragwyb-agentflow' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $logs as $log ) {
			$this->renderLogRow( $log );
		}

		echo '</tbody></table>';
	}

	/**
	 * @param WorkflowRunLog $log One node's outcome.
	 *
	 * @return void
	 */
	private function renderLogRow( WorkflowRunLog $log ): void {
		echo '<tr>';
		printf( '<td>%s</td>', esc_html( $this->nodeLabel( $log ) ) );
		printf( '<td>%s</td>', wp_kses_post( $this->logStatusBadge( $log->status() ) ) );
		printf( '<td>%s</td>', esc_html( RunDuration::forNode( $log->durationMs() ) ) );
		printf( '<td>%s</td>', esc_html( $log->message() ?? '' ) );
		echo '<td>';
		$this->renderLogDetails( $log );
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * @param WorkflowRunLog $log One node's outcome.
	 *
	 * @return string
	 */
	private function nodeLabel( WorkflowRunLog $log ): string {
		if ( null !== $log->nodeLabel() && '' !== $log->nodeLabel() ) {
			return $log->nodeLabel();
		}

		if ( null !== $log->nodeType() ) {
			return $log->nodeType();
		}

		return __( '(unknown node)', 'dragwyb-agentflow' );
	}

	/**
	 * WorkflowRunLog::STATUS_* is a different, smaller set than
	 * WorkflowRun::STATUS_*, so this intentionally does not reuse
	 * RunStatusBadge (which is typed to run statuses specifically).
	 *
	 * @param string $status One of WorkflowRunLog::STATUS_*.
	 *
	 * @return string
	 */
	private function logStatusBadge( string $status ): string {
		$labels = array(
			WorkflowRunLog::STATUS_SUCCESS => __( 'Success', 'dragwyb-agentflow' ),
			WorkflowRunLog::STATUS_ERROR   => __( 'Error', 'dragwyb-agentflow' ),
			WorkflowRunLog::STATUS_SKIPPED => __( 'Skipped', 'dragwyb-agentflow' ),
		);

		$label = $labels[ $status ] ?? __( 'Unknown', 'dragwyb-agentflow' );
		$slug  = array_key_exists( $status, $labels ) ? $status : 'unknown';

		return sprintf(
			'<span class="aiawa-status-badge aiawa-status-badge--%1$s">%2$s</span>',
			esc_attr( $slug ),
			esc_html( $label )
		);
	}

	/**
	 * Per Section 7: surface a clear message by default, with technical
	 * detail available on demand for advanced users, rather than always
	 * showing raw JSON inline.
	 *
	 * @param WorkflowRunLog $log One node's outcome.
	 *
	 * @return void
	 */
	private function renderLogDetails( WorkflowRunLog $log ): void {
		if ( null === $log->input() && null === $log->output() ) {
			echo '&#8212;';

			return;
		}

		echo '<details class="aiawa-run-details-block"><summary>' . esc_html__( 'View', 'dragwyb-agentflow' ) . '</summary>';

		if ( null !== $log->input() ) {
			echo '<p><strong>' . esc_html__( 'Input', 'dragwyb-agentflow' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) wp_json_encode( $log->input(), JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		if ( null !== $log->output() ) {
			echo '<p><strong>' . esc_html__( 'Output', 'dragwyb-agentflow' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) wp_json_encode( $log->output(), JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		echo '</details>';
	}

	/**
	 * Renders a small standalone POST form, matching
	 * WorkflowsListTable::actionForm()'s CSRF reasoning, styled as a normal
	 * button here rather than a row-action link since this is the page's
	 * primary action, not a secondary one.
	 *
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function rerunForm( int $run_id ): string {
		return $this->runActionForm(
			'rerun',
			$run_id,
			__( 'Re-run this workflow', 'dragwyb-agentflow' ),
			'button button-secondary'
		);
	}

	/**
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function deleteForm( int $run_id ): string {
		return $this->runActionForm(
			'delete',
			$run_id,
			__( 'Delete this run', 'dragwyb-agentflow' ),
			'button button-link-delete',
			true
		);
	}

	/**
	 * @param string $op            One of 'rerun', 'delete'.
	 * @param int    $run_id        Run id.
	 * @param string $label         Button label.
	 * @param string $button_class  CSS classes for the submit button.
	 * @param bool   $confirm       Whether to ask for browser confirmation.
	 *
	 * @return string
	 */
	private function runActionForm( string $op, int $run_id, string $label, string $button_class, bool $confirm = false ): string {
		$nonce_field  = wp_nonce_field( 'aiawa_run_action_' . $op . '_' . $run_id, '_wpnonce', true, false );
		$confirm_attr = '';

		if ( $confirm ) {
			$confirm_attr = sprintf(
				' onclick="return confirm(%s);"',
				wp_json_encode( __( 'Delete this run permanently? This cannot be undone.', 'dragwyb-agentflow' ) )
			);
		}

		return sprintf(
			'<form method="post" action="%1$s" style="display:inline-block;margin-right:8px;">'
				. '<input type="hidden" name="action" value="aiawa_run_action" />'
				. '<input type="hidden" name="op" value="%2$s" />'
				. '<input type="hidden" name="run_id" value="%3$d" />'
				. '%4$s'
				. '<button type="submit" class="%5$s"%6$s>%7$s</button>'
				. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $op ),
			$run_id,
			$nonce_field,
			esc_attr( $button_class ),
			$confirm_attr,
			esc_html( $label )
		);
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?aiawa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'rerun_started' => array(
				'message' => __( 'Re-run started below.', 'dragwyb-agentflow' ),
				'type'    => 'success',
			),
			'rerun_failed'  => array(
				'message' => __( 'That run could not be re-run.', 'dragwyb-agentflow' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['aiawa_notice'] ) ? sanitize_key( wp_unslash( $_GET['aiawa_notice'] ) ) : '';
		$notices = $this->notices();

		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ]['type'] ),
			esc_html( $notices[ $key ]['message'] )
		);
	}
}
