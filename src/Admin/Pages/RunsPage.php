<?php
/**
 * Runs (execution history) admin page.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Admin\Pages;

use DRAGAIW\Plugin\Admin\AdminPage;
use DRAGAIW\Plugin\Admin\EmptyState;
use DRAGAIW\Plugin\Admin\ListTableUi;
use DRAGAIW\Plugin\Admin\RunActionsController;
use DRAGAIW\Plugin\Admin\RunsListTable;
use DRAGAIW\Plugin\Core\Capabilities;
use DRAGAIW\Plugin\Persistence\WorkflowRepository;
use DRAGAIW\Plugin\Persistence\WorkflowRunRepository;
use DRAGAIW\Plugin\Service\SettingsService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level "Runs" screen (roadmap item 9): a paginated, status-filterable
 * list of every run across every workflow, optionally narrowed to a single
 * workflow via the `?workflow_id=` query arg set by WorkflowsListTable's
 * "Runs" row action. Reading run history needs no service-layer
 * orchestration beyond what the repositories already provide, so — unlike
 * WorkflowsPage — this reads directly from WorkflowRunRepository /
 * WorkflowRepository rather than through a dedicated service.
 */
class RunsPage implements AdminPage {

	public const SLUG = 'dragaiw-runs';

	private WorkflowRunRepository $runs;

	private WorkflowRepository $workflows;

	private SettingsService $settings;

	private RunActionsController $runActions;

	public function __construct( WorkflowRunRepository $runs, WorkflowRepository $workflows, SettingsService $settings, RunActionsController $runActions ) {
		$this->runs       = $runs;
		$this->workflows  = $workflows;
		$this->settings   = $settings;
		$this->runActions = $runActions;
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
		return __( 'Runs', 'dragwyb-ai-agent-workflows' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Runs', 'dragwyb-ai-agent-workflows' );
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
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueueAssets(): void {
		wp_enqueue_style(
			'dragaiw-admin',
			DRAGAIW_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			DRAGAIW_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-ai-agent-workflows' ) );
		}

		$table = new RunsListTable( $this->runs, $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragaiw-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->renderWorkflowFilterNotice();
		$this->renderNotice();
		$this->renderFilters( $table );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$has_filters = ( isset( $_GET['workflow_id'] ) && absint( wp_unslash( $_GET['workflow_id'] ) ) > 0 ) || ( isset( $_GET['status'] ) && '' !== sanitize_key( wp_unslash( $_GET['status'] ) ) );

		if ( ! $table->has_items() && ! $has_filters ) {
			EmptyState::render(
				__( 'No runs yet', 'dragwyb-ai-agent-workflows' ),
				__( 'Runs appear here when a workflow executes — automatically from a trigger or webhook, or when you use Run now in the editor.', 'dragwyb-ai-agent-workflows' ),
				array(),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . WorkflowsPage::SLUG ),
						'label'   => __( 'Go to Workflows', 'dragwyb-ai-agent-workflows' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		$table->views();

		ListTableUi::openBulkForm( $this->slug(), 'dragaiw_run_bulk_action', 'dragaiw_run_bulk' );
		ListTableUi::renderPreservedFilters( $table->preservedFilters() );
		$table->display();
		ListTableUi::closeBulkForm();

		$table->renderRowActionForms();

		echo '</div>';
	}

	/**
	 * @param RunsListTable $table Prepared list table.
	 *
	 * @return void
	 */
	private function renderFilters( RunsListTable $table ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter form.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		echo '<form method="get" class="dragaiw-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );

		if ( '' !== $status ) {
			printf( '<input type="hidden" name="status" value="%s" />', esc_attr( $status ) );
		}

		if($table->has_items()) {
			ListTableUi::renderFilterBar(
				'top',
				$table->filterFields()
			);
		}
		echo '</form>';
	}

	/**
	 * When the list is filtered to a single workflow (via `?workflow_id=`),
	 * shows which one and a link to clear the filter — the same purpose
	 * core's own post-list "all dates"/category filters serve.
	 *
	 * @return void
	 */
	private function renderWorkflowFilterNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter, not a state change.
		$workflow_id = isset( $_GET['workflow_id'] ) ? absint( wp_unslash( $_GET['workflow_id'] ) ) : 0;

		if ( $workflow_id <= 0 ) {
			return;
		}

		$workflow = $this->workflows->find( $workflow_id, true );
		$name     = $workflow ? $workflow->title() : __( '(deleted workflow)', 'dragwyb-ai-agent-workflows' );

		printf(
			'<p class="dragaiw-runs-filter-notice">%1$s <a href="%2$s">%3$s</a></p>',
			sprintf(
				/* translators: %s: workflow title. */
				esc_html__( 'Showing runs for: %s', 'dragwyb-ai-agent-workflows' ),
				'<strong>' . esc_html( $name ) . '</strong>'
			),
			esc_url( remove_query_arg( 'workflow_id' ) ),
			esc_html__( 'Clear filter', 'dragwyb-ai-agent-workflows' )
		);
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?dragaiw_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'deleted'       => array(
				'message' => __( 'Run deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'bulk_deleted'  => array(
				'message' => __( 'Selected runs deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'delete_failed' => array(
				'message' => __( 'That run could not be deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
			'action_failed' => array(
				'message' => __( 'That run action could not be completed.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
			'rerun_failed'  => array(
				'message' => __( 'That run could not be re-run.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['dragaiw_notice'] ) ? sanitize_key( wp_unslash( $_GET['dragaiw_notice'] ) ) : '';
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
