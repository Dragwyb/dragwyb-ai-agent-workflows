<?php
/**
 * Admin menu bootstrap.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Admin;

use AIAWA\Plugin\Core\Capabilities;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's top-level admin menu and every page attached to
 * it, and enqueues each page's assets only when that specific page is the
 * one being viewed (Section 6 performance requirement: never enqueue
 * plugin-wide).
 *
 * New screens are added by appending an `AdminPage` to the array passed
 * into the constructor — this class never needs to change as later
 * roadmap increments (Runs, Connections, Webhooks, Settings) add pages.
 */
class Menu {

	/**
	 * @var AdminPage[]
	 */
	private array $pages;

	/**
	 * Maps a registered screen's hook suffix to the page that owns it.
	 *
	 * @var array<string, AdminPage>
	 */
	private array $hookSuffixes = array();

	/**
	 * @param AdminPage[] $pages Ordered list of pages; the first becomes the
	 *                           top-level menu entry.
	 */
	public function __construct( array $pages ) {
		$this->pages = $pages;
	}

	/**
	 * Hooks menu and asset registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Registers the top-level menu and every page as a submenu entry.
	 *
	 * @return void
	 */
	public function registerMenu(): void {
		if ( array() === $this->pages ) {
			return;
		}

		$first = $this->pages[0];

		// Top-level menu uses ACCESS (implied by any granular cap, and by
		// manage_options) so a user granted only e.g. aiawa_manage_runs still
		// sees the plugin menu; individual submenu rows keep each page's
		// own capability so unauthorized items stay hidden.
		$hook = add_menu_page(
			$first->pageTitle(),
			__( 'Workflow Automate', 'workflow-automate' ),
			Capabilities::ACCESS,
			$first->slug(),
			array( $this, 'renderCurrentPage' ),
			'dashicons-randomize',
			26
		);

		$this->registerHook( $hook, $first );

		// Re-registers the first page as its own submenu entry, using the
		// *same* slug as the top-level page above (the standard WordPress
		// technique for giving that first submenu row its own label instead
		// of the auto-generated duplicate of the top-level menu title).
		$hook = add_submenu_page(
			$first->slug(),
			$first->pageTitle(),
			$first->menuTitle(),
			$first->capability(),
			$first->slug(),
			array( $this, 'renderCurrentPage' )
		);

		$this->registerHook( $hook, $first );

		foreach ( array_slice( $this->pages, 1 ) as $page ) {
			// Pages that should not appear in the plugin menu must still stay
			// in WordPress's $submenu so user_can_access_admin_page() allows
			// them. Registering under options.php is the standard pattern for
			// a capability-checked admin screen with no menu row. Do not use
			// remove_submenu_page() — that removes the page from $submenu and
			// causes "Sorry, you are not allowed to access this page."
			$parent_slug = $page->showInMenu() ? $first->slug() : 'options.php';

			$hook = add_submenu_page(
				$parent_slug,
				$page->pageTitle(),
				$page->menuTitle(),
				$page->capability(),
				$page->slug(),
				array( $this, 'renderCurrentPage' )
			);

			$this->registerHook( $hook, $page );
		}
	}

	/**
	 * @param string|false $hook Return value of add_menu_page()/add_submenu_page().
	 * @param AdminPage    $page The page registered at that hook.
	 *
	 * @return void
	 */
	private function registerHook( $hook, AdminPage $page ): void {
		if ( is_string( $hook ) && '' !== $hook ) {
			$this->hookSuffixes[ $hook ] = $page;
		}
	}

	/**
	 * Renders whichever page matches the current `$_GET['page']` slug.
	 *
	 * When the requested page exists but the user lacks its capability
	 * (common when clicking the top-level menu, which always opens the
	 * first page's slug, while the user only has e.g. Runs access),
	 * redirects to the first menu-visible page they *can* access rather
	 * than showing a dead-end "not allowed" screen.
	 *
	 * @return void
	 */
	public function renderCurrentPage(): void {
		$requested_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing, not a state change.

		foreach ( $this->pages as $page ) {
			if ( $page->slug() !== $requested_slug ) {
				continue;
			}

			if ( current_user_can( $page->capability() ) ) {
				$page->render();

				return;
			}

			break;
		}

		foreach ( $this->pages as $page ) {
			if ( $page->showInMenu() && current_user_can( $page->capability() ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $page->slug() ) );
				exit;
			}
		}

		wp_die( esc_html__( 'You are not allowed to access this page.', 'workflow-automate' ), 403 );
	}

	/**
	 * Enqueues the current screen's page assets, if any.
	 *
	 * @param string $hook_suffix Current admin screen's hook suffix.
	 *
	 * @return void
	 */
	public function enqueueAssets( string $hook_suffix ): void {
		if ( ! isset( $this->hookSuffixes[ $hook_suffix ] ) ) {
			return;
		}

		$this->hookSuffixes[ $hook_suffix ]->enqueueAssets();
	}
}
