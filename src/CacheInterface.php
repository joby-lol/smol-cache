<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

interface CacheInterface
{

    /**
     * Get an item from the cache if it exists, otherwise set it to the default value. The default value may be a callable, in which case it will be executed and its return value cached and returned. If $ttl is provided, it specifies the time-to-live in seconds, overriding the default value for this cache. If $tags are provided, they will be associated with the cached item for later clearing.
     * 
     * @template T of mixed
     * @param string|string[] $tags
     * @param (callable(mixed...):T)|null $default
     * @return ($default is null ? mixed : T)
     */
    public function get(
        string $key,
        callable|null $default = null,
        int|null $ttl = null,
        string|array $tags = [],
    ): mixed;

    /**
     * Return a namespaced version of this cache. All keys set or retrieved through the returned instance will be prefixed with the given namespace and a slash delimiter.
     * 
     * For example, if the namespace is "user/123", then setting a key "profile" will actually set the key "user/123/profile" in the underlying cache.
     * 
     * If tags are provided, they will be associated with all items set through the returned instance for later clearing.
     * 
     * A TTL may also be provided, which will override the default TTL for all items set through the returned instance.
     * 
     * @param string|string[] $tags
     */
    public function namespace(
        string $namespace,
        string|array $tags = [],
        int|null $ttl = null,
    ): CacheInterface;

    /**
     * Set an item in the cache. $value may be a callable, in which case it will be executed and its return value cached. If $ttl is provided, it specifies the time-to-live in seconds, overriding the default value for this cache. If $tags are provided, they will be associated with the cached item for later clearing.
     * 
     * Returns the set value.
     * 
     * @param string|string[] $tags
     */
    public function set(
        string $key,
        mixed $value,
        int|null $ttl = null,
        string|array $tags = [],
    ): static;

    /**
     * Check if an item exists in the cache, and is not expired.
     */
    public function has(
        string $key,
    ): bool;

    /**
     * Delete an item from the cache. If $recursive is true, delete all items with keys that start with the given key and a slash delimiter. For example, deleting "user/123" with $recursive true would also delete "user/123/profile" and "user/123/settings".
     */
    public function delete(
        string $key,
        bool $recursive = false,
    ): static;

    /**
     * Clear all items from the cache that are associated with the given tag or tags.
     * 
     * @param string|string[] $tags
     */
    public function clear(
        string|array $tags,
        bool $recursive = false,
    ): static;

}
