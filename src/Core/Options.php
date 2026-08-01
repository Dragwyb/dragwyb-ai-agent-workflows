<?php
/**
 * Prefixed options helper.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Core;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the WordPress options API that applies the plugin's
 * option name prefix consistently, so call sites never hardcode it.
 */
class Options {

	public const PREFIX = 'aiawa_option_';

	/**
	 * Retrieves a plugin option.
	 *
	 * @param string $name    Unprefixed option name.
	 * @param mixed  $default Value to return if the option is not set.
	 *
	 * @return mixed
	 */
	public static function get( string $name, $default = false ) {
		return get_option( self::PREFIX . $name, $default );
	}

	/**
	 * Adds a plugin option if it does not already exist.
	 *
	 * @param string $name     Unprefixed option name.
	 * @param mixed  $value    Option value.
	 * @param bool   $autoload Whether WordPress should autoload the option.
	 *
	 * @return bool True on success, false on failure or if the option already exists.
	 */
	public static function add( string $name, $value, bool $autoload = false ): bool {
		return add_option( self::PREFIX . $name, $value, '', $autoload ? 'yes' : 'no' );
	}

	/**
	 * Adds or updates a plugin option.
	 *
	 * @param string $name  Unprefixed option name.
	 * @param mixed  $value Option value.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function update( string $name, $value ): bool {
		return update_option( self::PREFIX . $name, $value );
	}

	/**
	 * Deletes a plugin option.
	 *
	 * @param string $name Unprefixed option name.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $name ): bool {
		return delete_option( self::PREFIX . $name );
	}
}
