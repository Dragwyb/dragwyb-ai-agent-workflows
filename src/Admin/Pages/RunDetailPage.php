<?php
/**
 * Run detail admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Admin\RunDuration;
use WorkflowAutomate\Plugin\Admin\RunStatusBadge;
use WorkflowAutomate\Plugin\Admin\RunTimestamp;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Domain\WorkflowRunLog;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Service\SettingsService;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;

// BuilderPage and RunsPage live in this same namespace (WorkflowAutomate\Plugin\Admin\Pages), so no `use` import is needed to reference BuilderPage::SLUG/RunsPage::SLUG below.

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

	public const SLUG = 'wfa-run-detail';

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
		$this->runs = $runs;
		$this->workflows = $workflows;
		$this->executor = $executor;
		$this->settings = $settings;
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
		return __( 'Run Details', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Run Details', 'workflow-automate' );
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
			'wfa-admin',
			WFA_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			WFA_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which run to view.
		$run_id = isset( $_GET['run_id'] ) ? absint( wp_unslash( $_GET['run_id'] ) ) : 0;
		$run = $run_id > 0 ? $this->runs->find( $run_id ) : null;

		echo '<div class="wrap wfa-admin-page">';
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
				'page' => BuilderPage::SLUG,
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
				'page' => self::SLUG,
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
			esc_html__( 'That run could not be found.', 'workflow-automate' ),
			esc_url( admin_url( 'admin.php?page=' . RunsPage::SLUG ) ),
			esc_html__( 'Back to Runs', 'workflow-automate' )
		);
	}

	/**
	 * @param WorkflowRun $run The run.
	 *
	 * @return void
	 */
	private function renderMeta( WorkflowRun $run ): void {
		$workflow = $this->workflows->find( $run->workflowId(), true );

		echo '<table class="widefat fixed striped wfa-run-meta"><tbody>';

		$this->renderMetaRow(
			__( 'Workflow', 'workflow-automate' ),
			$workflow
				? sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $this->builderUrl( $run->workflowId() ) ),
					esc_html( $workflow->title() )
				)
				: esc_html__( '(deleted workflow)', 'workflow-automate' )
		);

		$this->renderMetaRow( __( 'Status', 'workflow-automate' ), RunStatusBadge::render( $run->status() ) );
		$this->renderMetaRow( __( 'Attempt', 'workflow-automate' ), esc_html( (string) $run->attempts() ) );

		if ( null !== $run->parentRunId() ) {
			$this->renderMetaRow(
				__( 'Re-run of', 'workflow-automate' ),
				sprintf(
					'<a href="%1$s">#%2$d</a>',
					esc_url( $this->detailUrl( $run->parentRunId() ) ),
					$run->parentRunId()
				)
			);
		}

		$this->renderMetaRow(
			__( 'Started', 'workflow-automate' ),
			$run->startedAt() ? esc_html( RunTimestamp::format( $run->startedAt(), $this->settings->displayTimestampsInUtc() ) ) : esc_html__( 'Not started yet', 'workflow-automate' )
		);
		$this->renderMetaRow(
			__( 'Finished', 'workflow-automate' ),
			$run->finishedAt() ? esc_html( RunTimestamp::format( $run->finishedAt(), $this->settings->displayTimestampsInUtc() ) ) : esc_html__( 'Not finished yet', 'workflow-automate' )
		);
		$this->renderMetaRow( __( 'Duration', 'workflow-automate' ), esc_html( RunDuration::forRun( $run ) ) );

		if ( in_array( $run->status(), self::RERUNNABLE_STATUSES, true ) ) {
			$this->renderMetaRow( __( 'Actions', 'workflow-automate' ), $this->rerunForm( $run->id() ) );
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
		printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), $value );
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
			'<details class="wfa-run-details-block"><summary>%1$s</summary><pre>%2$s</pre></details>',
			esc_html__( 'Trigger payload', 'workflow-automate' ),
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

		echo '<h2>' . esc_html__( 'Node log', 'workflow-automate' ) . '</h2>';

		if ( array() === $logs ) {
			echo '<p>' . esc_html__( 'This run has no node log entries yet.', 'workflow-automate' ) . '</p>';

			return;
		}

		echo '<table class="widefat fixed striped wfa-run-logs"><thead><tr>';
		echo '<th>' . esc_html__( 'Node', 'workflow-automate' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'workflow-automate' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'workflow-automate' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'workflow-automate' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'workflow-automate' ) . '</th>';
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
		printf( '<td>%s</td>', $this->logStatusBadge( $log->status() ) );
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

		return __( '(unknown node)', 'workflow-automate' );
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
			WorkflowRunLog::STATUS_SUCCESS => __( 'Success', 'workflow-automate' ),
			WorkflowRunLog::STATUS_ERROR => __( 'Error', 'workflow-automate' ),
			WorkflowRunLog::STATUS_SKIPPED => __( 'Skipped', 'workflow-automate' ),
		);

		$label = $labels[ $status ] ?? __( 'Unknown', 'workflow-automate' );
		$slug = array_key_exists( $status, $labels ) ? $status : 'unknown';

		return sprintf(
			'<span class="wfa-status-badge wfa-status-badge--%1$s">%2$s</span>',
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

		echo '<details class="wfa-run-details-block"><summary>' . esc_html__( 'View', 'workflow-automate' ) . '</summary>';

		if ( null !== $log->input() ) {
			echo '<p><strong>' . esc_html__( 'Input', 'workflow-automate' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) wp_json_encode( $log->input(), JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		if ( null !== $log->output() ) {
			echo '<p><strong>' . esc_html__( 'Output', 'workflow-automate' ) . '</strong></p>';
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
		$nonce_field = wp_nonce_field( 'wfa_run_action_rerun_' . $run_id, '_wpnonce', true, false );

		return sprintf(
			'<form method="post" action="%1$s">'
				. '<input type="hidden" name="action" value="wfa_run_action" />'
				. '<input type="hidden" name="op" value="rerun" />'
				. '<input type="hidden" name="run_id" value="%2$d" />'
				. '%3$s'
				. '<button type="submit" class="button button-secondary">%4$s</button>'
				. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$run_id,
			$nonce_field,
			esc_html__( 'Re-run this workflow', 'workflow-automate' )
		);
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?wfa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'rerun_started' => array(
				'message' => __( 'Re-run started below.', 'workflow-automate' ),
				'type' => 'success',
			),
			'rerun_failed' => array(
				'message' => __( 'That run could not be re-run.', 'workflow-automate' ),
				'type' => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key = isset( $_GET['wfa_notice'] ) ? sanitize_key( wp_unslash( $_GET['wfa_notice'] ) ) : '';
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
