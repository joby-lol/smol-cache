# smolCache

A lightweight PHP caching library with tag support and hierarchical invalidation.

## Installation

```bash
composer require joby-lol/smol-cache
```

## About

smol-cache provides a simple, consistent interface for caching data with support for tags, TTL expiration, and hierarchical cache invalidation. It includes two implementations:

- **EphemeralCache**: In-memory cache for single-request caching
- **SqliteCache**: Persistent SQLite-backed cache for multi-request caching

**Hierarchical Organization**: Both keys and tags use forward slash (`/`) as the canonical delimiter for hierarchical structures, enabling powerful recursive operations.

## Basic Usage

```php
use Joby\Smol\Cache\SqliteCache;

$cache = new SqliteCache('/path/to/cache.db', ttl: 3600);

// Set a value
$cache->set('user/123', ['name' => 'Alice', 'email' => 'alice@example.com']);

// Get a value
$user = $cache->get('user/123');

// Check if exists
if ($cache->has('user/123')) {
    // ...
}

// Delete a value
$cache->delete('user/123');
```

## Cache Implementations

### EphemeralCache

In-memory cache that persists only for the current request.

```php
use Joby\Smol\Cache\EphemeralCache;

$cache = new EphemeralCache(ttl: 30); // 30 second default TTL
```

### SqliteCache

Persistent cache backed by SQLite database.

```php
use Joby\Smol\Cache\SqliteCache;

// Basic usage
$cache = new SqliteCache('/path/to/cache.db', ttl: 300);

// With automatic cleanup (1 in 100 chance on each instantiation)
$cache = new SqliteCache('/path/to/cache.db', ttl: 300, cleanup_odds: 100);
```

## Core Methods

### get()

Retrieve a value from the cache, optionally setting a default if not found.

```php
// Simple get
$value = $cache->get('key');

// Get with default
$value = $cache->get('key', 'default value');

// Get with callable default (only executed if key doesn't exist)
$value = $cache->get('expensive/calculation', function() {
    return performExpensiveCalculation();
});

// Get with custom TTL and tags
$value = $cache->get('key', 'default', ttl: 600, tags: 'user-data');
```

### set()

Store a value in the cache.

```php
// Simple set
$cache->set('key', 'value');

// Set with custom TTL
$cache->set('key', 'value', ttl: 1800);

// Set with tags
$cache->set('key', 'value', tags: ['user', 'profile']);

// Set with callable (executed immediately and result is cached)
$cache->set('key', fn() => computeValue());
```

### has()

Check if a key exists and is not expired.

```php
if ($cache->has('user/123')) {
    // Key exists
}
```

### delete()

Remove a value from the cache. Use slash (`/`) as the delimiter for hierarchical keys.

```php
// Delete single key
$cache->delete('user/123');

// Delete recursively (deletes 'user' and all keys starting with 'user/123/')
$cache->delete('user', recursive: true);
// Deletes: user/123, user/123/profile, user/123/settings, user/123/posts/1, etc.
```

## Tags

Associate cache items with tags for grouped invalidation. Tags support the same hierarchical structure as keys using slash (`/`) delimiters.

```php
// Set with single tag
$cache->set('user/123', $userData, tags: 'users');

// Set with multiple tags
$cache->set('post/456', $postData, tags: ['posts', 'user/123/content']);

// Clear all items with a tag
$cache->clear('users');
$cache->clear(['users', 'posts']);
```

### Hierarchical Tags

Use slash (`/`) separated tag names for hierarchical invalidation.

```php
$cache->set('post/1', $data, tags: 'content/posts/published');
$cache->set('post/2', $data, tags: 'content/posts/draft');
$cache->set('page/1', $data, tags: 'content/pages');

// Clear only published posts
$cache->clear('content/posts/published');

// Clear all posts (published and draft) - clears all tags starting with 'content/posts/'
$cache->clear('content/posts', recursive: true);

// Clear all content - clears all tags starting with 'content/'
$cache->clear('content', recursive: true);
```

