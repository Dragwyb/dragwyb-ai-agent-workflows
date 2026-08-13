<?php
/**
 * Workflows admin page.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Admin\Pages;

use DragwybVisualAutomation\Plugin\Admin\AdminPage;
use DragwybVisualAutomation\Plugin\Admin\EmptyState;
use DragwybVisualAutomation\Plugin\Admin\ListTableUi;
use DragwybVisualAutomation\Plugin\Admin\WorkflowActionsController;
use DragwybVisualAutomation\Plugin\Admin\WorkflowsListTable;
use DragwybVisualAutomation\Plugin\Core\Capabilities;
use DragwybVisualAutomation\Plugin\Service\SettingsService;
use DragwybVisualAutomation\Plugin\Service\WorkflowService;

// BuilderPage lives in this same namespace (DragwybVisualAutomation\Plugin\Admin\Pages), so no `use` import is needed to reference BuilderPage::SLUG below.

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
	public const SLUG = 'dragwyb-af-dashboard';

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
		return __( 'Workflows', 'dragwyb-visual-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Workflows', 'dragwyb-visual-automation' );
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
			'dragwyb-af-admin',
			DRAGWYB_AF_PLUGIN_URL . 'assets/admin/css/admin.css',
			array(),
			DRAGWYB_AF_VERSION
		);

		wp_register_script(
			'dragwyb-af-workflow-import',
			false,
			array(),
			DRAGWYB_AF_VERSION,
			true
		);
		wp_enqueue_script( 'dragwyb-af-workflow-import' );
		wp_add_inline_script(
			'dragwyb-af-workflow-import',
			'(function(){var f=document.querySelector(".dragwyb-af-workflow-import-form");if(!f)return;var i=f.querySelector(".dragwyb-af-workflow-import-form__input");if(!i)return;i.addEventListener("change",function(){if(i.files&&i.files.length){f.submit();}});})();'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-visual-automation' ) );
		}

		$table = new WorkflowsListTable( $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap dragwyb-af-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . BuilderPage::SLUG ) ),
			esc_html__( 'Add New', 'dragwyb-visual-automation' )
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
				__( 'Create your first workflow', 'dragwyb-visual-automation' ),
				__( 'Workflows automate work for you: a trigger starts a run, then one or more actions do the work.', 'dragwyb-visual-automation' ),
				array(
					__( 'Open the editor and add a trigger (for example a WordPress hook or an inbound webhook).', 'dragwyb-visual-automation' ),
					__( 'Add an action (send email, HTTP request, and more).', 'dragwyb-visual-automation' ),
					__( 'Save, then set the workflow to Active so it can run automatically.', 'dragwyb-visual-automation' ),
				),
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . BuilderPage::SLUG ),
						'label'   => __( 'Create workflow', 'dragwyb-visual-automation' ),
						'primary' => true,
					),
				)
			);

			// Keep the status views (especially Trash) reachable when the
			// "all" list is empty but trashed workflows still exist.
			echo '<form method="get" class="dragwyb-af-list-table-filters-form">';
			printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
			$table->views();
			echo '</form>';
			echo '</div>';

			return;
		}

		echo '<form method="get" class="dragwyb-af-list-table-filters-form">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selector.
		$view = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		if ( 'all' !== $view ) {
			printf( '<input type="hidden" name="status" value="%s" />', esc_attr( $view ) );
		}
		ListTableUi::renderFilterBar( 'top', $table->filterFields() );
		echo '</form>';

		$table->views();

		ListTableUi::openBulkForm( $this->slug(), 'dragwyb_af_workflow_bulk_action', 'dragwyb_af_workflow_bulk' );
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
	 * `?dragwyb_af_notice=` query arg. Kept as literal `__()` calls (rather than a
	 * class constant) so i18n string-extraction tooling can find them.
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'trashed'      => array(
				'message' => __( 'Workflow moved to Trash.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'restored'     => array(
				'message' => __( 'Workflow restored.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'deleted'      => array(
				'message' => __( 'Workflow permanently deleted.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'activated'    => array(
				'message' => __( 'Workflow activated. It will run when its trigger fires.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'paused'       => array(
				'message' => __( 'Workflow paused. Triggers will not start new runs until it is activated again.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'imported'     => array(
				'message' => __( 'Workflow imported from JSON.', 'dragwyb-visual-automation' ),
				'type'    => 'success',
			),
			'import_error' => array(
				'message' => __( 'Could not import that JSON file. Use a Dragwyb AI Agent Workflows export (not an n8n file).', 'dragwyb-visual-automation' ),
				'type'    => 'error',
			),
			'error'        => array(
				'message' => __( 'That workflow action could not be completed.', 'dragwyb-visual-automation' ),
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
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="dragwyb-af-workflow-import-form page-title-action">';
		echo '<input type="hidden" name="action" value="dragwyb_af_workflow_import" />';
		wp_nonce_field( 'dragwyb_af_workflow_import' );
		echo '<label class="dragwyb-af-workflow-import-form__label">';
		echo '<span class="screen-reader-text">' . esc_html__( 'Import workflow JSON', 'dragwyb-visual-automation' ) . '</span>';
		echo '<span aria-hidden="true">' . esc_html__( 'Import', 'dragwyb-visual-automation' ) . '</span>';
		echo '<input type="file" name="dragwyb_af_workflow_json" accept="application/json,.json" class="dragwyb-af-workflow-import-form__input" required />';
		echo '</label>';
		echo '<button type="submit" class="dragwyb-af-workflow-import-form__submit screen-reader-text">' . esc_html__( 'Upload', 'dragwyb-visual-automation' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Prints an admin notice for the read-only `?dragwyb_af_notice=` query arg, if
	 * it matches one of the allow-listed keys from self::notices().
	 *
	 * @return void
	 */
	private function renderNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector; the value is never echoed, only used as an array-key lookup against a fixed allow-list.
		$key     = isset( $_GET['dragwyb_af_notice'] ) ? sanitize_key( wp_unslash( $_GET['dragwyb_af_notice'] ) ) : '';
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
