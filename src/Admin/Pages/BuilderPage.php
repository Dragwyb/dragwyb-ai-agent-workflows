<?php
/**
 * Workflow builder admin page.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin\Pages;

use WorkflowAutomate\Plugin\Admin\AdminPage;
use WorkflowAutomate\Plugin\Core\Capabilities;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mounts the React builder app (roadmap item 6) from a single root element.
 *
 * Deliberately has no `WorkflowService` dependency: everything the builder
 * needs (the workflow itself, the node type catalog) is fetched client-side
 * from the REST API (item 3, plus the new node-types endpoint), which is
 * the same API surface any external integration would use. That keeps this
 * class a thin shell and avoids a second, PHP-side code path for reading
 * workflow data that could drift from what the REST API actually returns.
 *
 * Reachable only via a link (the workflows list's "Add New"/"Edit" actions)
 * — see `showInMenu()`.
 */
class BuilderPage implements AdminPage {

	/**
	 * Public so `WorkflowsPage` can build "Add New"/"Edit" links without
	 * needing an instantiated `BuilderPage` (see `WorkflowsPage::SLUG` for
	 * the same pattern used in reverse, for this page's back-to-list link).
	 */
	public const SLUG = 'wfa-builder';

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
		return __( 'Workflow Editor', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Workflow Editor', 'workflow-automate' );
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
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueueAssets(): void {
		$asset_file = WFA_PLUGIN_DIR . 'assets/builder/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// The React app hasn't been built (e.g. a git checkout without
			// `npm run build`). Failing quietly here would leave the admin
			// with a blank page and no clue why; surface it instead.
			add_action( 'admin_notices', array( $this, 'renderMissingBuildNotice' ) );

			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wfa-builder',
			WFA_PLUGIN_URL . 'assets/builder/build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// wp-scripts' MiniCssExtractPlugin config names the extracted
		// stylesheet "style-{entry}.css" (plus an auto-generated
		// "-rtl.css" companion), not "{entry}.css".
		if ( file_exists( WFA_PLUGIN_DIR . 'assets/builder/build/style-index.css' ) ) {
			wp_enqueue_style(
				'wfa-builder',
				WFA_PLUGIN_URL . 'assets/builder/build/style-index.css',
				array( 'wp-components' ),
				$asset['version']
			);
			wp_style_add_data( 'wfa-builder', 'rtl', 'replace' );
		}

		wp_add_inline_script(
			'wfa-builder',
			'var wfaBuilderSettings = ' . wp_json_encode( $this->bootstrapSettings() ) . ';',
			'before'
		);
	}

	/**
	 * @return array{workflowId: int, listUrl: string}
	 */
	private function bootstrapSettings(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which workflow to load; the REST API this feeds still re-checks capability on every request.
		$workflow_id = isset( $_GET['workflow'] ) ? absint( wp_unslash( $_GET['workflow'] ) ) : 0;

		return array(
			'workflowId' => $workflow_id,
			// Same namespace as WorkflowsPage, so no `use` import is needed.
			'listUrl' => admin_url( 'admin.php?page=' . WorkflowsPage::SLUG ),
		);
	}

	/**
	 * @return void
	 */
	public function renderMissingBuildNotice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Workflow Automate: the builder app has not been built yet. Run "npm install && npm run build" in the plugin directory.',
				'workflow-automate'
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ) );
		}

		echo '<div class="wrap wfa-admin-page">';
		echo '<div id="wfa-builder-root"></div>';
		echo '</div>';
	}
}
