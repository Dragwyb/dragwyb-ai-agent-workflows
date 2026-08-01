<?php
/**
 * Runs admin list table.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Admin\Pages\RunDetailPage;
use AIAWA\Plugin\Admin\Pages\RunsPage;
use AIAWA\Plugin\Domain\WorkflowRun;
use AIAWA\Plugin\Persistence\WorkflowRepository;
use AIAWA\Plugin\Persistence\WorkflowRunRepository;
use AIAWA\Plugin\Service\SettingsService;
use WP_List_Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the top-level "Runs" execution-history screen, across every
 * workflow — the single-workflow "Runs" row action on WorkflowsListTable
 * links here with a `?workflow_id=` filter rather than to a separate
 * per-workflow screen, so there is only one list implementation to
 * maintain. Same `WP_List_Table` + POST-form-row-action conventions as
 * WorkflowsListTable (see that class's own docblock for the CSRF reasoning).
 */
class RunsListTable extends WP_List_Table {

	private const PER_PAGE = 20;

	/**
	 * Statuses that make sense to re-run. Deliberately excludes `queued`
	 * and `running`: re-running something that hasn't finished yet (or
	 * hasn't even started) isn't a meaningful action.
	 */
	private const RERUNNABLE_STATUSES = array(
		WorkflowRun::STATUS_SUCCESS,
		WorkflowRun::STATUS_FAILED,
		WorkflowRun::STATUS_PARTIAL,
	);

	private WorkflowRunRepository $runs;

	private WorkflowRepository $workflows;

	private SettingsService $settings;

	private RowActionForms $rowForms;

	/**
	 * Workflow titles for the current page of rows, keyed by workflow id.
	 * Populated once in prepare_items() via a single batch lookup, rather
	 * than one WorkflowRepository::find() per row.
	 *
	 * @var array<int, string>
	 */
	private array $workflowTitles = array();

	/**
	 * @var array<int, string> Workflow id => title for the filter dropdown.
	 */
	private array $workflowFilterOptions = array();

