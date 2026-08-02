<?php
/**
 * Optional action grouping for the builder palette.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Domain\Contracts;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actions that belong to a palette app (e.g. WordPress) and optional
 * sub-group implement this so the node-types REST response can expose
 * `app` / `group` / `group_label` for the builder picker.
 */
interface ActionGroupInterface {

	/**
	 * Palette app id (e.g. `wordpress`).
	 */
	public function app(): string;

	/**
	 * Sub-group id within the app (e.g. `user`, `post`).
	 */
	public function group(): string;

	/**
	 * Human-readable sub-group label.
	 */
	public function groupLabel(): string;
}
