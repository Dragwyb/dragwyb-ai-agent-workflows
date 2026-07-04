<?php
/**
 * Admin menu bootstrap.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Admin;

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

		$hook = add_menu_page(
			$first->pageTitle(),
			__( 'Workflow Automate', 'workflow-automate' ),
			$first->capability(),
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
			$hook = add_submenu_page(
				$first->slug(),
				$page->pageTitle(),
				$page->menuTitle(),
				$page->capability(),
				$page->slug(),
				array( $this, 'renderCurrentPage' )
			);

			$this->registerHook( $hook, $page );

			// Still fully registered (route + capability check both apply);
			// only its row in the visible menu is removed. See
			// AdminPage::showInMenu() for why a page would opt out.
			if ( ! $page->showInMenu() ) {
				remove_submenu_page( $first->slug(), $page->slug() );
			}
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
	 * @return void
	 */
	public function renderCurrentPage(): void {
		$requested_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing, not a state change.

		foreach ( $this->pages as $page ) {
			if ( $page->slug() === $requested_slug ) {
				$page->render();

				return;
			}
		}
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
