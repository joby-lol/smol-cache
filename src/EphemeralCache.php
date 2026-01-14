<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

class EphemeralCache implements CacheInterface
{

    /**
     * Array of data, each key maps to its value.
     * @var array<string,mixed> $data
     */
    protected array $data = [];

    /**
     * Array of expirations, each key maps to its expiration timestamp.
     * @var array<string,int> $expires
     */
    protected array $expires = [];

    /**
     * Array of tags, each tag maps to an array of keys.
     * @var array<string,array<string>> $tags
     */
    protected array $tags = [];

    public function __construct(
        protected int $ttl = 30,
    ) {}

    /**
     * @inheritDoc
     */
    public function clear(string|array $tags, bool $recursive = false): static
    {
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $this->clear($tag, $recursive);
            }
        }
        else {
            // Handle recursive clear by checking all keys
            if ($recursive) {
                $tag = $tags;
                $tag_prefix = "$tag/";
                foreach ($this->tags as $existing_tag => $existing_tag_keys) {
                    if ($existing_tag === $tag || str_starts_with($existing_tag, $tag_prefix)) {
                        foreach ($existing_tag_keys as $key) {
                            unset($this->data[$key]);
                            unset($this->expires[$key]);
                        }
                        unset($this->tags[$existing_tag]);
                    }
                }
            }
            // Non-recursive clear is simpler
            else {
                $tag = $tags;
                if (array_key_exists($tag, $this->tags)) {
                    foreach ($this->tags[$tag] as $key) {
                        unset($this->data[$key]);
                        unset($this->expires[$key]);
                    }
                    unset($this->tags[$tag]);
                }
            }
        }
        return $this;
    }

    /**
     * Completely flush the cache, deleting all items.
     */
    public function flush(): static
    {
        $this->data = [];
        $this->expires = [];
        $this->tags = [];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key, bool $recursive = false): static
    {
        // build list of keys to delete, starting with the given key and adding more if recursive
        $keys_to_delete = [$key];
        if ($recursive) {
            foreach (array_keys($this->data) as $existing_key) {
                if (str_starts_with($existing_key, $key . '/')) {
                    $keys_to_delete[] = $existing_key;
                }
            }
        }
        // delete keys from data and expires
        foreach ($keys_to_delete as $k) {
            unset($this->data[$k]);
            unset($this->expires[$k]);
        }
        // clean up tag references
        foreach ($this->tags as $tag => &$tagged_keys) {
            $this->tags[$tag] = array_diff($tagged_keys, $keys_to_delete);
            if (empty($this->tags[$tag])) {
                unset($this->tags[$tag]);
            }
        }
        // return
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null, int|null $ttl = null, string|array $tags = []): mixed
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }
        else {
            return $this->set($key, $default, $ttl, $tags);
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data)
            && $this->expires[$key] > time();
    }

    /**
     * @inheritDoc
     */
    public function namespace(string $namespace, string|array $tags = [], int|null $ttl = null): CacheNamespace
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
        // if value is a callable, call it to get the actual value
        if (is_callable($value))
            $value = $value();
        // clear old tags for this key if they exist
        foreach ($this->tags as $tag => $tagged_keys) {
            $this->tags[$tag] = array_diff($tagged_keys, [$key]);
            if (empty($this->tags[$tag]))
                unset($this->tags[$tag]);
        }
        // set new data
        $this->data[$key] = $value;
        $this->expires[$key] = time() + ($ttl ?? $this->ttl);
        // set new tags
        $tags = is_array($tags) ? $tags : [$tags];
        foreach ($tags as $tag) {
            if (!array_key_exists($tag, $this->tags))
                $this->tags[$tag] = [];
            $this->tags[$tag][] = $key;
        }
        // return
        return $value;
    }

}
