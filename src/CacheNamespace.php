<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

/**
 * A namespaced cache that prefixes all keys with a given namespace.
 */
class CacheNamespace implements CacheInterface
{

    protected CacheInterface $driver;

    protected string $namespace;

    protected string $prefix;

    /** @var string[] $tags */
    protected array $tags = [];

    protected int|null $ttl = null;

    /**
     * @param string|string[] $tags
     */
    public function __construct(
        CacheInterface $driver,
        string $namespace,
        string|array $tags = [],
        int|null $ttl = null,
    )
    {
        $this->driver = $driver;
        $this->namespace = $namespace;
        if ($namespace !== '')
            $this->prefix = $namespace . '/';
        else
            $this->prefix = '';
        $this->tags = is_array($tags) ? $tags : [$tags];
        $this->ttl = $ttl;
    }

    /**
     * WARNING: This will clear all items associated with the tags in the entire underlying cache, not just those in this namespace.
     * 
     * Clear all items from the cache that are associated with the given tag or tags.
     * 
     * @param string|string[] $tags
     */
    public function clear(string|array $tags, bool $recursive = false): static
    {
        $this->driver->clear($tags, $recursive);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key, bool $recursive = false): static
    {
        $this->driver->delete(
            $this->key($key),
            $recursive,
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null, int|null $ttl = null, string|array $tags = []): mixed
    {
        return $this->driver->get(
            $this->key($key),
            $default,
            $ttl ?? $this->ttl,
            array_merge($this->tags, is_array($tags) ? $tags : [$tags]),
        );
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->driver->has(
            $this->key($key),
        );
    }

    /**
     * @inheritDoc
     */
    public function namespace(string $namespace, string|array $tags = [], int|null $ttl = null): CacheInterface
    {
        return new CacheNamespace(
            $this,
            $namespace,
            $tags,
            $ttl,
        );
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int|null $ttl = null, string|array $tags = []): mixed
    {
        $this->driver->set(
            $this->key($key),
            $value,
            $ttl ?? $this->ttl,
            $this->addTags($tags),
        );
        return $this;
    }

    /**
     * Prefix the given key with the namespace prefix.
     */
    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Merge the given tags with the namespace tags, ensuring uniqueness.
     * 
     * @param string|string[] $tags
     * @return string[]
     */
    protected function addTags(string|array $tags): array
    {
        $tags = is_array($tags) ? $tags : [$tags];
        return array_unique(array_merge($this->tags, $tags));
    }

}
