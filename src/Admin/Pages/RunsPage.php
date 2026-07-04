<?php
/**
 * Runs (execution history) admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Admin\EmptyState;
use WorkflowAutomate\Plugin\Admin\RunsListTable;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Service\SettingsService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level "Runs" screen (roadmap item 9): a paginated, status-filterable
 * list of every run across every workflow, optionally narrowed to a single
 * workflow via the `?workflow_id=` query arg set by WorkflowsListTable's
 * "Runs" row action. Reading run history needs no service-layer
 * orchestration beyond what the repositories already provide, so — unlike
 * WorkflowsPage — this reads directly from WorkflowRunRepository /
 * WorkflowRepository rather than through a dedicated service.
 */
class RunsPage implements AdminPage {

	public const SLUG = 'wfa-runs';

	private WorkflowRunRepository $runs;

	private WorkflowRepository $workflows;

	private SettingsService $settings;

	public function __construct( WorkflowRunRepository $runs, WorkflowRepository $workflows, SettingsService $settings ) {
		$this->runs = $runs;
		$this->workflows = $workflows;
		$this->settings = $settings;
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
		return __( 'Runs', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Runs', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function capability(): string {
		return Capabilities::MANAGE_RUNS;
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

		$table = new RunsListTable( $this->runs, $this->workflows, $this->settings );
		$table->prepare_items();

		echo '<div class="wrap wfa-admin-page">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $this->pageTitle() ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->renderWorkflowFilterNotice();
		$this->renderNotice();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$has_filters = ( isset( $_GET['workflow_id'] ) && absint( wp_unslash( $_GET['workflow_id'] ) ) > 0 )
			|| ( isset( $_GET['status'] ) && '' !== sanitize_key( wp_unslash( $_GET['status'] ) ) );

		if ( ! $table->has_items() && ! $has_filters ) {
			EmptyState::render(
				__( 'No runs yet', 'workflow-automate' ),
				__( 'Runs appear here when a workflow executes — automatically from a trigger or webhook, or when you use Run now in the editor.', 'workflow-automate' ),
				array(),
				array(
					array(
						'url' => admin_url( 'admin.php?page=' . WorkflowsPage::SLUG ),
						'label' => __( 'Go to Workflows', 'workflow-automate' ),
						'primary' => true,
					),
				)
			);
			echo '</div>';

			return;
		}

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( $this->slug() ) );
		$table->views();
		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * When the list is filtered to a single workflow (via `?workflow_id=`),
	 * shows which one and a link to clear the filter — the same purpose
	 * core's own post-list "all dates"/category filters serve.
	 *
	 * @return void
	 */
	private function renderWorkflowFilterNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter, not a state change.
		$workflow_id = isset( $_GET['workflow_id'] ) ? absint( wp_unslash( $_GET['workflow_id'] ) ) : 0;

		if ( $workflow_id <= 0 ) {
			return;
		}

		$workflow = $this->workflows->find( $workflow_id, true );
		$name = $workflow ? $workflow->title() : __( '(deleted workflow)', 'workflow-automate' );

		printf(
			'<p class="wfa-runs-filter-notice">%1$s <a href="%2$s">%3$s</a></p>',
			sprintf(
				/* translators: %s: workflow title. */
				esc_html__( 'Showing runs for: %s', 'workflow-automate' ),
				'<strong>' . esc_html( $name ) . '</strong>'
			),
			esc_url( remove_query_arg( 'workflow_id' ) ),
			esc_html__( 'Clear filter', 'workflow-automate' )
		);
	}

	/**
	 * Allow-listed, already-translated messages for the read-only
	 * `?wfa_notice=` query arg, same pattern as WorkflowsPage::notices().
	 *
	 * @return array<string, array{message: string, type: string}>
	 */
	private function notices(): array {
		return array(
			'rerun_failed' => array(
				'message' => __( 'That run could not be re-run.', 'workflow-automate' ),
				'type' => 'error',
			),
		);
	}

	/**
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
