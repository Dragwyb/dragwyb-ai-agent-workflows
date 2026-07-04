<?php
/**
 * Handles state-changing Workflow admin actions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

use WorkflowAutomate\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=wfa_workflow_action` POST submitted
 * by WorkflowsListTable's row-action forms (trash/restore/delete).
 *
 * Deliberately its own class rather than logic inlined into `WorkflowsPage`
 * or `Menu`: this is the only place in the Admin layer that mutates data,
 * so it is the only place that needs the full nonce + capability + input
 * validation treatment from Section 5.
 */
class WorkflowActionsController {

	private const ALLOWED_OPS = array( 'trash', 'restore', 'delete' );

	private WorkflowService $workflows;

	private string $redirectSlug;

	/**
	 * @param WorkflowService $workflows    Workflow service.
	 * @param string          $redirectSlug Menu slug (see AdminPage::slug()) to redirect back to after handling.
	 */
	public function __construct( WorkflowService $workflows, string $redirectSlug ) {
		$this->workflows = $workflows;
		$this->redirectSlug = $redirectSlug;
	}

	/**
	 * Hooks the admin-post handler for both logged-in-only dispatch.
	 * There is no `admin_post_nopriv_*` counterpart: this action is never
	 * valid for a logged-out visitor.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wfa_workflow_action', array( $this, 'handle' ) );
	}

	/**
	 * Processes the request, then redirects back to the Workflows list.
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
		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( wp_unslash( $_POST['workflow_id'] ) ) : 0;

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) || $workflow_id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'wfa_workflow_action_' . $op . '_' . $workflow_id );

		$success = $this->perform( $op, $workflow_id );

		$this->redirect( $success ? $this->successNotice( $op ) : 'error' );
	}

	/**
	 * @param string $op          One of self::ALLOWED_OPS.
	 * @param int    $workflow_id Workflow id.
	 *
	 * @return bool
	 */
	private function perform( string $op, int $workflow_id ): bool {
		// Confirmed to exist first: the repository's update-based methods
		// report success based on "the query ran without error," which is
		// also true (0 rows affected) when the id simply doesn't exist.
		if ( null === $this->workflows->find( $workflow_id, true ) ) {
			return false;
		}

		switch ( $op ) {
			case 'trash':
				return $this->workflows->delete( $workflow_id, false );

			case 'restore':
				return $this->workflows->restore( $workflow_id );

			case 'delete':
				return $this->workflows->delete( $workflow_id, true );

			default:
				return false;
		}
	}

	private function successNotice( string $op ): string {
		switch ( $op ) {
			case 'trash':
				return 'trashed';
			case 'restore':
				return 'restored';
			case 'delete':
				return 'deleted';
			default:
				return 'error';
		}
	}

	/**
	 * Redirects back to the Workflows list with a notice, then exits.
	 *
	 * @param string $notice One of the keys understood by WorkflowsPage::renderNotice().
	 *
	 * @return void
	 */
	private function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => $this->redirectSlug,
					'wfa_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
