<?php

/**
 * Plugin Name: Memcached
 * Description: Memcached backend for the WP Object Cache.
 * Version: 4.0.0
 * Author: Automattic
 * Plugin URI: https://wordpress.org/plugins/memcached/
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Users with setups where multiple installs share a common wp-config.php or $table_prefix
// can use this to guarantee uniqueness for the keys generated by this object cache
if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', '' );
}

require_once __DIR__ . '/includes/wp-object-cache.php';
require_once __DIR__ . '/includes/adapter-interface.php';
require_once __DIR__ . '/includes/memcached-adapter.php';
require_once __DIR__ . '/includes/memcache-adapter.php';
require_once __DIR__ . '/includes/stats.php';

/**
 * Sets up Object Cache Global and assigns it.
 *
 * @global WP_Object_Cache $wp_object_cache
 * @return void
 */
function wp_cache_init() {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$GLOBALS['wp_object_cache'] = new WP_Object_Cache();
}

/**
 * Adds data to the cache, if the cache key doesn't already exist.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key    The cache key to use for retrieval later.
 * @param mixed      $data   The data to add to the cache.
 * @param string     $group  Optional. The group to add the cache to. Enables the same key
 *                           to be used across groups. Default empty.
 * @param int        $expire Optional. When the cache data should expire, in seconds.
 *                           Default 0 (no expiration).
 * @return bool True on success, false if cache key and group already exist.
 */
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	// Falsey values for alloptions should never be cached.
	if ( 'alloptions' === $key && 'options' === $group && ! $data ) {
		return false;
	}

	/** @psalm-suppress RedundantCastGivenDocblockType */
	return $wp_object_cache->add( $key, $data, $group, (int) $expire );
}

/**
 * Adds multiple values to the cache in one call.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param array  $data   Array of keys and values to be set.
 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
 * @param int    $expire Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false if cache key and group already exist.
 * @psalm-param mixed[] $data
 */
function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	/** @psalm-suppress RedundantCastGivenDocblockType */
	return $wp_object_cache->add_multiple( $data, $group, (int) $expire );
}

/**
 * Replaces the contents of the cache with new data.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key    The key for the cache data that should be replaced.
 * @param mixed      $data   The new data to store in the cache.
 * @param string     $group  Optional. The group for the cache data that should be replaced.
 *                           Default empty.
 * @param int        $expire Optional. When to expire the cache contents, in seconds.
 *                           Default 0 (no expiration).
 * @return bool True if contents were replaced, false if original value does not exist.
 * @psalm-suppress RedundantCast
 */
function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, (int) $expire );
}

/**
 * Saves the data to the cache.
 *
 * Differs from wp_cache_add() and wp_cache_replace() in that it will always write data.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key    The cache key to use for retrieval later.
 * @param mixed      $data   The contents to store in the cache.
 * @param string     $group  Optional. Where to group the cache contents. Enables the same key
 *                           to be used across groups. Default empty.
 * @param int        $expire Optional. When to expire the cache contents, in seconds.
 *                           Default 0 (no expiration).
 * @return bool True on success, false on failure.
 */
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	if ( ! defined( 'WP_INSTALLING' ) ) {
		/** @psalm-suppress RedundantCastGivenDocblockType */
		return $wp_object_cache->set( $key, $data, $group, (int) $expire );
	}

	return $wp_object_cache->delete( $key, $group );
}

/**
 * Sets multiple values to the cache in one call.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param array  $data   Array of keys and values to be set.
 * @param string $group  Optional. Where the cache contents are grouped. Default empty.
 * @param int    $expire Optional. When to expire the cache contents, in seconds.
 *                       Default 0 (no expiration).
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false on failure.
 * @psalm-param mixed[] $data
 */
function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	global $wp_object_cache;

	if ( ! defined( 'WP_INSTALLING' ) ) {
		/** @psalm-suppress RedundantCastGivenDocblockType */
		return $wp_object_cache->set_multiple( $data, $group, (int) $expire );
	}

	return $wp_object_cache->delete_multiple( array_keys( $data ), $group );
}

/**
 * Retrieves the cache contents from the cache by key and group.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key   The key under which the cache contents are stored.
 * @param string     $group Optional. Where the cache contents are grouped. Default empty.
 * @param bool       $force Optional. Whether to force an update of the local cache
 *                          from the persistent cache. Default false.
 * @param bool       $found Optional. Whether the key was found in the cache (passed by reference).
 *                          Disambiguates a return of false, a storable value. Default null.
 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
 */
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	global $wp_object_cache;

	$value = apply_filters( 'pre_wp_cache_get', false, $key, $group, $force );
	if ( false !== $value ) {
		$found = true;
		return $value;
	}

	return $wp_object_cache->get( $key, $group, $force, $found );
}