	public function __construct( WorkflowRunRepository $runs, WorkflowRepository $workflows, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'run',
				'plural'   => 'runs',
				'ajax'     => false,
			)
		);

		$this->runs      = $runs;
		$this->workflows = $workflows;
		$this->settings  = $settings;
		$this->rowForms  = new RowActionForms();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'id'         => __( 'Run', 'workflow-automate' ),
			'workflow'   => __( 'Workflow', 'workflow-automate' ),
			'status'     => __( 'Status', 'workflow-automate' ),
			'attempt'    => __( 'Attempt', 'workflow-automate' ),
			'started_at' => __( 'Started', 'workflow-automate' ),
			'duration'   => __( 'Duration', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param WorkflowRun $item Row.
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="runs[]" value="%d" />',
			$item->id()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$workflow_id = $this->currentWorkflowFilter();
		$view        = $this->currentView();

		$args = array(
			'page'     => $paged,
			'per_page' => self::PER_PAGE,
		);

		if ( $workflow_id > 0 ) {
			$args['workflow_id'] = $workflow_id;
		}

		if ( in_array( $view, WorkflowRun::VALID_STATUSES, true ) ) {
			$args['status'] = $view;
		}

		$result = $this->runs->paginateAll( $args );

		$this->items                 = $result['items'];
		$this->workflowTitles        = $this->resolveWorkflowTitles( $this->items );
		$this->workflowFilterOptions = $this->loadWorkflowFilterOptions();

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		esc_html_e( 'No runs match this filter.', 'workflow-automate' );
	}

	/**
	 * @param WorkflowRun[] $runs Current page of runs.
	 *
	 * @return array<int, string>
	 */
	private function resolveWorkflowTitles( array $runs ): array {
		$workflow_ids = array_map(
			static function ( WorkflowRun $run ): int {
				return $run->workflowId();
			},
			$runs
		);

		$titles = array();

		foreach ( $this->workflows->findByIds( $workflow_ids ) as $id => $workflow ) {
			$titles[ $id ] = $workflow->title();
		}

		return $titles;
	}

	/**
	 * @return array<int, string>
	 */
	private function loadWorkflowFilterOptions(): array {
		$result = $this->workflows->paginate(
			array(
				'page'     => 1,
				'per_page' => 100,
			)
		);

		$options = array();

		foreach ( $result['items'] as $workflow ) {
			$options[ $workflow->id() ] = $workflow->title();
		}

		return $options;
	}

	/**
	 * Filter dropdown definitions for the GET filter form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function filterFields(): array {
		$options = array(
			'0' => __( 'All workflows', 'workflow-automate' ),
		);

		foreach ( $this->workflowFilterOptions as $id => $title ) {
			$options[ (string) $id ] = $title;
		}

		return array(
			array(
				'name'    => 'workflow_id',
				'label'   => __( 'Filter by workflow', 'workflow-automate' ),
				'value'   => (string) $this->currentWorkflowFilter(),
				'options' => $options,
			),
		);
	}

	/**
	 * GET filter values to preserve on bulk POST redirects.
	 *
	 * @return array<string, scalar>
	 */
	public function preservedFilters(): array {
		return array(
			'workflow_id' => $this->currentWorkflowFilter(),
			'status'      => 'all' === $this->currentView() ? '' : $this->currentView(),
		);
	}

	/**
	 * @return void
	 */
	public function renderRowActionForms(): void {
		$this->rowForms->render();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->currentView();
		$labels  = array(
			'all'                       => __( 'All', 'workflow-automate' ),
			WorkflowRun::STATUS_QUEUED  => __( 'Queued', 'workflow-automate' ),
			WorkflowRun::STATUS_RUNNING => __( 'Running', 'workflow-automate' ),
			WorkflowRun::STATUS_SUCCESS => __( 'Success', 'workflow-automate' ),
			WorkflowRun::STATUS_FAILED  => __( 'Failed', 'workflow-automate' ),
			WorkflowRun::STATUS_PARTIAL => __( 'Partial', 'workflow-automate' ),
		);

		$views = array();

		foreach ( $labels as $slug => $label ) {
			$views[ $slug ] = $this->viewLink( $slug, $label, $current );
		}

		return $views;
	}

	/**
	 * @param WorkflowRun $item Row.
	 *
	 * @return string
	 */
	protected function column_id( $item ) {
		$view_url = $this->detailUrl( $item->id() );

		$actions = array(
			'view' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $view_url ), esc_html__( 'View', 'workflow-automate' ) ),
		);

		if ( in_array( $item->status(), self::RERUNNABLE_STATUSES, true ) ) {
			$actions['rerun'] = $this->actionForm( 'rerun', $item->id(), __( 'Re-run', 'workflow-automate' ) );
		}

		$actions['delete'] = $this->actionForm(
			'delete',
			$item->id(),
			__( 'Delete', 'workflow-automate' ),
			__( 'Delete this run permanently? This cannot be undone.', 'workflow-automate' )
		);

		$label = sprintf(
			'<a href="%1$s"><strong>#%2$d</strong></a>',
			esc_url( $view_url ),
			$item->id()
		);

		return $label . $this->row_actions( $actions );
	}

	/**
	 * @param WorkflowRun $item        Row.
	 * @param string      $column_name Column being rendered.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'workflow':
				return $this->workflowCell( $item );

			case 'status':
				return RunStatusBadge::render( $item->status() );

			case 'attempt':
				return esc_html( (string) $item->attempts() );

			case 'started_at':
				return $item->startedAt()
					? esc_html( RunTimestamp::format( $item->startedAt(), $this->settings->displayTimestampsInUtc() ) )
					: esc_html__( 'Not started yet', 'workflow-automate' );

			case 'duration':
				return esc_html( RunDuration::forRun( $item ) );

			default:
				return '';
		}
	}

	/**
	 * @param WorkflowRun $item Row.
	 *
	 * @return string
	 */
	private function workflowCell( WorkflowRun $item ): string {
		if ( ! isset( $this->workflowTitles[ $item->workflowId() ] ) ) {
			return esc_html__( '(deleted workflow)', 'workflow-automate' );
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->workflowFilterUrl( $item->workflowId() ) ),
			esc_html( $this->workflowTitles[ $item->workflowId() ] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'aiawa-runs-table' );
	}

	/**
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function detailUrl( int $run_id ): string {
		return add_query_arg(
			array(
				'page'   => RunDetailPage::SLUG,
				'run_id' => $run_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param int $workflow_id Workflow id.
	 *
	 * @return string
	 */
	private function workflowFilterUrl( int $workflow_id ): string {
		return add_query_arg(
			array(
				'page'        => RunsPage::SLUG,
				'workflow_id' => $workflow_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param string      $op      One of 'rerun', 'delete'.
	 * @param int         $run_id  Run id.
	 * @param string      $label   Visible button label.
	 * @param string|null $confirm Optional browser confirm message.
	 *
	 * @return string
	 */
	private function actionForm( string $op, int $run_id, string $label, ?string $confirm = null ): string {
		$form_id     = 'aiawa-run-action-' . $op . '-' . $run_id;
		$nonce_field = wp_nonce_field( 'aiawa_run_action_' . $op . '_' . $run_id, '_wpnonce', true, false );

		$form_markup = sprintf(
			'<form id="%1$s" method="post" action="%2$s" class="aiawa-detached-row-action-form">'
				. '<input type="hidden" name="action" value="aiawa_run_action" />'
				. '<input type="hidden" name="op" value="%3$s" />'
				. '<input type="hidden" name="run_id" value="%4$d" />'
				. '%5$s'
				. '</form>',
			esc_attr( $form_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $op ),
			$run_id,
			$nonce_field
		);

		return $this->rowForms->registerButton( $form_id, $form_markup, $label, 'aiawa-row-action-button', $confirm );
	}

	/**
	 * @param string $view    View slug (a WorkflowRun status, or "all").
	 * @param string $label   Visible label.
	 * @param string $current Currently active view slug.
	 *
	 * @return string
	 */
	private function viewLink( string $view, string $label, string $current ): string {
		$args = array( 'page' => RunsPage::SLUG );

		if ( 'all' !== $view ) {
			$args['status'] = $view;
		}

		$workflow_id = $this->currentWorkflowFilter();
		if ( $workflow_id > 0 ) {
			$args['workflow_id'] = $workflow_id;
		}

		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		if ( 'all' === $view ) {
			$url = remove_query_arg( 'status', $url );
		}

		$class = $view === $current ? ' class="current"' : '';

		return sprintf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( $url ), $class, esc_html( $label ) );
	}

	/**
	 * The current "status" view, from the read-only `?status=` query arg.
	 *
	 * @return string
	 */
	private function currentView(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter, not a state change.
		$requested = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		return in_array( $requested, WorkflowRun::VALID_STATUSES, true ) ? $requested : 'all';
	}

	/**
	 * The current "workflow_id" filter, from the read-only `?workflow_id=`
	 * query arg (set by WorkflowsListTable's "Runs" row action).
	 *
	 * @return int
	 */
	private function currentWorkflowFilter(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter, not a state change.
		return isset( $_GET['workflow_id'] ) ? absint( wp_unslash( $_GET['workflow_id'] ) ) : 0;
	}
}
