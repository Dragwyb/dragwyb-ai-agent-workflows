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

// BuilderPage lives in this same namespace (WorkflowAutomate\Plugin\Admin\Pages), so no `use` import is needed to reference BuilderPage::SLUG below.

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's top-level admin screen: a list of workflows with per-row
 * edit/trash/restore/delete actions. Creating and editing a workflow's
 * actual content happens on the visual builder screen (`BuilderPage`,
 * roadmap item 6) — this screen only ever links out to it via "Add New"
 * and "Edit".
 */
class WorkflowsPage implements AdminPage {

	/**
	 * Public so `BuilderPage` can link back to this page without needing an
	 * instantiated `WorkflowsPage` (see `BuilderPage::SLUG` for the same
	 * pattern used in reverse, for the "Add New"/"Edit" links below).
	 */
	public const SLUG = 'wfa-dashboard';

	private WorkflowService $workflows;

	public function __construct( WorkflowService $workflows ) {
		$this->workflows = $workflows;
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
	public function showInMenu(): bool {
		return true;
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
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . BuilderPage::SLUG ) ),
			esc_html__( 'Add New', 'workflow-automate' )
		);
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
