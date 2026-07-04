<?php
/**
 * Workflows admin list table.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use WorkflowAutomate\Plugin\Admin\Pages\BuilderPage;
use WorkflowAutomate\Plugin\Admin\Pages\RunsPage;
use WorkflowAutomate\Plugin\Domain\Workflow;
use WorkflowAutomate\Plugin\Service\SettingsService;
use WorkflowAutomate\Plugin\Service\WorkflowService;
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

	public function __construct( WorkflowService $workflows, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'workflow',
				'plural' => 'workflows',
				'ajax' => false,
			)
		);

		$this->workflows = $workflows;
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'title' => __( 'Title', 'workflow-automate' ),
			'status' => __( 'Status', 'workflow-automate' ),
			'run_count' => __( 'Runs', 'workflow-automate' ),
			'updated_at' => __( 'Last Updated', 'workflow-automate' ),
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
			'page' => $paged,
			'per_page' => self::PER_PAGE,
		);

		if ( 'trash' === $view ) {
			$args['only_trashed'] = true;
		} elseif ( in_array( $view, array( 'draft', 'active', 'paused' ), true ) ) {
			$args['status'] = $this->statusFromSlug( $view );
		}

		$result = $this->workflows->list( $args );

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page' => self::PER_PAGE,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$current = $this->currentView();
		$views = array();

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
			$title = '<strong>' . esc_html( $item->title() ) . '</strong>';
			$actions = array(
				'restore' => $this->actionForm( 'restore', $item->id(), __( 'Restore', 'workflow-automate' ) ),
				'delete' => $this->actionForm( 'delete', $item->id(), __( 'Delete Permanently', 'workflow-automate' ) ),
			);
		} else {
			$edit_url = $this->editUrl( $item->id() );
			$title = sprintf(
				'<strong><a href="%1$s">%2$s</a></strong>',
				esc_url( $edit_url ),
				esc_html( $item->title() )
			);
			$actions = array(
				'edit' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'workflow-automate' ) ),
				'runs' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $this->runsUrl( $item->id() ) ), esc_html__( 'Runs', 'workflow-automate' ) ),
				'trash' => $this->actionForm( 'trash', $item->id(), __( 'Trash', 'workflow-automate' ) ),
			);
		}

		return $title . $this->row_actions( $actions );
	}

	/**
	 * @param int $id Workflow id.
	 *
	 * @return string
	 */
	private function editUrl( int $id ): string {
		return add_query_arg(
			array(
				'page' => BuilderPage::SLUG,
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
				'page' => RunsPage::SLUG,
				'workflow_id' => $id,
			),
			admin_url( 'admin.php' )
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
		return array( 'widefat', 'fixed', 'striped', 'wfa-workflows-table' );
	}

	/**
	 * Renders a small standalone POST form styled as a row-action link.
	 *
	 * @param string $op    One of 'trash', 'restore', 'delete'.
	 * @param int    $id    Workflow id.
	 * @param string $label Visible button label.
	 *
	 * @return string
	 */
	private function actionForm( string $op, int $id, string $label ): string {
		$nonce_field = wp_nonce_field( 'wfa_workflow_action_' . $op . '_' . $id, '_wpnonce', true, false );

		return sprintf(
			'<form method="post" action="%1$s" class="wfa-row-action-form">'
				. '<input type="hidden" name="action" value="wfa_workflow_action" />'
				. '<input type="hidden" name="op" value="%2$s" />'
				. '<input type="hidden" name="workflow_id" value="%3$d" />'
				. '%4$s'
				. '<button type="submit" class="wfa-row-action-button">%5$s</button>'
				. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $op ),
			$id,
			$nonce_field,
			esc_html( $label )
		);
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
}