## Namespacing

Create namespaced cache instances that automatically prefix keys with a namespace followed by a slash (`/`).

```php
// Create namespaced cache
$userCache = $cache->namespace('user/123');

// Keys are automatically prefixed with 'user/123/'
$userCache->set('profile', $data);
// Actually sets 'user/123/profile'

$userCache->get('settings');
// Actually gets 'user/123/settings'
```

### Namespace with Default Tags and TTL

```php
// Namespace with tags that apply to all items
$userCache = $cache->namespace('user/123', tags: 'user-data', ttl: 600);

$userCache->set('profile', $data);
// Sets with both 'user-data' tag and 600 second TTL

// Clear all data for this user
$cache->clear('user-data');
```

### Nested Namespaces

Namespaces compose naturally with slash delimiters:

```php
$userCache = $cache->namespace('user/123');
$settingsCache = $userCache->namespace('settings');

$settingsCache->set('theme', 'dark');
// Actually sets 'user/123/settings/theme'
```

## Utility Methods

These are not included in the interface, but are available in the built-in implementations.

### flush()

Delete all items from the cache.

```php
$cache->flush();
```

### clean() (SqliteCache only)

Remove expired items from the database.

```php
$cache->clean();
```

### compact() (SqliteCache only)

Reclaim unused space in the SQLite database. This operation is slow and locks the database.

```php
$cache->compact();
```

## Advanced Features

### Callable Values and Defaults

Both `get()` and `set()` accept callables, which are executed and their return value is cached.

```php
// Get or compute and cache
$result = $cache->get('expensive/operation', doExpensiveOperation(...));

// Set with callable
$cache->set('timestamp', time(...));
```

### Complex Data Types

All data is JSON-encoded internally, supporting arrays, objects, and scalar types. EphemeralCache does no manipulation of values, and supports literally anything that can be put in a PHP variable.

```php
$cache->set('complex', [
    'user' => ['id' => 123, 'name' => 'Alice'],
    'metadata' => ['created' => time()],
    'flags' => ['active' => true, 'verified' => false]
]);

$data = $cache->get('complex');
// Returns the full array structure
```

### Hierarchical Keys

Use slash (`/`) separators in keys for logical grouping with recursive deletion.

```php
$cache->set('user/123/profile', $profile);
$cache->set('user/123/settings', $settings);
$cache->set('user/123/posts/1', $post1);
$cache->set('user/123/posts/2', $post2);

// Delete all user data - deletes all keys starting with 'user/123/'
$cache->delete('user/123', recursive: true);
```

## Usage Patterns

### Page Fragment Caching

```php
$cache = new SqliteCache('cache.db', ttl: 3600);

$content = $cache->get("page/$pageId/fragment/sidebar", function() use ($pageId) {
    return renderSidebar($pageId);
}, tags: "page/$pageId");

// Invalidate all fragments for a page
$cache->clear("page/$pageId");
```

### API Response Caching

```php
$apiCache = $cache->namespace('api/v1', ttl: 300);

$users = $apiCache->get("users/$userId", function() use ($userId) {
    return fetchUserFromDatabase($userId);
}, tags: ['users', "user/$userId"]);

// Invalidate all user data
$cache->clear('users');
```

### Computed Value Caching

```php
$result = $cache->get('report/monthly', function() {
    return generateMonthlyReport();
}, ttl: 86400, tags: 'reports');

// Invalidate all reports
$cache->clear('reports');
```

## Performance Considerations

### SqliteCache Automatic Cleanup

Configure automatic cleanup to periodically remove expired entries:

```php
// 1 in 100 chance of cleanup on each instantiation
$cache = new SqliteCache('cache.db', ttl: 3600, cleanup_odds: 100);
```

### Manual Cleanup

For cron-based cleanup:

```php
$cache = new SqliteCache('cache.db');
$cache->clean();     // Remove expired items
$cache->compact();   // Reclaim disk space (optional, slow, locking)
```

## License

MIT License - See [LICENSE](LICENSE) file for details.