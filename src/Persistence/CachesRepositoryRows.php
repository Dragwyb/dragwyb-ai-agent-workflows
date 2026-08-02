<?php
/**
 * Shared object-cache helpers for repositories.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Persistence;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around `wp_cache_*()` for classes that define a
 * `CACHE_GROUP` constant. Centralizes the group argument so every
 * repository's cache calls stay consistent, and gives one place to adjust
 * behavior (e.g. a TTL) later without touching every call site.
 *
 * Only single-row-by-key and parent-scoped-collection reads use this —
 * paginated/filtered list queries and queries that must always read live
 * data (e.g. atomic queue claiming) intentionally do not, since caching
 * those would require a cache key per filter+page combination invalidated
 * on nearly every write, for little to no real hit rate.
 */
trait CachesRepositoryRows {

	/**
	 * @param string $key Cache key, unique within static::CACHE_GROUP.
	 *
	 * @return mixed False on a cache miss, the cached value otherwise.
	 */
	private function cacheGet( string $key ) {
		return wp_cache_get( $key, static::CACHE_GROUP );
	}

	/**
	 * @param array<int, string> $keys Cache keys, unique within static::CACHE_GROUP.
	 *
	 * @return array<string, mixed> Keyed by the requested cache key; a miss's value is `false`.
	 */
	private function cacheGetMultiple( array $keys ): array {
		return wp_cache_get_multiple( $keys, static::CACHE_GROUP );
	}

	/**
	 * @param string $key   Cache key, unique within static::CACHE_GROUP.
	 * @param mixed  $value Value to cache.
	 *
	 * @return void
	 */
	private function cacheSet( string $key, $value ): void {
		wp_cache_set( $key, $value, static::CACHE_GROUP );
	}

	/**
	 * @param string $key Cache key to invalidate.
	 *
	 * @return void
	 */
	private function cacheDelete( string $key ): void {
		wp_cache_delete( $key, static::CACHE_GROUP );
	}
}
