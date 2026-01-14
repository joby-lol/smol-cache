<?php

namespace Joby\Smol\Cache;

use PHPUnit\Framework\TestCase;

class SqliteCacheTest extends TestCase
{

    private array $dbPaths = [];

    protected function tearDown(): void
    {
        // Clean up all database files created during this test
        foreach ($this->dbPaths as $dbPath) {
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            // Clean up WAL files if they exist
            if (file_exists($dbPath . '-wal')) {
                unlink($dbPath . '-wal');
            }
            if (file_exists($dbPath . '-shm')) {
                unlink($dbPath . '-shm');
            }
        }
        $this->dbPaths = [];
    }

    private function createCache(int $ttl = 30, int $cleanup_odds = 0): SqliteCache
    {
        $dbPath = sys_get_temp_dir() . '/test_cache_' . uniqid() . '.db';
        $this->dbPaths[] = $dbPath;
        return new SqliteCache($dbPath, ttl: $ttl, cleanup_odds: $cleanup_odds);
    }

    public function test_set_and_get(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value');

        $this->assertEquals('value', $cache->get('key'));
    }

    public function test_set_returns_value(): void
    {
        $cache = $this->createCache();
        $result = $cache->set('key', 'value');

        $this->assertEquals('value', $result);
    }

    public function test_get_nonexistent_returns_null(): void
    {
        $cache = $this->createCache();
        $this->assertNull($cache->get('nonexistent'));
    }

    public function test_get_with_default(): void
    {
        $cache = $this->createCache();
        $result = $cache->get('missing', 'default');

        $this->assertEquals('default', $result);
        $this->assertTrue($cache->has('missing'));
        $this->assertEquals('default', $cache->get('missing'));
    }

    public function test_get_with_callable_default(): void
    {
        $cache = $this->createCache();
        $called = false;

        $result = $cache->get('missing', function () use (&$called) {
            $called = true;
            return 'computed';
        });

        $this->assertTrue($called);
        $this->assertEquals('computed', $result);
        $this->assertEquals('computed', $cache->get('missing'));
    }

    public function test_set_with_callable(): void
    {
        $cache = $this->createCache();
        $cache->set('key', fn() => 'computed');

        $this->assertEquals('computed', $cache->get('key'));
    }

