<?php
/**
 * Connections admin list table.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Admin\Pages\ConnectionFormPage;
use AIAWA\Plugin\Domain\Connection;
use AIAWA\Plugin\Service\ConnectionAuthTypes;
use AIAWA\Plugin\Service\ConnectionService;
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
 * Renders the Connections screen (roadmap item 11) using `WP_List_Table`,
 * the same idiomatic-wp-admin approach as WorkflowsListTable/RunsListTable.
 * Never renders a decrypted credential value — only label/integration/auth
 * type/status/created — matching CURSOR_INSTRUCTIONS.md's masking
 * requirement at the strongest possible level: the list view shows no
 * secret material at all, masked or otherwise.
 */
class ConnectionsListTable extends WP_List_Table {

	private const PER_PAGE = 20;

	private ConnectionService $connections;

	private SettingsService $settings;

	private RowActionForms $rowForms;

	public function __construct( ConnectionService $connections, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'connection',
				'plural'   => 'connections',
				'ajax'     => false,
			)
		);

		$this->connections = $connections;
		$this->settings    = $settings;
		$this->rowForms    = new RowActionForms();
	}

	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'label'            => __( 'Label', 'ai-agent-workflow-automation' ),
			'integration_slug' => __( 'Integration', 'ai-agent-workflow-automation' ),
			'auth_type'        => __( 'Authentication', 'ai-agent-workflow-automation' ),
			'status'           => __( 'Status', 'ai-agent-workflow-automation' ),
			'created_at'       => __( 'Created', 'ai-agent-workflow-automation' ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'ai-agent-workflow-automation' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="connections[]" value="%d" />',
			$item->id()
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$result = $this->connections->list(
			array(
				'page'             => $paged,
				'per_page'         => self::PER_PAGE,
				'integration_slug' => $this->currentIntegrationFilter(),
			)
		);

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
		esc_html_e( 'No connections yet.', 'ai-agent-workflow-automation' );
	}

	/**
	 * @param Connection $item Row.
	 *
	 * @return string
	 */
	protected function column_label( $item ) {
		$edit_url = $this->editUrl( $item->id() );

		$title = sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>',
			esc_url( $edit_url ),
			esc_html( $item->label() )
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'ai-agent-workflow-automation' ) ),
			'delete' => $this->deleteForm( $item->id() ),
		);

		return $title . $this->row_actions( $actions );
	}

	public function filterFields(): array {
		return array(
			array(
				'name'        => 'integration_slug',
				'type'        => 'search',
				'label'       => __( 'Filter by integration', 'ai-agent-workflow-automation' ),
				'placeholder' => __( 'e.g. gemini, openai', 'ai-agent-workflow-automation' ),
				'value'       => $this->currentIntegrationFilter(),
			),
		);
	}

	public function preservedFilters(): array {
		return array(
			'integration_slug' => $this->currentIntegrationFilter(),
		);
	}

	public function renderRowActionForms(): void {
		$this->rowForms->render();
	}

	/**
	 * @param int $id Connection id.
	 *
	 * @return string
	 */
	private function editUrl( int $id ): string {
		return add_query_arg(
			array(
				'page'       => ConnectionFormPage::SLUG,
				'connection' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param Connection $item        Row.
	 * @param string     $column_name Column being rendered.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'integration_slug':
				return esc_html( $item->integrationSlug() );

			case 'auth_type':
				return esc_html( ConnectionAuthTypes::label( $item->authType() ) );

			case 'status':
				return ConnectionStatusBadge::render( $item->status() );

			case 'created_at':
				return esc_html( RunTimestamp::format( $item->createdAt(), $this->settings->displayTimestampsInUtc() ) );

			default:
				return '';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'aiawa-connections-table' );
	}

	/**
	 * Renders a small standalone POST form styled as a row-action link,
	 * same pattern as WorkflowsListTable::actionForm() — including the
	 * lack of a JS confirm() dialog, matching every other destructive
	 * row action in this plugin (e.g. the Workflows list's "Delete
	 * Permanently"), none of which use one either.
	 *
	 * @param int $id Connection id.
	 *
	 * @return string
	 */
	private function deleteForm( int $id ): string {
		$form_id     = 'aiawa-connection-delete-' . $id;
		$nonce_field = wp_nonce_field( 'aiawa_connection_action_delete_' . $id, '_wpnonce', true, false );

		$form_markup = sprintf(
			'<form id="%1$s" method="post" action="%2$s" class="aiawa-detached-row-action-form">'
				. '<input type="hidden" name="action" value="aiawa_connection_action" />'
				. '<input type="hidden" name="op" value="delete" />'
				. '<input type="hidden" name="connection_id" value="%3$d" />'
				. '%4$s'
				. '</form>',
			esc_attr( $form_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			$id,
			$nonce_field
		);

		return $this->rowForms->registerButton( $form_id, $form_markup, __( 'Delete', 'ai-agent-workflow-automation' ) );
	}

	private function currentIntegrationFilter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		return isset( $_GET['integration_slug'] ) ? sanitize_key( wp_unslash( $_GET['integration_slug'] ) ) : '';
	}
}
