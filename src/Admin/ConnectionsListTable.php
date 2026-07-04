<?php
/**
 * Connections admin list table.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use WorkflowAutomate\Plugin\Admin\Pages\ConnectionFormPage;
use WorkflowAutomate\Plugin\Domain\Connection;
use WorkflowAutomate\Plugin\Service\ConnectionAuthTypes;
use WorkflowAutomate\Plugin\Service\ConnectionService;
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

	public function __construct( ConnectionService $connections, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'connection',
				'plural' => 'connections',
				'ajax' => false,
			)
		);

		$this->connections = $connections;
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'label' => __( 'Label', 'workflow-automate' ),
			'integration_slug' => __( 'Integration', 'workflow-automate' ),
			'auth_type' => __( 'Authentication', 'workflow-automate' ),
			'status' => __( 'Status', 'workflow-automate' ),
			'created_at' => __( 'Created', 'workflow-automate' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$result = $this->connections->list(
			array(
				'page' => $paged,
				'per_page' => self::PER_PAGE,
			)
		);

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page' => self::PER_PAGE,
			)
		);
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
			'edit' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'workflow-automate' ) ),
			'delete' => $this->deleteForm( $item->id() ),
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * @param int $id Connection id.
	 *
	 * @return string
	 */
	private function editUrl( int $id ): string {
		return add_query_arg(
			array(
				'page' => ConnectionFormPage::SLUG,
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
		return array( 'widefat', 'fixed', 'striped', 'wfa-connections-table' );
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
		$nonce_field = wp_nonce_field( 'wfa_connection_action_delete_' . $id, '_wpnonce', true, false );

		return sprintf(
			'<form method="post" action="%1$s" class="wfa-row-action-form">'
				. '<input type="hidden" name="action" value="wfa_connection_action" />'
				. '<input type="hidden" name="op" value="delete" />'
				. '<input type="hidden" name="connection_id" value="%2$d" />'
				. '%3$s'
				. '<button type="submit" class="wfa-row-action-button">%4$s</button>'
				. '</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			$id,
			$nonce_field,
			esc_html__( 'Delete', 'workflow-automate' )
		);
	}
}
