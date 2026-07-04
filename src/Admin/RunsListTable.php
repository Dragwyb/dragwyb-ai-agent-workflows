<?php
/**
 * Runs admin list table.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use WorkflowAutomate\Plugin\Admin\Pages\RunDetailPage;
use WorkflowAutomate\Plugin\Admin\Pages\RunsPage;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Service\SettingsService;
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

	/**
	 * Workflow titles for the current page of rows, keyed by workflow id.
	 * Populated once in prepare_items() via a single batch lookup, rather
	 * than one WorkflowRepository::find() per row.
	 *
	 * @var array<int, string>
	 */
	private array $workflowTitles = array();

	public function __construct( WorkflowRunRepository $runs, WorkflowRepository $workflows, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'run',
				'plural' => 'runs',
				'ajax' => false,
			)
		);

		$this->runs = $runs;
		$this->workflows = $workflows;
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'id' => __( 'Run', 'workflow-automate' ),
			'workflow' => __( 'Workflow', 'workflow-automate' ),
			'status' => __( 'Status', 'workflow-automate' ),
			'attempt' => __( 'Attempt', 'workflow-automate' ),
			'started_at' => __( 'Started', 'workflow-automate' ),
			'duration' => __( 'Duration', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$workflow_id = $this->currentWorkflowFilter();
		$view = $this->currentView();

		$args = array(
			'page' => $paged,
			'per_page' => self::PER_PAGE,
		);

		if ( $workflow_id > 0 ) {
			$args['workflow_id'] = $workflow_id;
		}

		if ( in_array( $view, WorkflowRun::VALID_STATUSES, true ) ) {
			$args['status'] = $view;
		}

		$result = $this->runs->paginateAll( $args );

		$this->items = $result['items'];
		$this->workflowTitles = $this->resolveWorkflowTitles( $this->items );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page' => self::PER_PAGE,
			)
		);
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
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->currentView();
		$labels = array(
			'all' => __( 'All', 'workflow-automate' ),
			WorkflowRun::STATUS_QUEUED => __( 'Queued', 'workflow-automate' ),
			WorkflowRun::STATUS_RUNNING => __( 'Running', 'workflow-automate' ),
			WorkflowRun::STATUS_SUCCESS => __( 'Success', 'workflow-automate' ),
			WorkflowRun::STATUS_FAILED => __( 'Failed', 'workflow-automate' ),
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
			$actions['rerun'] = $this->rerunForm( $item->id() );
		}

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
		return array( 'widefat', 'fixed', 'striped', 'wfa-runs-table' );
	}

	/**
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function detailUrl( int $run_id ): string {
		return add_query_arg(
			array(
				'page' => RunDetailPage::SLUG,
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
				'page' => RunsPage::SLUG,
				'workflow_id' => $workflow_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Renders a small standalone POST form styled as a row-action link,
	 * matching WorkflowsListTable::actionForm()'s CSRF reasoning.
	 *
	 * @param int $run_id Run id.
	 *
	 * @return string
	 */
	private function rerunForm( int $run_id ): string {
		$nonce_field = wp_nonce_field( 'wfa_run_action_rerun_' . $run_id, '_wpnonce', true, false );

		return sprintf(
			'<form method="post" action="%1$s" class="wfa-row-action-form">'
				. '<input type="hidden" name="action" value="wfa_run_action" />'
				. '<input type="hidden" name="op" value="rerun" />'
				. '<input type="hidden" name="run_id" value="%2$d" />'
				. '%3$s'
				. '<button type="submit" class="wfa-row-action-button">%4$s</button>'
				. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$run_id,
			$nonce_field,
			esc_html__( 'Re-run', 'workflow-automate' )
		);
	}

	/**
	 * @param string $view    View slug (a WorkflowRun status, or "all").
	 * @param string $label   Visible label.
	 * @param string $current Currently active view slug.
	 *
	 * @return string
	 */
	private function viewLink( string $view, string $label, string $current ): string {
		$url = 'all' === $view
			? remove_query_arg( 'status' )
			: add_query_arg( 'status', $view );

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
