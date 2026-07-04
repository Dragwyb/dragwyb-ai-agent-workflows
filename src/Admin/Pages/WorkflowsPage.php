<?php
/**
 * Workflows admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Admin\WorkflowsListTable;
use WorkflowAutomate\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's top-level admin screen: a list of workflows with per-row
 * trash/restore/delete actions. Creating and editing a workflow's content
 * is intentionally out of scope here — it lands with the visual builder
 * (roadmap item 6), which is the only screen that will have anywhere
 * meaningful to send a user after those actions.
 */
class WorkflowsPage implements AdminPage {

	private WorkflowService $workflows;

	public function __construct( WorkflowService $workflows ) {
		$this->workflows = $workflows;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'wfa-dashboard';
	}

	/**
	 * {@inheritDoc}
	 */
	public function pageTitle(): string {
		return __( 'Workflows', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Workflows', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return 'manage_options';
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

		$table = new WorkflowsListTable( $this->workflows );
		$table->prepare_items();

		echo '<div class="wrap wfa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		$table->views();
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?wfa_notice=` query arg. Kept as literal `__()` calls (rather than a
	 * class constant) so i18n string-extraction tooling can find them.
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'trashed' => array(
				'message' => __( 'Workflow moved to Trash.', 'workflow-automate' ),
				'type' => 'success',
			),
			'restored' => array(
				'message' => __( 'Workflow restored.', 'workflow-automate' ),
				'type' => 'success',
			),
			'deleted' => array(
				'message' => __( 'Workflow permanently deleted.', 'workflow-automate' ),
				'type' => 'success',
			),
			'error' => array(
				'message' => __( 'That workflow action could not be completed.', 'workflow-automate' ),
				'type' => 'error',
			),
		);
	}

	/**
	 * Prints an admin notice for the read-only `?wfa_notice=` query arg, if
	 * it matches one of the allow-listed keys from self::notices().
	 *
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
