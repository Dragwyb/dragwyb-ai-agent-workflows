<?php
/**
 * Handles state-changing Run admin actions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Admin\Pages\RunDetailPage;
use WorkflowAutomate\Plugin\Admin\Pages\RunsPage;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=wfa_run_action` POST submitted by
 * RunsListTable's and RunDetailPage's row/page action forms.
 *
 * Same reasoning as WorkflowActionsController for being its own class: the
 * only place in the Runs history UI that mutates anything, so the only
 * place that needs the full nonce + capability + input validation
 * treatment from Section 5.
 */
class RunActionsController {

	private const ALLOWED_OPS = array( 'rerun', 'delete' );

	private WorkflowExecutionService $executor;

	private WorkflowRunRepository $runs;

	private WorkflowRunLogRepository $runLogs;

	public function __construct( WorkflowExecutionService $executor, WorkflowRunRepository $runs, WorkflowRunLogRepository $runLogs ) {
		$this->executor = $executor;
		$this->runs = $runs;
		$this->runLogs = $runLogs;
	}

	/**
	 * Hooks the admin-post handler. There is no `admin_post_nopriv_*`
	 * counterpart: this action is never valid for a logged-out visitor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wfa_run_action', array( $this, 'handle' ) );
		add_action( 'admin_init', array( $this, 'maybeHandleRunsBulkFromList' ), 5 );
	}

	/**
	 * Early router so bulk POST is handled before any admin output.
	 *
	 * @return void
	 */
	public function maybeHandleRunsBulkFromList(): void {
		if ( ! is_admin() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( RunsPage::SLUG !== $page ) {
			return;
		}

		$this->handleRunsBulkFromList();
	}

	/**
	 * Handles bulk actions submitted from the Runs list table form.
	 * Called from admin_init because WP_List_Table's bulk dropdown also
	 * uses the `action` field name, which conflicts with admin-post.php
	 * routing and the default `_wpnonce` field name.
	 *
	 * @return void
	 */
	public function handleRunsBulkFromList(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
		if ( empty( $_POST['wfa_run_bulk'] ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_RUNS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		if ( ! ListTableUi::verifyBulkNonce( 'wfa_run_bulk_action' ) ) {
			$this->redirectToList( 'action_failed', $this->bulkRedirectArgs() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$bulk_action = isset( $_POST['action2'] ) && '-1' !== $_POST['action2']
			? sanitize_key( wp_unslash( $_POST['action2'] ) )
			: ( isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' );

		if ( 'delete' !== $bulk_action ) {
			$this->redirectToList( 'action_failed', $this->bulkRedirectArgs() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$ids = isset( $_POST['runs'] ) && is_array( $_POST['runs'] )
			? array_map( 'absint', wp_unslash( $_POST['runs'] ) )
			: array();

		$ids = array_values( array_filter( $ids ) );

		if ( array() === $ids ) {
			$this->redirectToList( 'action_failed', $this->bulkRedirectArgs() );
		}

		$this->runLogs->deleteByRunIds( $ids );
		$this->runs->deleteByIds( $ids );

		$this->redirectToList( 'bulk_deleted', $this->bulkRedirectArgs() );
	}

	/**
	 * Processes the request, then redirects.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_RUNS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$run_id = isset( $_POST['run_id'] ) ? absint( wp_unslash( $_POST['run_id'] ) ) : 0;

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) || $run_id <= 0 ) {
			$this->redirectToList( 'action_failed' );
		}

		check_admin_referer( 'wfa_run_action_' . $op . '_' . $run_id );

		switch ( $op ) {
			case 'rerun':
				$this->handleRerun( $run_id );
				break;

			case 'delete':
				$this->handleDelete( $run_id );
				break;
		}
	}

	/**
	 * @param int $run_id The run to re-execute.
	 *
	 * @return void
	 */
	private function handleRerun( int $run_id ): void {
		try {
			$new_run = $this->executor->rerun( $run_id );
		} catch ( InvalidArgumentException | RuntimeException $exception ) {
			unset( $exception );
			$this->redirectToDetail( $run_id, 'rerun_failed' );

			return;
		}

		$this->redirectToDetail( $new_run->id(), 'rerun_started' );
	}

	/**
	 * Permanently removes a run and its log rows.
	 *
	 * @param int $run_id Run id.
	 *
	 * @return void
	 */
	private function handleDelete( int $run_id ): void {
		if ( null === $this->runs->find( $run_id ) ) {
			$this->redirectToList( 'delete_failed' );

			return;
		}

		$this->runLogs->deleteByRunIds( array( $run_id ) );
		$deleted = $this->runs->deleteByIds( array( $run_id ) );

		if ( $deleted <= 0 ) {
			$this->redirectToList( 'delete_failed' );

			return;
		}

		$this->redirectToList( 'deleted' );
	}

	/**
	 * Redirects to a run's detail page with a notice, then exits.
	 *
	 * @param int    $run_id Run id.
	 * @param string $notice One of the keys understood by RunDetailPage::notices().
	 *
	 * @return void
	 */
	private function redirectToDetail( int $run_id, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => RunDetailPage::SLUG,
					'run_id' => $run_id,
					'wfa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return array<string, scalar>
	 */
	private function bulkRedirectArgs(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only redirect filters from the submitting bulk form.
		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( wp_unslash( $_POST['workflow_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only redirect filters from the submitting bulk form.
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		$args = array();

		if ( $workflow_id > 0 ) {
			$args['workflow_id'] = $workflow_id;
		}

		if ( '' !== $status && in_array( $status, WorkflowRun::VALID_STATUSES, true ) ) {
			$args['status'] = $status;
		}

		return $args;
	}

	/**
	 * Redirects to the Runs list with a notice, then exits. Used when
	 * the request itself is malformed (no specific run to go back to),
	 * or after a successful delete.
	 *
	 * @param string               $notice One of the keys understood by RunsPage::notices().
	 * @param array<string, scalar> $extra  Optional query args to preserve (filters).
	 *
	 * @return void
	 */
	private function redirectToList( string $notice, array $extra = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page' => RunsPage::SLUG,
						'wfa_notice' => $notice,
					),
					$extra
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