/**
 * Retrieves multiple values from the cache in one call.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param array  $keys  Array of keys under which the cache contents are stored.
 * @param string $group Optional. Where the cache contents are grouped. Default empty.
 * @param bool   $force Optional. Whether to force an update of the local cache
 *                      from the persistent cache. Default false.
 * @return array Array of return values, grouped by key. Each value is either
 *               the cache contents on success, or false on failure.
 * @psalm-param (int|string)[] $keys
 * @psalm-return mixed[]
 */
function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	global $wp_object_cache;

	return $wp_object_cache->get_multiple( $keys, $group, $force );
}

/**
 * Retrieves multiple values from the cache in one call.
 * TODO: Deprecate
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param array<string, array<string|int>> $groups  Array of keys, indexed by group. Example: $groups['group-name'] = [ 'key1', 'key2' ]
 *
 * @return array Array of return values, with the full cache key as the index. Each value is either the cache contents on success, or false on failure.
 */
function wp_cache_get_multi( $groups ) {
	global $wp_object_cache;

	return $wp_object_cache->get_multi( $groups );
}

/**
 * Removes the cache contents matching key and group.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key   What the contents in the cache are called.
 * @param string     $group Optional. Where the cache contents are grouped. Default empty.
 * @return bool True on successful removal, false on failure.
 */
function wp_cache_delete( $key, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}

/**
 * Deletes multiple values from the cache in one call.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param array  $keys  Array of keys under which the cache to deleted.
 * @param string $group Optional. Where the cache contents are grouped. Default empty.
 * @return bool[] Array of return values, grouped by key. Each value is either
 *                true on success, or false if the contents were not deleted.
 * @psalm-param (int|string)[] $keys
 */
function wp_cache_delete_multiple( array $keys, $group = '' ) {
	global $wp_object_cache;

	return $wp_object_cache->delete_multiple( $keys, $group );
}

/**
 * Increments numeric cache item's value.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key    The key for the cache contents that should be incremented.
 * @param int        $offset Optional. The amount by which to increment the item's value. Default 1.
 * @param string     $group  Optional. The group the key is in. Default empty.
 * @return int|false The item's new value on success, false on failure.
 */
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	global $wp_object_cache;

	/** @psalm-suppress RedundantCastGivenDocblockType */
	return $wp_object_cache->incr( $key, (int) $offset, $group );
}

/**
 * Decrements numeric cache item's value.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int|string $key    The cache key to decrement.
 * @param int        $offset Optional. The amount by which to decrement the item's value. Default 1.
 * @param string     $group  Optional. The group the key is in. Default empty.
 * @return int|false The item's new value on success, false on failure.
 */
function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	global $wp_object_cache;

	/** @psalm-suppress RedundantCastGivenDocblockType */
	return $wp_object_cache->decr( $key, (int) $offset, $group );
}

/**
 * Removes all cache items.
 *
 * @see WP_Object_Cache::flush()
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @return bool True on success, false on failure.
 */
function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

/**
 * Removes all cache items from the in-memory runtime cache.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @return bool True on success, false on failure.
 */
function wp_cache_flush_runtime() {
	global $wp_object_cache;

	return $wp_object_cache->flush_runtime();
}

/**
 * Unsupported: Removes all cache items in a group, if the object cache implementation supports it.
 *
 * Before calling this function, always check for group flushing support using the
 * `wp_cache_supports( 'flush_group' )` function.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param string $_group Name of group to remove from cache.
 * @return bool True if group was flushed, false otherwise.
 */
function wp_cache_flush_group( $_group ) {
	global $wp_object_cache;

	return $wp_object_cache->flush_group( $_group );
}

/**
 * Determines whether the object cache implementation supports a particular feature.
 *
 * @param string $feature Name of the feature to check for. Possible values include:
 *                        'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple',
 *                        'flush_runtime', 'flush_group'.
 * @return bool True if the feature is supported, false otherwise.
 */
function wp_cache_supports( $feature ) {
	switch ( $feature ) {
		case 'add_multiple':
		case 'set_multiple':
		case 'get_multiple':
		case 'delete_multiple':
		case 'flush_runtime':
			return true;

		default:
			return false;
	}
}

/**
 * Closes the cache.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @return void
 */
function wp_cache_close() {
	global $wp_object_cache;

	$wp_object_cache->close();
}

/**
 * Adds a group or set of groups to the list of global groups.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param string|string[] $groups A group or an array of groups to add.
 * @return void
 */
function wp_cache_add_global_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param string|string[] $groups A group or an array of groups to add.
 * @return void
 */
function wp_cache_add_non_persistent_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups( $groups );
}

/**
 * Switches the internal blog ID.
 * This changes the blog id used to create keys in blog specific groups.
 *
 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
 *
 * @param int $blog_id Site ID.
 * @return void
 */
function wp_cache_switch_to_blog( $blog_id ) {
	global $wp_object_cache;

	/** @psalm-suppress RedundantCastGivenDocblockType */
	$wp_object_cache->switch_to_blog( (int) $blog_id );
}
