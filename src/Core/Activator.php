<?php
/**
 * Plugin activation handler.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Core;

use AIAWA\Plugin\Database\MigrationRunner;
use AIAWA\Plugin\Database\SchemaMigrations;
use AIAWA\Plugin\Service\BackgroundRunner;
use AIAWA\Plugin\Service\RunRetentionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs once when the plugin is activated.
 *
 * Responsible for activation-time concerns: verifying the environment,
 * creating/updating the database schema, granting plugin capabilities to
 * the administrator role, stamping installation metadata, and scheduling
 * the recurring background-queue cron event.
 */
class Activator {

	/**
	 * Activation callback registered via register_activation_hook().
	 *
	 * @return void
	 */
	public static function activate(): void {
		$requirements = Requirements::check();

		if ( is_wp_error( $requirements ) ) {
			deactivate_plugins( AIAWA_PLUGIN_BASENAME );

			wp_die(
				esc_html( implode( ' ', $requirements->get_error_messages() ) ),
				esc_html__( 'Plugin activation error', 'workflow-automate' ),
				array( 'back_link' => true )
			);
		}

		( new MigrationRunner( SchemaMigrations::all() ) )->run();

		Capabilities::grantToAdministrator();

		if ( false === Options::get( 'installed_at' ) ) {
			Options::add( 'installed_at', time(), true );
		}

		Options::update( 'db_version', AIAWA_VERSION );

		self::scheduleBackgroundQueue();
		self::scheduleRetentionPruning();
	}

	/**
	 * Schedules the recurring cron event BackgroundRunner runs on.
	 *
	 * The `cron_schedules` filter that defines BackgroundRunner::CRON_SCHEDULE
	 * is normally registered by Plugin::load() on `plugins_loaded`, but
	 * activation happens via WordPress re-including this plugin's main file
	 * and firing `activate_{plugin}` directly from within the plugins
	 * admin screen's own request — a point at which `plugins_loaded` has
	 * already fired for this request without this (not-yet-active) plugin
	 * present. Without registering the filter here first, wp_schedule_event()
	 * would not recognize the custom schedule and would fail silently.
	 *
	 * @return void
	 */
	private static function scheduleBackgroundQueue(): void {
		add_filter( 'cron_schedules', array( BackgroundRunner::class, 'registerCronSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- interval is intentionally short; see BackgroundRunner::CRON_SCHEDULE docblock.

		if ( ! wp_next_scheduled( BackgroundRunner::CRON_HOOK ) ) {
			wp_schedule_event( time(), BackgroundRunner::CRON_SCHEDULE, BackgroundRunner::CRON_HOOK );
		}
	}

	/**
	 * Schedules the daily cron event RunRetentionService runs on. Uses
	 * WordPress's built-in `daily` schedule, so — unlike
	 * scheduleBackgroundQueue() — there is no custom `cron_schedules`
	 * filter to register first.
	 *
	 * @return void
	 */
	private static function scheduleRetentionPruning(): void {
		if ( ! wp_next_scheduled( RunRetentionService::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', RunRetentionService::CRON_HOOK );
		}
	}
}
