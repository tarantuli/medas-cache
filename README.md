# medas-cache

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

Provides a unified caching abstraction with multiple backend implementations. All backends share the same `Cache` interface — `get()`, `set()`, `remove()`, and `contains()` — so the calling code is decoupled from the storage mechanism. Keys can be either a plain string or an array of parts that are joined with a null byte separator, making namespaced compound keys straightforward to compose.

Available backends:

| Class             | Backend                    | Notes                                                                                             |
|-------------------|----------------------------|---------------------------------------------------------------------------------------------------|
| `MemoryCache`     | PHP array (request-scoped) | Always available; used as a first-level layer inside `FileSystemCache`                            |
| `FileSystemCache` | Local filesystem           | SHA1-sharded directory layout; locking writes; pluggable serializer (defaults to `PhpSerializer`) |
| `ApcuCache`       | APCu shared memory         | Requires the `apcu` extension; namespaced via SHA1 prefix                                         |
| `RedisCache`      | Redis                      | Requires the `redis` extension and a `\Redis` instance; namespaced via SHA1 prefix                |

`KeyRegisterDecorator` wraps any `Cache & TransformsKeys` implementation to maintain a persistent JSON key index on disk — useful when you need to list or bulk-invalidate cached entries.

All backends implement `Clearable` so the entire cache can be wiped with a single `clear()` call.

## Usage

### Package developer context

Register the package and choose a backend to inject:

```php
use Medas\Cache\CachePackage;
use Medas\Cache\FileSystemCache;
use Medas\Cache\ApcuCache;
use Medas\Cache\MemoryCache;
use Medas\Core\Attributes\Service;

// Register the package with the framework bootstrapper
CachePackage::instance();
```

**File system cache** — the most common choice for general-purpose caching:

```php
#[Service]
readonly class MyRepository
{
    private FileSystemCache $cache;

    public function __construct()
    {
        $this->cache = new FileSystemCache(baseDirectory: 'var/cache/my-repository');
    }

    public function findById(int $id): MyEntity
    {
        // The callable is only invoked on a cache miss; the result is stored and
        // returned on later calls within the TTL window.
        return $this->cache->get(
            key: ['entity', $id],
            getter: fn() => $this->loadFromDatabase($id),
            ttl: 3600,
        );
    }

    public function invalidate(int $id): void
    {
        $this->cache->remove(['entity', $id]);
    }
}
```

**APCu cache** — shared across requests within the same PHP process pool:

```php
use Medas\Cache\ApcuCache;

// The namespace is hashed internally, so any string is safe to use.
$cache = new ApcuCache(namespace: 'my-app');

$result = $cache->get(
    key: 'expensive-query',
    getter: fn() => runExpensiveQuery(),
    ttl: 300,
);
```

**Redis cache** — for distributed or persistent caching across multiple servers:

```php
use Medas\Cache\RedisCache;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$cache = new RedisCache(redis: $redis, namespace: 'my-app');

$cache->set(key: 'config', value: $configArray, ttl: 86400);
$config = $cache->get(key: 'config', getter: fn() => loadConfig(), ttl: 86400);
```

**Memory cache** — request-scoped, zero dependencies, useful in tests or as a short-circuit layer:

```php
use Medas\Cache\MemoryCache;

$cache = new MemoryCache();

$cache->set('greeting', 'hello', ttl: 60);

if ($cache->contains('greeting')) {
    echo $cache->get('greeting', fn() => 'fallback');
}
```

**KeyRegisterDecorator** — wraps any backend to maintain an enumerable key index:

```php
use Medas\Cache\FileSystemCache;
use Medas\Cache\KeyRegisterDecorator;

$inner = new FileSystemCache(baseDirectory: 'var/cache/products');
$cache = new KeyRegisterDecorator(cache: $inner, baseDirectory: 'var/cache/products');

// Keys are registered automatically on get() and set().
$cache->get(['product', 42], fn() => fetchProduct(42), ttl: 600);

// clear() wipes the key index and delegates to the inner cache's clear().
$cache->clear();
```

### Backend user context

Caches are typically wired up once during bootstrap and then consumed transparently through services. The most common interaction points are:

**Warming or invalidating a specific entry:**

```php
// Force-store a value regardless of whether it already exists
$cache->set(key: ['report', 'monthly', '2026-05'], value: $reportData, ttl: 86400);

// Remove a stale entry so the next request recomputes it
$cache->remove(['report', 'monthly', '2026-05']);

// Check presence without triggering a getter
if ($cache->contains(['report', 'monthly', '2026-05'])) {
    // serve from cache
}
```

**Clearing the entire cache** (e.g., after a deployment or data migration):

```php
// Works on all backends — MemoryCache, FileSystemCache, ApcuCache, RedisCache
$cache->clear();
```

For `FileSystemCache`, the framework also registers each cache directory under `var/dirs-to-clear` so a single framework-level flush command can wipe all file caches at once without having to list them manually.
