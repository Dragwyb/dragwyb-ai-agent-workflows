<?php
/**
 * Webhooks admin list table.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Admin;

use DragwybAgentFlow\Plugin\Admin\Pages\WebhookFormPage;
use DragwybAgentFlow\Plugin\Domain\Webhook;
use DragwybAgentFlow\Plugin\Service\SettingsService;
use DragwybAgentFlow\Plugin\Service\WebhookService;
use DragwybAgentFlow\Plugin\Service\WorkflowService;
use WP_List_Table;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the Webhooks screen (roadmap item 13) using `WP_List_Table`.
 * Never renders a decrypted signing secret — only whether one is
 * configured, plus the public URL, linked workflow, IP allow-list size,
 * and created timestamp.
 */
class WebhooksListTable extends WP_List_Table {

	private const PER_PAGE = 20;

	private WebhookService $webhooks;

	private WorkflowService $workflows;

	private SettingsService $settings;

	private RowActionForms $rowForms;

	/**
	 * @var array<int, string> workflow id => title, filled in prepare_items().
	 */
	private array $workflowTitles = array();

	/**
	 * @var array<int, string> workflow id => title for the filter dropdown.
	 */
	private array $workflowFilterOptions = array();

	public function __construct( WebhookService $webhooks, WorkflowService $workflows, SettingsService $settings ) {
		parent::__construct(
			array(
				'singular' => 'webhook',
				'plural'   => 'webhooks',
				'ajax'     => false,
			)
		);

		$this->webhooks  = $webhooks;
		$this->workflows = $workflows;
		$this->settings  = $settings;
		$this->rowForms  = new RowActionForms();
	}

	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'public_url'    => __( 'Public URL', 'dragwyb-agentflow' ),
			'workflow'      => __( 'Workflow', 'dragwyb-agentflow' ),
			'signing'       => __( 'Signing', 'dragwyb-agentflow' ),
			'ip_allow_list' => __( 'IP allow-list', 'dragwyb-agentflow' ),
			'created_at'    => __( 'Created', 'dragwyb-agentflow' ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'dragwyb-agentflow' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="webhooks[]" value="%d" />',
			$item->id()
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter, not a state change.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$result = $this->webhooks->list(
			array(
				'page'        => $paged,
				'per_page'    => self::PER_PAGE,
				'workflow_id' => $this->currentWorkflowFilter(),
			)
		);

		$this->items                 = $result['items'];
		$this->workflowTitles        = $this->loadWorkflowTitles( $result['items'] );
		$this->workflowFilterOptions = $this->loadWorkflowFilterOptions();

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $result['per_page'],
				'total_pages' => (int) ceil( $result['total'] / max( 1, $result['per_page'] ) ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		esc_html_e( 'No webhooks yet.', 'dragwyb-agentflow' );
	}

	/**
	 * @param Webhook[] $webhooks Current page of webhooks.
	 *
	 * @return array<int, string>
	 */
	private function loadWorkflowTitles( array $webhooks ): array {
		$titles = array();

		foreach ( $webhooks as $webhook ) {
			$workflow_id = $webhook->workflowId();

			if ( null === $workflow_id || isset( $titles[ $workflow_id ] ) ) {
				continue;
			}

			$workflow               = $this->workflows->find( $workflow_id, true );
			$titles[ $workflow_id ] = $workflow
				? $workflow->title()
				: __( '(deleted workflow)', 'dragwyb-agentflow' );
		}

		return $titles;
	}

	private function loadWorkflowFilterOptions(): array {
		$result = $this->workflows->list(
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

	public function filterFields(): array {
		$options = array(
			'0' => __( 'All workflows', 'dragwyb-agentflow' ),
		);

		foreach ( $this->workflowFilterOptions as $id => $title ) {
			$options[ (string) $id ] = $title;
		}

		return array(
			array(
				'name'    => 'workflow_id',
				'label'   => __( 'Filter by workflow', 'dragwyb-agentflow' ),
				'value'   => (string) $this->currentWorkflowFilter(),
				'options' => $options,
			),
		);
	}

	public function preservedFilters(): array {
		return array(
			'workflow_id' => $this->currentWorkflowFilter(),
		);
	}

	public function renderRowActionForms(): void {
		$this->rowForms->render();
	}

	/**
	 * @param Webhook $item Row item.
	 *
	 * @return string
	 */
	protected function column_public_url( $item ) {
		$url      = $this->webhooks->publicUrl( $item );
		$edit_url = $this->editUrl( $item->id() );

		$title = sprintf(
			'<strong><a href="%1$s"><code class="dragwyb-af-webhook-url">%2$s</code></a></strong>',
			esc_url( $edit_url ),
			esc_html( $url )
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'dragwyb-agentflow' ) ),
			'delete' => $this->deleteForm( $item->id() ),
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * @param Webhook $item        Row item.
	 * @param string  $column_name Column key.
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'workflow':
				$workflow_id = $item->workflowId();

				if ( null === $workflow_id ) {
					return esc_html__( '(none)', 'dragwyb-agentflow' );
				}

				return esc_html( $this->workflowTitles[ $workflow_id ] ?? __( '(deleted workflow)', 'dragwyb-agentflow' ) );

			case 'signing':
				return $item->hasSigningSecret()
					? esc_html__( 'Required', 'dragwyb-agentflow' )
					: esc_html__( 'Off', 'dragwyb-agentflow' );

			case 'ip_allow_list':
				$count = count( $item->ipAllowList() );

				return $count > 0
					? esc_html(
						sprintf(
						/* translators: %d: number of allowed IPs/CIDRs. */
							_n( '%d entry', '%d entries', $count, 'dragwyb-agentflow' ),
							$count
						)
					)
					: esc_html__( 'Any IP', 'dragwyb-agentflow' );

			case 'created_at':
				return esc_html( RunTimestamp::format( $item->createdAt(), $this->settings ) );

			default:
				return '';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'table-view-list', 'dragwyb-af-webhooks-table' );
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return string
	 */
	private function editUrl( int $id ): string {
		return admin_url( 'admin.php?page=' . WebhookFormPage::SLUG . '&webhook=' . $id );
	}

	/**
	 * @param int $id Webhook id.
	 *
	 * @return string
	 */
	private function deleteForm( int $id ): string {
		$form_id     = 'dragwyb-af-webhook-delete-' . $id;
		$nonce_field = wp_nonce_field( 'dragwyb_af_webhook_action_delete_' . $id, '_wpnonce', true, false );

		$form_markup = sprintf(
			'<form id="%1$s" method="post" action="%2$s" class="dragwyb-af-detached-row-action-form">'
				. '<input type="hidden" name="action" value="dragwyb_af_webhook_action" />'
				. '<input type="hidden" name="op" value="delete" />'
				. '<input type="hidden" name="webhook_id" value="%3$d" />'
				. '%4$s'
				. '</form>',
			esc_attr( $form_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			$id,
			$nonce_field
		);

		return $this->rowForms->registerButton( $form_id, $form_markup, __( 'Delete', 'dragwyb-agentflow' ) );
	}

	private function currentWorkflowFilter(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		return isset( $_GET['workflow_id'] ) ? absint( wp_unslash( $_GET['workflow_id'] ) ) : 0;
	}
}
