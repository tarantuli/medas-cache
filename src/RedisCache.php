<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Attributes\Entrypoint;

/**
 * Stores values in Redis, serialised with `serialize()`, under a prefix derived from the namespace.
 *
 * Unlike `FileSystemCache` and `ApcuCache`, it has no memory cache in front of it: every lookup goes to Redis. A hit
 * through `get()` costs a single round trip.
 *
 * Nothing is registered in `var/dirs-to-clear`: the entries are only removed by `clear()`, which `clearAll()` on the
 * `CacheManager` calls when the cache is registered there.
 *
 * @assumes The `\Redis` instance is connected, and stays usable for the lifetime of the cache.
 */
#[Entrypoint]
class RedisCache extends BaseCache
{
    private string $keyPrefix;

    /**
     * @param string $namespace Keeps these entries apart from those of other caches and applications that use the
     *                          same Redis database
     */
    public function __construct(
        private readonly \Redis $redis,
        string                  $namespace,
    )
    {
        $this->keyPrefix = sha1($namespace) . ':';

        parent::__construct();
    }

    /**
     * Always `true`: the constructor needs a `\Redis` instance, and that class only exists when the `redis` extension
     * is loaded. Without it, PHP already fails on the constructor's parameter type, before this is ever called.
     */
    public function isSupported(): bool
    {
        return true;
    }

    /**
     * Overridden so a value in the cache costs a single `GET`, instead of a check whether it exists followed by a
     * fetch, which would also fail when the entry expires in between.
     */
    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        // A stored value is never false, because it's serialised; false means there is no entry
        $serialized = $this->redis->get($this->keyPrefix . $normalizedKey);

        if ($serialized !== false) {
            return unserialize($serialized);
        }

        $value = $getter();

        $this->store($normalizedKey, $value, $ttl);

        return $value;
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->keyPrefix . $key);
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($this->keyPrefix . $key) > 0;
    }

    /**
     * @throws Exceptions\RedisKeyDisappeared When the entry expired or was deleted after `exists()`.
     */
    public function fetch(string $key): mixed
    {
        $value = $this->redis->get($this->keyPrefix . $key);

        if ($value === false) {
            throw new Exceptions\RedisKeyDisappeared($key);
        }

        return unserialize($value);
    }

    /**
     * Deletes every key with this cache's prefix, scanning for them in batches of 1000.
     *
     * @performance Scans the whole Redis database, so it takes longer the more keys the database holds, including
     *              those of other prefixes.
     */
    public function clear(): void
    {
        $pattern = $this->keyPrefix . '*';
        $cursor = null;

        do {
            $keys = $this->redis->scan($cursor, $pattern, 1000);

            if ($keys !== false && $keys !== []) {
                $this->redis->del(...$keys);
            }
        } while ($cursor !== 0);
    }

    protected function storeEntry(string $key, mixed $value, int $ttl): void
    {
        $serialized = serialize($value);

        if ($ttl > 0) {
            $this->redis->setex($this->keyPrefix . $key, $ttl, $serialized);
        }
        else {
            $this->redis->set($this->keyPrefix . $key, $serialized);
        }
    }
}
