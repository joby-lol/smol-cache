<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

use PHPUnit\Framework\TestCase;

class CacheNamespaceTest extends TestCase
{

    public function test_key_prefixing(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', null, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->set('profile', 'data');
    }

    public function test_empty_namespace_no_prefix(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('key', 'value', null, [])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, '');
        $namespace->set('key', 'value');
    }

    public function test_tag_merging(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', null, ['user-tag', 'profile-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->set('profile', 'data', null, 'profile-tag');
    }

    public function test_tag_merging_array(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', null, ['user-tag', 'tag1', 'tag2'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->set('profile', 'data', null, ['tag1', 'tag2']);
    }

    public function test_tag_deduplication(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', null, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->set('profile', 'data', null, 'user-tag');
    }

    public function test_ttl_inheritance(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', 600, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $namespace->set('profile', 'data');
    }

    public function test_ttl_override(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/profile', 'data', 300, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $namespace->set('profile', 'data', 300);
    }

    public function test_get(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('get')
            ->with('user/123/profile', null, 600, ['user-tag'])
            ->willReturn('cached-data');

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $result = $namespace->get('profile');

        $this->assertEquals('cached-data', $result);
    }

    public function test_get_with_default(): void
    {
        $callable = fn() => 'default';

        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('get')
            ->with('user/123/missing', $callable, 300, ['user-tag', 'tag'])
            ->willReturn('default');

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $result = $namespace->get('missing', $callable, 300, 'tag');

        $this->assertEquals('default', $result);
    }

    public function test_has(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('has')
            ->with('user/123/profile')
            ->willReturn(true);

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $this->assertTrue($namespace->has('profile'));
    }

    public function test_delete(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('delete')
            ->with('user/123/profile', false)
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->delete('profile');
    }

    public function test_delete_recursive(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('delete')
            ->with('user/123/profile', true)
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->delete('profile', true);
    }

    public function test_clear_passes_through(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('clear')
            ->with('some-tag', false)
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->clear('some-tag');
    }

    public function test_clear_recursive(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('clear')
            ->with('tag', true)
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag']);
        $namespace->clear('tag', true);
    }

    public function test_nested_namespace(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/settings/theme', 'dark', 100, ['user-tag', 'settings-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $nested = $namespace->namespace('settings', 'settings-tag', 100);
        $nested->set('theme', 'dark');
    }

    public function test_nested_namespace_inherits_tags(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/settings/theme', 'dark', 600, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $nested = $namespace->namespace('settings');
        $nested->set('theme', 'dark');
    }

    public function test_nested_namespace_inherits_ttl(): void
    {
        $driver = $this->createMock(CacheInterface::class);
        $driver->expects($this->once())
            ->method('set')
            ->with('user/123/settings/theme', 'dark', 600, ['user-tag'])
            ->willReturnSelf();

        $namespace = new CacheNamespace($driver, 'user/123', ['user-tag'], 600);
        $nested = $namespace->namespace('settings');
        $nested->set('theme', 'dark');
    }

}