    public function test_has(): void
    {
        $cache = $this->createCache();
        $this->assertFalse($cache->has('key'));

        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));
    }

    public function test_ttl_expiration(): void
    {
        $cache = $this->createCache(ttl: 1);
        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));

        sleep(2);

        $this->assertFalse($cache->has('key'));
        $this->assertNull($cache->get('key'));
    }

    public function test_custom_ttl(): void
    {
        $cache = $this->createCache(ttl: 100);
        $cache->set('key', 'value', 1);

        $this->assertTrue($cache->has('key'));

        sleep(2);

        $this->assertFalse($cache->has('key'));
    }

    public function test_delete(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));

        $cache->delete('key');

        $this->assertFalse($cache->has('key'));
    }

    public function test_delete_recursive(): void
    {
        $cache = $this->createCache();
        $cache->set('parent', 'value1');
        $cache->set('parent/child1', 'value2');
        $cache->set('parent/child2', 'value3');
        $cache->set('parent/child2/grandchild', 'value4');
        $cache->set('other', 'value5');

        $cache->delete('parent', true);

        $this->assertFalse($cache->has('parent'));
        $this->assertFalse($cache->has('parent/child1'));
        $this->assertFalse($cache->has('parent/child2'));
        $this->assertFalse($cache->has('parent/child2/grandchild'));
        $this->assertTrue($cache->has('other'));
    }

    public function test_set_with_tags(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, ['tag1', 'tag2']);

        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
    }

    public function test_clear_by_tag(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, 'tag1');
        $cache->set('key3', 'value3', null, 'tag2');

        $cache->clear('tag1');

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
    }

    public function test_clear_multiple_tags(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, 'tag2');
        $cache->set('key3', 'value3', null, 'tag3');

        $cache->clear(['tag1', 'tag2']);

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
    }

    public function test_clear_recursive(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, 'parent');
        $cache->set('key2', 'value2', null, 'parent/child');
        $cache->set('key3', 'value3', null, 'parent/child/grandchild');
        $cache->set('key4', 'value4', null, 'other');

        $cache->clear('parent', true);

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertFalse($cache->has('key3'));
        $this->assertTrue($cache->has('key4'));
    }

    public function test_overwriting_key_clears_old_tags(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value1', null, 'tag1');
        $cache->set('key', 'value2', null, 'tag2');

        $cache->clear('tag1');

        // Key should still exist because it's now only tagged with tag2
        $this->assertTrue($cache->has('key'));
        $this->assertEquals('value2', $cache->get('key'));

        $cache->clear('tag2');

        // Now it should be gone
        $this->assertFalse($cache->has('key'));
    }

    public function test_flush(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, 'tag2');

        $cache->flush();

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function test_clean(): void
    {
        $cache = $this->createCache(ttl: 1);
        $cache->set('expired', 'value1');
        $cache->set('valid', 'value2', 100);

        sleep(2);

        $cache->clean();

        $this->assertFalse($cache->has('expired'));
        $this->assertTrue($cache->has('valid'));
    }

    public function test_namespace(): void
    {
        $cache = $this->createCache();
        $namespace = $cache->namespace('user/123');

        $this->assertInstanceOf(CacheNamespace::class, $namespace);

        $namespace->set('profile', 'data');

        $this->assertTrue($cache->has('user/123/profile'));
        $this->assertEquals('data', $cache->get('user/123/profile'));
    }

    public function test_namespace_with_tags_and_ttl(): void
    {
        $cache = $this->createCache();
        $namespace = $cache->namespace('user/123', 'user-tag', 50);

        $namespace->set('profile', 'data');

        $this->assertTrue($cache->has('user/123/profile'));

        $cache->clear('user-tag');

        $this->assertFalse($cache->has('user/123/profile'));
    }

    public function test_complex_data_types(): void
    {
        $cache = $this->createCache();
        $data = [
            'string' => 'value',
            'int'    => 42,
            'float'  => 3.14,
            'bool'   => true,
            'null'   => null,
            'array'  => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $cache->set('complex', $data);

        $this->assertEquals($data, $cache->get('complex'));
    }

    public function test_multiple_tags_per_key(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value', null, ['tag1', 'tag2', 'tag3']);

        $cache->clear('tag1');

        $this->assertFalse($cache->has('key'));
    }

    public function test_callable_only_executed_once(): void
    {
        $cache = $this->createCache();
        $count = 0;

        $callable = function () use (&$count) {
            $count++;
            return 'value';
        };

        $cache->get('key', $callable);
        $cache->get('key', $callable);
        $cache->get('key', $callable);

        $this->assertEquals(1, $count);
    }

    public function test_empty_tag_string(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value', null, '');

        $this->assertTrue($cache->has('key'));

        $cache->clear('');

        $this->assertFalse($cache->has('key'));
    }

    public function test_persistence(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_cache_' . uniqid() . '.db';
        $this->dbPaths[] = $dbPath;

        $cache1 = new SqliteCache($dbPath);
        $cache1->set('key', 'value');

        // Create a new instance pointing to the same database
        $cache2 = new SqliteCache($dbPath);

        $this->assertEquals('value', $cache2->get('key'));
    }

    public function test_persistence_with_tags(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_cache_' . uniqid() . '.db';
        $this->dbPaths[] = $dbPath;

        $cache1 = new SqliteCache($dbPath);
        $cache1->set('key1', 'value1', null, 'tag1');
        $cache1->set('key2', 'value2', null, 'tag1');

        // Create a new instance pointing to the same database
        $cache2 = new SqliteCache($dbPath);

        $cache2->clear('tag1');

        $this->assertFalse($cache2->has('key1'));
        $this->assertFalse($cache2->has('key2'));
    }

    public function test_foreign_key_cascade_on_delete(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value', null, ['tag1', 'tag2']);

        $cache->delete('key');

        // After re-setting with different tags, old tags should be gone
        $cache->set('key', 'new_value', null, 'tag3');

        $cache->clear('tag1');
        $cache->clear('tag2');

        // Key should still exist since it's only tagged with tag3 now
        $this->assertTrue($cache->has('key'));
    }

    public function test_large_value(): void
    {
        $cache = $this->createCache();
        $largeValue = str_repeat('a', 1000000); // 1MB string

        $cache->set('large', $largeValue);

        $this->assertEquals($largeValue, $cache->get('large'));
    }

    public function test_unicode_keys_and_values(): void
    {
        $cache = $this->createCache();
        $cache->set('键', '值');
        $cache->set('clé', 'valeur');
        $cache->set('🔑', '🎁');

        $this->assertEquals('值', $cache->get('键'));
        $this->assertEquals('valeur', $cache->get('clé'));
        $this->assertEquals('🎁', $cache->get('🔑'));
    }

    public function test_unicode_tags(): void
    {
        $cache = $this->createCache();
        $cache->set('key1', 'value1', null, '标签');
        $cache->set('key2', 'value2', null, 'étiquette');

        $cache->clear('标签');

        $this->assertFalse($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
    }

    public function test_cleanup_odds_disabled_by_default(): void
    {
        $cache = $this->createCache(ttl: 1, cleanup_odds: 0);
        $cache->set('key', 'value');

        sleep(2);

        // Expired key should still be in database (just not accessible)
        $this->assertFalse($cache->has('key'));
    }

    public function test_automatic_cleanup(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_cache_' . uniqid() . '.db';
        $this->dbPaths[] = $dbPath;

        $cache1 = new SqliteCache($dbPath, ttl: 1);
        $cache1->set('expired', 'value1');
        $cache1->set('valid', 'value2', 100);

        sleep(2);

        // Force cleanup
        $cache2 = new SqliteCache($dbPath, ttl: 30, cleanup_odds: 1);

        $this->assertFalse($cache2->has('expired'));
        $this->assertTrue($cache2->has('valid'));
    }

    public function test_duplicate_tags_handled(): void
    {
        $cache = $this->createCache();
        $cache->set('key', 'value', null, ['tag1', 'tag1', 'tag2', 'tag2']);

        $this->assertTrue($cache->has('key'));

        $cache->clear('tag1');

        $this->assertFalse($cache->has('key'));
    }

    public function test_special_characters_in_keys(): void
    {
        $cache = $this->createCache();
        $keys = [
            'key with spaces',
            'key-with-dashes',
            'key_with_underscores',
            'key.with.dots',
            'key:with:colons',
            'key@with@at',
        ];

        foreach ($keys as $key) {
            $cache->set($key, "value for $key");
            $this->assertEquals("value for $key", $cache->get($key));
        }
    }

}
