<?php
/**
 * Global plugin settings.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

use DragwybAgentFlow\Plugin\Core\Options;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads/writes the plugin's single `global_settings` option (roadmap item
 * 10), plus the pre-existing standalone `remove_data_on_uninstall` option
 * (added in item 1, never surfaced in a UI until now). One option, rather
 * than one row per setting, matching what `docs/internal/architecture.md`
 * §2.3 already documented for it.
 *
 * Every other service in this codebase (`WorkflowService`, etc.) is the
 * thing request-facing code (REST, admin) calls through; this class fills
 * that same role for settings, so `SettingsPage`/`SettingsController` never
 * touch `Options` directly.
 */
class SettingsService {

	public const ON_FAILURE_STOP = 'stop';

	public const ON_FAILURE_CONTINUE = 'continue';

	/**
	 * @var string[]
	 */
	public const VALID_ON_FAILURE = array( self::ON_FAILURE_STOP, self::ON_FAILURE_CONTINUE );

	public const MIN_RETENTION_DAYS = 0;

	public const MAX_RETENTION_DAYS = 3650;

	public const DEFAULT_RETENTION_DAYS = 14;

	private const OPTION_NAME = 'global_settings';

	/**
	 * Every stored setting, merged with defaults for any key never saved
	 * (a fresh install, or one that predates a later setting being added).
	 *
	 * @return array{on_node_failure: string, display_timestamps_in_utc: bool, retention_days: int, background_execution_enabled: bool, require_webhook_signing: bool}
	 */
	public function all(): array {
		return array_merge( $this->defaults(), (array) Options::get( self::OPTION_NAME, array() ) );
	}

	/**
	 * @return array{on_node_failure: string, display_timestamps_in_utc: bool, retention_days: int, background_execution_enabled: bool, require_webhook_signing: bool}
	 */
	private function defaults(): array {
		return array(
			'on_node_failure'              => self::ON_FAILURE_STOP,
			'display_timestamps_in_utc'    => false,
			'retention_days'               => self::DEFAULT_RETENTION_DAYS,
			'background_execution_enabled' => true,
			'require_webhook_signing' => true,
		);
	}

	/**
	 * @return string One of self::VALID_ON_FAILURE.
	 */
	public function onNodeFailure(): string {
		return (string) $this->all()['on_node_failure'];
	}

	/**
	 * Whether WorkflowExecutionService::executeNodes() should keep running
	 * the remaining nodes after one fails, instead of stopping immediately
	 * (the "fail fast" default from roadmap item 7).
	 *
	 * @return bool
	 */
	public function shouldContinueOnFailure(): bool {
		return self::ON_FAILURE_CONTINUE === $this->onNodeFailure();
	}

	/**
	 * @return bool
	 */
	public function displayTimestampsInUtc(): bool {
		return (bool) $this->all()['display_timestamps_in_utc'];
	}

	/**
	 * @return int Days of finished-run history to keep; 0 means "keep forever" (see RunRetentionService).
	 */
	public function retentionDays(): int {
		return (int) $this->all()['retention_days'];
	}

	/**
	 * Whether a live trigger firing should queue its run for WP-Cron
	 * (roadmap item 8's background path) or execute it synchronously on
	 * the triggering request instead (see WorkflowTriggerBinder). Default
	 * true — background is the safer default, since it never blocks an
	 * unrelated request.
	 *
	 * @return bool
	 */
	public function backgroundExecutionEnabled(): bool {
		return (bool) $this->all()['background_execution_enabled'];
	}

	/**
	 * Whether every inbound webhook must have a signing secret configured
	 * (roadmap item 13). When true, ingress rejects webhooks that have no
	 * secret, and the admin create/edit forms refuse to save without one.
	 * Default false — signing remains optional per-webhook unless an
	 * operator turns this on.
	 *
	 * @return bool
	 */
	public function requireWebhookSigning(): bool {
		return (bool) $this->all()['require_webhook_signing'];
	}

	/**
	 * Whether Uninstaller::uninstall() should remove all plugin data. This
	 * reads the same standalone option Uninstaller itself checks directly
	 * — surfaced here too so the admin screen has one place to read
	 * current state from, exactly like every other setting.
	 *
	 * @return bool
	 */
	public function removeDataOnUninstall(): bool {
		return (bool) Options::get( 'remove_data_on_uninstall', false );
	}

	/**
	 * @param string $on_node_failure           One of self::VALID_ON_FAILURE; anything else falls back to ON_FAILURE_STOP.
	 * @param bool   $display_timestamps_in_utc New value.
	 *
	 * @return void
	 */
	public function updateGeneral( string $on_node_failure, bool $display_timestamps_in_utc ): void {
		$current                              = $this->all();
		$current['on_node_failure']           = in_array( $on_node_failure, self::VALID_ON_FAILURE, true ) ? $on_node_failure : self::ON_FAILURE_STOP;
		$current['display_timestamps_in_utc'] = $display_timestamps_in_utc;

		Options::update( self::OPTION_NAME, $current );
	}

	/**
	 * @param int $days Clamped to [MIN_RETENTION_DAYS, MAX_RETENTION_DAYS].
	 *
	 * @return void
	 */
	public function updateRetentionDays( int $days ): void {
		$current                   = $this->all();
		$current['retention_days'] = max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, $days ) );

		Options::update( self::OPTION_NAME, $current );
	}

	/**
	 * @param bool $background_execution_enabled New value.
	 *
	 * @return void
	 */
	public function updateBackgroundExecutionEnabled( bool $background_execution_enabled ): void {
		$current                                 = $this->all();
		$current['background_execution_enabled'] = $background_execution_enabled;

		Options::update( self::OPTION_NAME, $current );
	}

	/**
	 * @param bool $require_webhook_signing New value.
	 *
	 * @return void
	 */
	public function updateRequireWebhookSigning( bool $require_webhook_signing ): void {
		$current                            = $this->all();
		$current['require_webhook_signing'] = $require_webhook_signing;

		Options::update( self::OPTION_NAME, $current );
	}

	/**
	 * Updates the standalone option Uninstaller reads. Deliberately a
	 * separate method/option (not folded into the `global_settings`
	 * array): Uninstaller::OWNED_OPTIONS already lists it individually,
	 * and it predates this settings screen (see roadmap item 1) — moving
	 * it now would be a needless migration for no behavioral benefit.
	 *
	 * @param bool $remove_data_on_uninstall New value.
	 *
	 * @return void
	 */
	public function updateRemoveDataOnUninstall( bool $remove_data_on_uninstall ): void {
		Options::update( 'remove_data_on_uninstall', $remove_data_on_uninstall );
	}
}
