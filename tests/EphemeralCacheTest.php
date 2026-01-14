<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

use PHPUnit\Framework\TestCase;

class EphemeralCacheTest extends TestCase
{

    public function test_set_and_get(): void
    {
        $cache = new EphemeralCache();
        $cache->set('key', 'value');

        $this->assertEquals('value', $cache->get('key'));
    }

    public function test_set_returns_value(): void
    {
        $cache = new EphemeralCache();
        $result = $cache->set('key', 'value');

        $this->assertEquals('value', $result);
    }

    public function test_get_nonexistent_returns_null(): void
    {
        $cache = new EphemeralCache();

        $this->assertNull($cache->get('nonexistent'));
    }

    public function test_get_with_default(): void
    {
        $cache = new EphemeralCache();
        $result = $cache->get('missing', 'default');

        $this->assertEquals('default', $result);
        $this->assertEquals('default', $cache->get('missing'));
    }

    public function test_get_with_callable_default(): void
    {
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
        $cache->set('key', fn() => 'computed');

        $this->assertEquals('computed', $cache->get('key'));
    }

    public function test_has(): void
    {
        $cache = new EphemeralCache();

        $this->assertFalse($cache->has('key'));

        $cache->set('key', 'value');
        $this->assertTrue($cache->has('key'));
    }

    public function test_ttl_expiration(): void
    {
        $cache = new EphemeralCache(ttl: 1);
        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));

        sleep(2);

        $this->assertFalse($cache->has('key'));
        $this->assertNull($cache->get('key'));
    }

    public function test_custom_ttl(): void
    {
        $cache = new EphemeralCache(ttl: 100);
        $cache->set('key', 'value', 1);

        $this->assertTrue($cache->has('key'));

        sleep(2);

        $this->assertFalse($cache->has('key'));
    }

    public function test_delete(): void
    {
        $cache = new EphemeralCache();
        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));

        $cache->delete('key');

        $this->assertFalse($cache->has('key'));
    }

    public function test_delete_recursive(): void
    {
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, ['tag1', 'tag2']);

        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
    }

    public function test_clear_by_tag(): void
    {
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
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

    public function test_delete_cleans_up_tag_references(): void
    {
        $cache = new EphemeralCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, 'tag1');

        $cache->delete('key1');

        // Clearing tag1 should only affect key2 now
        $cache->clear('tag1');

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function test_flush(): void
    {
        $cache = new EphemeralCache();
        $cache->set('key1', 'value1', null, 'tag1');
        $cache->set('key2', 'value2', null, 'tag2');

        $cache->flush();

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function test_namespace(): void
    {
        $cache = new EphemeralCache();
        $namespace = $cache->namespace('user/123');

        $this->assertInstanceOf(CacheNamespace::class, $namespace);

        $namespace->set('profile', 'data');

        $this->assertTrue($cache->has('user/123/profile'));
        $this->assertEquals('data', $cache->get('user/123/profile'));
    }

    public function test_namespace_with_tags_and_ttl(): void
    {
        $cache = new EphemeralCache(ttl: 100);
        $namespace = $cache->namespace('user/123', 'user-tag', 50);

        $namespace->set('profile', 'data');

        $this->assertTrue($cache->has('user/123/profile'));

        $cache->clear('user-tag');

        $this->assertFalse($cache->has('user/123/profile'));
    }

    public function test_complex_data_types(): void
    {
        $cache = new EphemeralCache();

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
        $cache = new EphemeralCache();
        $cache->set('key', 'value', null, ['tag1', 'tag2', 'tag3']);

        $cache->clear('tag1');

        $this->assertFalse($cache->has('key'));
    }

    public function test_callable_only_executed_once(): void
    {
        $cache = new EphemeralCache();
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
        $cache = new EphemeralCache();
        $cache->set('key', 'value', null, '');

        // Should not throw an error
        $this->assertTrue($cache->has('key'));

        $cache->clear('');

        // Should clear the key
        $this->assertFalse($cache->has('key'));
    }

    public function test_tag_cleanup_on_recursive_delete(): void
    {
        $cache = new EphemeralCache();
        $cache->set('parent', 'value1', null, 'tag1');
        $cache->set('parent/child', 'value2', null, 'tag1');
        $cache->set('other', 'value3', null, 'tag1');

        $cache->delete('parent', true);

        // After clearing tag1, only 'other' should be gone
        $cache->clear('tag1');
        $this->assertFalse($cache->has('other'));
    }

}
