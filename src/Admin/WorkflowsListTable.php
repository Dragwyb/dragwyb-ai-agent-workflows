<?php
/**
 * Workflows admin list table.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Admin\Pages\BuilderPage;
use AIAWA\Plugin\Admin\Pages\RunsPage;
use AIAWA\Plugin\Domain\Workflow;
use AIAWA\Plugin\Service\SettingsService;
use AIAWA\Plugin\Service\WorkflowService;
use WP_List_Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the Workflows screen using WordPress's own `WP_List_Table` base
 * class — idiomatic wp-admin UI (Section 7: "consistent with current
 * WordPress admin design conventions"), not a bespoke table implementation.
 *
 * Row-level state changes (trash/restore/delete) are deliberately rendered
 * as small POST forms rather than plain `<a href>` links: WordPress core
 * itself uses nonce-protected GET links for this exact pattern, but this
 * project's own security requirements (Section 5) call for state changes to
 * never happen on a GET request, so this table follows the stricter rule.
 */
class WorkflowsListTable extends WP_List_Table {

	private const PER_PAGE = 20;

	private const VIEWS = array( 'all', 'draft', 'active', 'paused', 'trash' );

	private WorkflowService $workflows;

	private SettingsService $settings;

	private RowActionForms $rowForms;

	public function __construct( WorkflowService $workflows, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'workflow',
				'plural'   => 'workflows',
				'ajax'     => false,
			)
		);

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
			'title'      => __( 'Title', 'workflow-automate' ),
			'status'     => __( 'Status', 'workflow-automate' ),
			'run_count'  => __( 'Runs', 'workflow-automate' ),
			'updated_at' => __( 'Last Updated', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_bulk_actions() {
		if ( 'trash' === $this->currentView() ) {
			return array(
				'restore' => __( 'Restore', 'workflow-automate' ),
				'delete'  => __( 'Delete Permanently', 'workflow-automate' ),
			);
		}

		return array(
			'trash' => __( 'Move to Trash', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Workflow $item Row.
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="workflows[]" value="%d" />',
			$item->id()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$view = $this->currentView();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$args = array(
			'page'     => $paged,
			'per_page' => self::PER_PAGE,
		);

		if ( 'trash' === $view ) {
			$args['only_trashed'] = true;
		} elseif ( in_array( $view, array( 'draft', 'active', 'paused' ), true ) ) {
			$args['status'] = $this->statusFromSlug( $view );
		}

		$search = $this->currentSearch();
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$result = $this->workflows->list( $args );

		$this->items = $result['items'];

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
		$view = $this->currentView();

		if ( 'trash' === $view ) {
			esc_html_e( 'Trash is empty.', 'workflow-automate' );
			return;
		}

		if ( 'all' !== $view ) {
			esc_html_e( 'No workflows match this filter.', 'workflow-automate' );
			return;
		}

		esc_html_e( 'No workflows yet.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->currentView();
		$views   = array();

		foreach ( self::VIEWS as $view ) {
			$views[ $view ] = $this->viewLink( $view, $current );
		}

		return $views;
	}

	/**
	 * @param Workflow $item Row.
	 *
	 * @return string
	 */
	protected function column_title( $item ) {
		if ( $item->isTrashed() ) {
			$title   = '<strong>' . esc_html( $item->title() ) . '</strong>';
			$actions = array(
				'restore' => $this->actionForm( 'restore', $item->id(), __( 'Restore', 'workflow-automate' ) ),
				'delete'  => $this->actionForm( 'delete', $item->id(), __( 'Delete Permanently', 'workflow-automate' ) ),
			);
		} else {
			$edit_url = $this->editUrl( $item->id() );
			$title    = sprintf(
				'<strong><a href="%1$s">%2$s</a></strong>',
				esc_url( $edit_url ),
				esc_html( $item->title() )
			);
			$actions  = array(
				'edit'   => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'workflow-automate' ) ),
				'runs'   => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $this->runsUrl( $item->id() ) ), esc_html__( 'Runs', 'workflow-automate' ) ),
				'export' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $this->exportUrl( $item->id() ) ), esc_html__( 'Export', 'workflow-automate' ) ),
			);

			if ( Workflow::STATUS_ACTIVE === $item->status() ) {
				$actions['pause'] = $this->actionForm( 'pause', $item->id(), __( 'Pause', 'workflow-automate' ) );
			} else {
				$actions['activate'] = $this->actionForm( 'activate', $item->id(), __( 'Activate', 'workflow-automate' ) );
			}

			$actions['trash'] = $this->actionForm( 'trash', $item->id(), __( 'Trash', 'workflow-automate' ) );
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Search filter field for the GET filter form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function filterFields(): array {
		return array(
			array(
				'name'        => 's',
				'type'        => 'search',
				'label'       => __( 'Search workflows', 'workflow-automate' ),
				'placeholder' => __( 'Search by title…', 'workflow-automate' ),
				'value'       => $this->currentSearch(),
			),
		);
	}

	/**
	 * @return array<string, scalar>
	 */
	public function preservedFilters(): array {
		$view = $this->currentView();

		return array(
			'status' => 'all' === $view ? '' : $view,
			's'      => $this->currentSearch(),
		);
	}

	/**
	 * @return void
	 */
	public function renderRowActionForms(): void {
		$this->rowForms->render();
	}

	/**
	 * @param int $id Workflow id.
	 *
	 * @return string
	 */
	private function editUrl( int $id ): string {
		return add_query_arg(
			array(
				'page'     => BuilderPage::SLUG,
				'workflow' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Links to the Runs history screen (roadmap item 9), pre-filtered to
	 * this workflow.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return string
	 */
	private function runsUrl( int $id ): string {
		return add_query_arg(
			array(
				'page'        => RunsPage::SLUG,
				'workflow_id' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Nonced download URL for a portable JSON export of this workflow.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return string
	 */
	private function exportUrl( int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'aiawa_workflow_export',
					'workflow_id' => $id,
				),
				admin_url( 'admin-post.php' )
			),
			'aiawa_workflow_export_' . $id
		);
	}

	/**
	 * @param Workflow $item        Row.
	 * @param string   $column_name Column being rendered.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'status':
				return esc_html( $this->statusLabel( $item->status() ) );

			case 'run_count':
				return esc_html( (string) $item->runCount() );

			case 'updated_at':
				return esc_html( RunTimestamp::format( $item->updatedAt(), $this->settings->displayTimestampsInUtc() ) );

			default:
				return '';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'aiawa-workflows-table' );
	}

	/**
	 * Renders a small standalone POST form styled as a row-action link.
	 *
	 * @param string $op    One of 'trash', 'restore', 'delete', 'activate', 'pause'.
	 * @param int    $id    Workflow id.
	 * @param string $label Visible button label.
	 *
	 * @return string
	 */
	private function actionForm( string $op, int $id, string $label ): string {
		$form_id     = 'aiawa-workflow-action-' . $op . '-' . $id;
		$nonce_field = wp_nonce_field( 'aiawa_workflow_action_' . $op . '_' . $id, '_wpnonce', true, false );

		$form_markup = sprintf(
			'<form id="%1$s" method="post" action="%2$s" class="aiawa-detached-row-action-form">'
				. '<input type="hidden" name="action" value="aiawa_workflow_action" />'
				. '<input type="hidden" name="op" value="%3$s" />'
				. '<input type="hidden" name="workflow_id" value="%4$d" />'
				. '%5$s'
				. '</form>',
			esc_attr( $form_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $op ),
			$id,
			$nonce_field
		);

		return $this->rowForms->registerButton( $form_id, $form_markup, $label );
	}

	/**
	 * @param string $view    View slug (see self::VIEWS).
	 * @param string $current Currently active view slug.
	 *
	 * @return string
	 */
	private function viewLink( string $view, string $current ): string {
		$url = 'all' === $view
			? remove_query_arg( 'status' )
			: add_query_arg( 'status', $view );

		$class = $view === $current ? ' class="current"' : '';

		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$class,
			esc_html( $this->viewLabel( $view ) )
		);
	}

	private function viewLabel( string $view ): string {
		switch ( $view ) {
			case 'draft':
				return __( 'Draft', 'workflow-automate' );
			case 'active':
				return __( 'Active', 'workflow-automate' );
			case 'paused':
				return __( 'Paused', 'workflow-automate' );
			case 'trash':
				return __( 'Trash', 'workflow-automate' );
			default:
				return __( 'All', 'workflow-automate' );
		}
	}

	private function statusLabel( int $status ): string {
		switch ( $status ) {
			case Workflow::STATUS_ACTIVE:
				return __( 'Active', 'workflow-automate' );
			case Workflow::STATUS_PAUSED:
				return __( 'Paused', 'workflow-automate' );
			default:
				return __( 'Draft', 'workflow-automate' );
		}
	}

	private function statusFromSlug( string $slug ): int {
		switch ( $slug ) {
			case 'active':
				return Workflow::STATUS_ACTIVE;
			case 'paused':
				return Workflow::STATUS_PAUSED;
			default:
				return Workflow::STATUS_DRAFT;
		}
	}

	/**
	 * The current "status" view, from the read-only `?status=` query arg.
	 *
	 * @return string
	 */
	private function currentView(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter, not a state change.
		$requested = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		return in_array( $requested, self::VIEWS, true ) ? $requested : 'all';
	}

	/**
	 * @return string
	 */
	private function currentSearch(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search filter.
		return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	}
}
