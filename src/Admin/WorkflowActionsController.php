<?php
/**
 * Handles state-changing Workflow admin actions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use InvalidArgumentException;
use RuntimeException;
use AIAWA\Plugin\Admin\Pages\BuilderPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Domain\Workflow;
use AIAWA\Plugin\Service\WorkflowImportExport;
use AIAWA\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives the `admin-post.php?action=aiawa_workflow_action` POST submitted
 * by WorkflowsListTable's row-action forms (trash/restore/delete).
 *
 * Deliberately its own class rather than logic inlined into `WorkflowsPage`
 * or `Menu`: this is the only place in the Admin layer that mutates data,
 * so it is the only place that needs the full nonce + capability + input
 * validation treatment from Section 5.
 */
class WorkflowActionsController {

	private const ALLOWED_OPS = array( 'trash', 'restore', 'delete', 'activate', 'pause' );

	private const MAX_IMPORT_BYTES = 2097152; // 2 MiB.

	private WorkflowService $workflows;

	private string $redirectSlug;

	/**
	 * @param WorkflowService $workflows    Workflow service.
	 * @param string          $redirectSlug Menu slug (see AdminPage::slug()) to redirect back to after handling.
	 */
	public function __construct( WorkflowService $workflows, string $redirectSlug ) {
		$this->workflows    = $workflows;
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
		add_action( 'admin_post_aiawa_workflow_action', array( $this, 'handle' ) );
		add_action( 'admin_post_aiawa_workflow_import', array( $this, 'handleImport' ) );
		add_action( 'admin_post_aiawa_workflow_export', array( $this, 'handleExport' ) );
		add_action( 'admin_init', array( $this, 'maybeHandleWorkflowsBulkFromList' ), 5 );
	}

	/**
	 * Early router so bulk POST is handled before any admin output.
	 *
	 * @return void
	 */
	public function maybeHandleWorkflowsBulkFromList(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( ! is_admin() || 'POST' !== $request_method ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( $this->redirectSlug !== $page ) {
			return;
		}

		$this->handleWorkflowsBulkFromList();
	}

	/**
	 * Processes the request, then redirects back to the Workflows list.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ai-agent-workflow-automation' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified explicitly below, per-operation and per-id.
		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( wp_unslash( $_POST['workflow_id'] ) ) : 0;

		if ( ! in_array( $op, self::ALLOWED_OPS, true ) || $workflow_id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'aiawa_workflow_action_' . $op . '_' . $workflow_id );

		$success = $this->perform( $op, $workflow_id );

		$this->redirect( $success ? $this->successNotice( $op ) : 'error' );
	}

	/**
	 * Creates a workflow from an uploaded JSON definition, then opens the builder.
	 *
	 * @return void
	 */
	public function handleImport(): void {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ai-agent-workflow-automation' ), 403 );
		}

		check_admin_referer( 'aiawa_workflow_import' );

		if ( empty( $_FILES['aiawa_workflow_json'] ) || ! is_array( $_FILES['aiawa_workflow_json'] ) ) {
			$this->redirect( 'import_error' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- manual sanitization is performed below
		$file  = $_FILES['aiawa_workflow_json'];
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			$this->redirect( 'import_error' );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( $size <= 0 || $size > self::MAX_IMPORT_BYTES || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->redirect( 'import_error' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading an uploaded temp file.
		$raw = file_get_contents( $tmp );

		if ( false === $raw ) {
			$this->redirect( 'import_error' );
		}

		try {
			$payload    = WorkflowImportExport::decodeJson( $raw );
			$attributes = WorkflowImportExport::parseImportPayload( $payload );
			$workflow   = $this->workflows->create(
				array(
					'title'    => $attributes['title'],
					'graph'    => $attributes['graph'],
					'settings' => $attributes['settings'],
				)
			);
		} catch ( InvalidArgumentException $e ) {
			$this->redirect( 'import_error' );
		} catch ( RuntimeException $e ) {
			$this->redirect( 'import_error' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => BuilderPage::SLUG,
					'workflow'   => $workflow->id(),
					'aiawa_notice' => 'imported',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Streams a portable JSON definition for a workflow.
	 *
	 * @return void
	 */
	public function handleExport(): void {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ai-agent-workflow-automation' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		$workflow_id = isset( $_REQUEST['workflow_id'] ) ? absint( wp_unslash( $_REQUEST['workflow_id'] ) ) : 0;

		if ( $workflow_id <= 0 ) {
			$this->redirect( 'error' );
		}

		check_admin_referer( 'aiawa_workflow_export_' . $workflow_id );

		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			$this->redirect( 'error' );
		}

		$payload  = WorkflowImportExport::exportWorkflow( $workflow );
		$filename = WorkflowImportExport::exportFilename( $workflow );
		$json     = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			$this->redirect( 'error' );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $json ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON download body.
		echo $json;
		exit;
	}

	/**
	 * Handles bulk actions from the Workflows list table form.
	 *
	 * @return void
	 */
	public function handleWorkflowsBulkFromList(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified below.
		if ( empty( $_POST['aiawa_workflow_bulk'] ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ai-agent-workflow-automation' ), 403 );
		}

		if ( ! ListTableUi::verifyBulkNonce( 'aiawa_workflow_bulk_action' ) ) {
			$this->redirect( 'error', $this->bulkRedirectArgs() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in verifyBulkNonce().
		$bulk_action = isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : ( isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' );

		if ( ! in_array( $bulk_action, self::ALLOWED_OPS, true ) ) {
			$this->redirect( 'error', $this->bulkRedirectArgs() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in verifyBulkNonce().
		$ids = isset( $_POST['workflows'] ) && is_array( $_POST['workflows'] ) ? array_map( 'absint', wp_unslash( $_POST['workflows'] ) ) : array();

		$ids = array_values( array_filter( $ids ) );

		if ( array() === $ids ) {
			$this->redirect( 'error', $this->bulkRedirectArgs() );
		}

		$success = 0;
		foreach ( $ids as $workflow_id ) {
			if ( $this->perform( $bulk_action, $workflow_id ) ) {
				++$success;
			}
		}

		$this->redirect( $success > 0 ? $this->successNotice( $bulk_action ) : 'error', $this->bulkRedirectArgs() );
	}

	/**
	 * @return array<string, scalar>
	 */
	private function bulkRedirectArgs(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only redirect filters.
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only redirect filters.
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';

		$args = array();

		if ( '' !== $status && in_array( $status, array( 'all', 'draft', 'active', 'paused', 'trash' ), true ) && 'all' !== $status ) {
			$args['status'] = $status;
		}

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return $args;
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

			case 'activate':
				return null !== $this->workflows->changeStatus( $workflow_id, Workflow::STATUS_ACTIVE );

			case 'pause':
				return null !== $this->workflows->changeStatus( $workflow_id, Workflow::STATUS_PAUSED );

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
			case 'activate':
				return 'activated';
			case 'pause':
				return 'paused';
			default:
				return 'error';
		}
	}

	/**
	 * Redirects back to the Workflows list with a notice, then exits.
	 *
	 * @param string                $notice One of the keys understood by WorkflowsPage::renderNotice().
	 * @param array<string, scalar> $extra  Optional query args to preserve.
	 *
	 * @return void
	 */
	private function redirect( string $notice, array $extra = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page'       => $this->redirectSlug,
						'aiawa_notice' => $notice,
					),
					$extra
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
