<?php
/**
 * Workflows admin page.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Admin\Pages;

use DRAGAIW\Plugin\Admin\AdminPage;
use DRAGAIW\Plugin\Admin\EmptyState;
use DRAGAIW\Plugin\Admin\ListTableUi;
use DRAGAIW\Plugin\Admin\WorkflowActionsController;
use DRAGAIW\Plugin\Admin\WorkflowsListTable;
use DRAGAIW\Plugin\Core\Capabilities;
use DRAGAIW\Plugin\Service\SettingsService;
use DRAGAIW\Plugin\Service\WorkflowService;

// BuilderPage lives in this same namespace (DRAGAIW\Plugin\Admin\Pages), so no `use` import is needed to reference BuilderPage::SLUG below.

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
	public const SLUG = 'dragaiw-dashboard';

	private WorkflowService $workflows;

	private SettingsService $settings;

	private WorkflowActionsController $workflowActions;

	public function __construct( WorkflowService $workflows, SettingsService $settings, WorkflowActionsController $workflowActions ) {
		$this->workflows       = $workflows;
		$this->settings        = $settings;
		$this->workflowActions = $workflowActions;
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
		return __( 'Workflows', 'dragwyb-ai-agent-workflows' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Workflows', 'dragwyb-ai-agent-workflows' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_WORKFLOWS;
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

		wp_register_script(
			'dragaiw-workflow-import',
			false,
			array(),
			DRAGAIW_VERSION,
			true
		);
		wp_enqueue_script( 'dragaiw-workflow-import' );
		wp_add_inline_script(
			'dragaiw-workflow-import',
			'(function(){var f=document.querySelector(".dragaiw-workflow-import-form");if(!f)return;var i=f.querySelector(".dragaiw-workflow-import-form__input");if(!i)return;i.addEventListener("change",function(){if(i.files&&i.files.length){f.submit();}});})();'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-ai-agent-workflows' ) );
		}

		$table = new WorkflowsListTable( $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragaiw-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . BuilderPage::SLUG ) ),
			esc_html__( 'Add New', 'dragwyb-ai-agent-workflows' )
		);
		if($table->has_items()) {
			$this->renderImportButton();
		}
		echo '<hr class="wp-header-end" />';

		$this->renderNotice();

		// Guided first-workflow panel (roadmap item 16) when the site has
		// no workflows at all — not when a status filter or Trash view is
		// simply empty.
		if ( $this->shouldShowFirstWorkflowGuide( $table ) ) {
			EmptyState::render(
				__( 'Create your first workflow', 'dragwyb-ai-agent-workflows' ),
				__( 'Workflows automate work for you: a trigger starts a run, then one or more actions do the work.', 'dragwyb-ai-agent-workflows' ),
				array(
					__( 'Open the editor and add a trigger (for example a WordPress hook or an inbound webhook).', 'dragwyb-ai-agent-workflows' ),
					__( 'Add an action (send email, HTTP request, and more).', 'dragwyb-ai-agent-workflows' ),
					__( 'Save, then set the workflow to Active so it can run automatically.', 'dragwyb-ai-agent-workflows' ),
				),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . BuilderPage::SLUG ),
						'label'   => __( 'Create workflow', 'dragwyb-ai-agent-workflows' ),
						'primary' => true,
					),
				)
			);

			// Keep the status views (especially Trash) reachable when the
			// "all" list is empty but trashed workflows still exist.
			echo '<form method="get" class="dragaiw-list-table-filters-form">';
			printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
			$table->views();
			echo '</form>';
			echo '</div>';

			return;
		}

		echo '<form method="get" class="dragaiw-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selector.
		$view = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		if ( 'all' !== $view ) {
			printf( '<input type="hidden" name="status" value="%s" />', esc_attr( $view ) );
		}
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		$table->views();

		ListTableUi::openBulkForm( $this->slug(), 'dragaiw_workflow_bulk_action', 'dragaiw_workflow_bulk' );
		ListTableUi::renderPreservedFilters( $table->preservedFilters() );
		$table->display();
		ListTableUi::closeBulkForm();

		$table->renderRowActionForms();

		echo '</div>';
	}

	/**
	 * Whether to show the guided empty state instead of the list table.
	 *
	 * @param WorkflowsListTable $table Prepared list table.
	 *
	 * @return bool
	 */
	private function shouldShowFirstWorkflowGuide( WorkflowsListTable $table ): bool {
		if ( $table->has_items() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selector.
		$view = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		return 'all' === $view || '' === $view;
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?dragaiw_notice=` query arg. Kept as literal `__()` calls (rather than a
	 * class constant) so i18n string-extraction tooling can find them.
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'trashed'      => array(
				'message' => __( 'Workflow moved to Trash.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'restored'     => array(
				'message' => __( 'Workflow restored.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Workflow permanently deleted.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'activated'    => array(
				'message' => __( 'Workflow activated. It will run when its trigger fires.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'paused'       => array(
				'message' => __( 'Workflow paused. Triggers will not start new runs until it is activated again.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'imported'     => array(
				'message' => __( 'Workflow imported from JSON.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'success',
			),
			'import_error' => array(
				'message' => __( 'Could not import that JSON file. Use a Dragwyb AI Agent Workflows export (not an n8n file).', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
			'error'        => array(
				'message' => __( 'That workflow action could not be completed.', 'dragwyb-ai-agent-workflows' ),
				'type'    => 'error',
			),
		);
	}

	/**
	 * Renders the page-title "Import" control (JSON file upload).
	 *
	 * @return void
	 */
	private function renderImportButton(): void {
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dragaiw-workflow-import-form page-title-action">';
		echo '<input type="hidden" name="action" value="dragaiw_workflow_import" />';
		wp_nonce_field( 'dragaiw_workflow_import' );
		echo '<label class="dragaiw-workflow-import-form__label">';
		echo '<span class="screen-reader-text">' . esc_html__( 'Import workflow JSON', 'dragwyb-ai-agent-workflows' ) . '</span>';
		echo '<span aria-hidden="true">' . esc_html__( 'Import', 'dragwyb-ai-agent-workflows' ) . '</span>';
		echo '<input type="file" name="dragaiw_workflow_json" accept="application/json,.json" class="dragaiw-workflow-import-form__input" required />';
		echo '</label>';
		echo '<button type="submit" class="dragaiw-workflow-import-form__submit screen-reader-text">' . esc_html__( 'Upload', 'dragwyb-ai-agent-workflows' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Prints an admin notice for the read-only `?dragaiw_notice=` query arg, if
	 * it matches one of the allow-listed keys from self::notices().
	 *
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
