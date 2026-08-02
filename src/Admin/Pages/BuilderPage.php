<?php
/**
 * Workflow builder admin page.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin\Pages;

use AIAWA\Plugin\Admin\AdminPage;
use AIAWA\Plugin\Core\Capabilities;
use AIAWA\Plugin\Service\GoogleOAuthService;

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
	public const SLUG = 'aiawa-builder';

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
		return __( 'Workflow Editor', 'dragwyb-agentflow' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menuTitle(): string {
		return __( 'Workflow Editor', 'dragwyb-agentflow' );
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
		$asset_file = AIAWA_PLUGIN_DIR . 'assets/builder/build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// The React app hasn't been built (e.g. a git checkout without
			// `npm run build`). Failing quietly here would leave the admin
			// with a blank page and no clue why; surface it instead.
			add_action( 'admin_notices', array( $this, 'renderMissingBuildNotice' ) );

			return;
		}

		$asset   = require $asset_file;
		$version = isset( $asset['version'] ) ? (string) $asset['version'] : null;
		// Bust browser caches when the built bundle changes on disk.
		$built_js = AIAWA_PLUGIN_DIR . 'assets/builder/build/index.js';
		if ( file_exists( $built_js ) ) {
			$version = (string) filemtime( $built_js );
		}

		wp_enqueue_style(
			'aiawa-builder-font',
			'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
			array(),
			AIAWA_VERSION
		);

		wp_enqueue_script(
			'aiawa-builder',
			AIAWA_PLUGIN_URL . 'assets/builder/build/index.js',
			$asset['dependencies'],
			$version,
			true
		);

		// wp-scripts' MiniCssExtractPlugin config names the extracted
		// stylesheet "style-{entry}.css" (plus an auto-generated
		// "-rtl.css" companion), not "{entry}.css".
		if ( file_exists( AIAWA_PLUGIN_DIR . 'assets/builder/build/style-index.css' ) ) {
			wp_enqueue_style(
				'aiawa-builder',
				AIAWA_PLUGIN_URL . 'assets/builder/build/style-index.css',
				array( 'wp-components', 'aiawa-builder-font' ),
				$version
			);
			wp_style_add_data( 'aiawa-builder', 'rtl', 'replace' );
		}

		wp_add_inline_script(
			'aiawa-builder',
			'var aiawaBuilderSettings = ' . wp_json_encode( $this->bootstrapSettings() ) . ';',
			'before'
		);
	}

	/**
	 * @return array{workflowId: int, listUrl: string, connectionsUrl: string, aiCredentialsUrl: string, googleCredentialsUrl: string, googleOAuthCallbackUrl: string}
	 */
	private function bootstrapSettings(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route parameter selecting which workflow to load; the REST API this feeds still re-checks capability on every request.
		$workflow_id = isset( $_GET['workflow'] ) ? absint( wp_unslash( $_GET['workflow'] ) ) : 0;

		return array(
			'workflowId'             => $workflow_id,
			// Same namespace as WorkflowsPage, so no `use` import is needed.
			'listUrl'                => admin_url( 'admin.php?page=' . WorkflowsPage::SLUG ),
			'connectionsUrl'         => admin_url( 'admin.php?page=' . ConnectionsPage::SLUG ),
			'aiCredentialsUrl'       => \AIAWA\Plugin\Service\Ai\AiClientBootstrap::credentialsUrl(),
			'googleCredentialsUrl'   => GoogleOAuthService::GOOGLE_CREDENTIALS_URL,
			'googleOAuthCallbackUrl' => rest_url( 'aiawa/v1/oauth/google/callback' ),
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
				'dragwyb-agentflow'
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'dragwyb-agentflow' ) );
		}

		echo '<div class="wrap aiawa-admin-page aiawa-builder-page">';
		$this->renderImportNotice();
		echo '<div id="aiawa-builder-root"></div>';
		echo '</div>';
	}

	/**
	 * Shows a one-shot notice after list-page JSON import redirects here.
	 *
	 * @return void
	 */
	private function renderImportNotice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display selector.
		$key = isset( $_GET['aiawa_notice'] ) ? sanitize_key( wp_unslash( $_GET['aiawa_notice'] ) ) : '';

		if ( 'imported' !== $key ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Workflow imported from JSON.', 'dragwyb-agentflow' )
		);
	}
}
