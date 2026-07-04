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
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin_post.php?action=wfa_run_action` POST submitted by
 * RunsListTable's and RunDetailPage's "Re-run" forms.
 *
 * Same reasoning as WorkflowActionsController for being its own class: the
 * only place in the Runs history UI that mutates anything, so the only
 * place that needs the full nonce + capability + input validation
 * treatment from Section 5.
 */
class RunActionsController {

	private const ALLOWED_OPS = array( 'rerun' );

	private WorkflowExecutionService $executor;

	public function __construct( WorkflowExecutionService $executor ) {
		$this->executor = $executor;
	}

	/**
	 * Hooks the admin-post handler. There is no `admin_post_nopriv_*`
	 * counterpart: this action is never valid for a logged-out visitor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wfa_run_action', array( $this, 'handle' ) );
	}

	/**
	 * Processes the request, then redirects.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'workflow-automate' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$run_id = isset( $_POST['run_id'] ) ? absint( wp_unslash( $_POST['run_id'] ) ) : 0;

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) || $run_id <= 0 ) {
			$this->redirectToList( 'rerun_failed' );
		}

		check_admin_referer( 'wfa_run_action_' . $op . '_' . $run_id );

		// 'rerun' is presently the only entry in self::ALLOWED_OPS, so it
		// is the only value that can reach this point; handleRerun() is
		// still its own method (rather than inlined here) so a future
		// second operation only needs a new branch, matching
		// WorkflowActionsController::perform()'s shape.
		$this->handleRerun( $run_id );
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
	 * Redirects to the Runs list with a notice, then exits. Used only when
	 * the request itself is malformed (no specific run to go back to).
	 *
	 * @param string $notice One of the keys understood by RunsPage::notices().
	 *
	 * @return void
	 */
	private function redirectToList( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => RunsPage::SLUG,
					'wfa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
