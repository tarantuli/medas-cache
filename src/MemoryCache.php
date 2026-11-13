<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{Attributes\Entrypoint, Interfaces\MemoryCache as MemoryCacheInterface};

/**
 * Keeps values in a PHP array, for the rest of the request or the lifetime of the process.
 *
 * Values are stored as they are, without serialising them, so an object that's changed after storing it is changed
 * in the cache too. `FileSystemCache` and `ApcuCache` each keep their own instance as a first layer in front of their
 * backend.
 *
 * @state Keeps every stored value until it's deleted, the cache is cleared or the process ends. Expired entries are
 *        only removed when they're looked up, so in a long-running worker they keep taking memory.
 * @see \Medas\ServiceManager\Cache\CacheManager Creates one as the `'memory'` cache, through the binding that
 *      `CachePackage` adds for @class(Medas\Core\Interfaces\MemoryCache).
 */
#[Entrypoint]
class MemoryCache extends BaseCache implements MemoryCacheInterface
{
    private array $data = [];
    private array $ttls = [];

    public function exists(string $key): bool
    {
        if (isset($this->ttls[$key]) && $this->ttls[$key] < time()) {
            $this->delete($key);
        }

        return array_key_exists($key, $this->data);
    }

    public function fetch(string $key): mixed
    {
        if (isset($this->ttls[$key]) && $this->ttls[$key] < time()) {
            $this->delete($key);

            throw new Exceptions\CacheEntryExpired($key);
        }

        return $this->data[$key];
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
        unset($this->ttls[$key]);
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function clear(): void
    {
        $this->data = [];
        $this->ttls = [];
    }

    protected function storeEntry(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
        $this->ttls[$key] = $ttl > 0 ? time() + $ttl : null;
    }
}
