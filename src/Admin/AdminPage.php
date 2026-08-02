<?php
/**
 * Admin page contract.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implemented by every screen registered against the plugin's admin menu.
 *
 * Keeping this as a small contract (rather than adding methods directly to
 * `Menu`) is what lets later roadmap increments (Runs, Connections,
 * Webhooks, Settings) each add their own page class without modifying the
 * menu-registration code itself.
 */
interface AdminPage {

	/**
	 * The page's unique menu slug.
	 *
	 * @return string
	 */
	public function slug(): string;

	/**
	 * The `<title>` and page heading text.
	 *
	 * @return string
	 */
	public function pageTitle(): string;

	/**
	 * The text shown in the admin menu itself.
	 *
	 * @return string
	 */
	public function menuTitle(): string;

	/**
	 * The capability required to view this page.
	 *
	 * @return string
	 */
	public function capability(): string;

	/**
	 * Renders the page body. Called inside WordPress's admin page callback;
	 * implementations are responsible for escaping all output themselves.
	 *
	 * @return void
	 */
	public function render(): void;

	/**
	 * Enqueues any CSS/JS this page needs. Called only when the current
	 * admin screen is this page (see Menu::enqueueAssets()), so
	 * implementations never need to check the screen themselves.
	 *
	 * @return void
	 */
	public function enqueueAssets(): void;

	/**
	 * Whether this page should appear as a row under the plugin's admin menu.
	 *
	 * When false, Menu registers the page under options.php so it remains a
	 * valid, capability-checked admin screen (reachable via admin.php?page=…)
	 * without adding a visible row under the plugin menu. Do not implement
	 * "hidden" pages by calling remove_submenu_page() — WordPress denies
	 * access to pages that are no longer present in $submenu.
	 *
	 * @return bool
	 */
	public function showInMenu(): bool;
}
