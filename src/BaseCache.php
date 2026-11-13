<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable, NotCacheable};

/**
 * Base class for the caches in this package. It implements the `Cache` interface on top of four primitives that each
 * backend provides: `exists()`, `fetch()`, `storeEntry()` and `delete()`.
 *
 * The primitives take a normalised key: a key given as an array is joined with null bytes into one string. A
 * `$ttl` of `0` means the entry never expires.
 *
 * Values that implement @class(Medas\Core\Interfaces\NotCacheable) aren't stored: `store()` deletes the entry for
 * the key instead. Only the value itself is checked, not the objects it contains.
 */
abstract class BaseCache implements Cache, Clearable, Interfaces\TransformsKeys
{
    /**
     * Whether the backend can be used in this process, e.g. whether its PHP extension is loaded.
     */
    abstract public function isSupported(): bool;

    abstract public function delete(string $key): void;

    abstract public function exists(string $key): bool;

    /**
     * @assumes `exists()` returned `true` for the key just before.
     */
    abstract public function fetch(string $key): mixed;

    /**
     * Stores the value in the backend. Only called by `store()`, so implementations don't need to check for
     * `NotCacheable`.
     *
     * @param int $ttl In seconds; `0` means the entry never expires
     */
    abstract protected function storeEntry(string $key, mixed $value, int $ttl): void;

    /**
     * @assumes Subclasses set up everything `isSupported()` needs before calling this constructor.
     *
     * @throws Exceptions\CacheTypeNotSupported When `isSupported()` returns `false`.
     */
    public function __construct()
    {
        if (!$this->isSupported()) {
            throw new Exceptions\CacheTypeNotSupported(static::class);
        }
    }

    /**
     * Stores the value, unless it implements `NotCacheable`. Then the entry for the key is deleted instead, so an
     * older value stored under the same key isn't returned later.
     *
     * @param int $ttl In seconds; `0` means the entry never expires
     */
    final public function store(string $key, mixed $value, int $ttl): void
    {
        if ($value instanceof NotCacheable) {
            $this->delete($key);

            return;
        }

        $this->storeEntry($key, $value, $ttl);
    }

    /**
     * Checks with `exists()` and then calls `fetch()`, so a hit costs two lookups. Backends that can do it in one
     * override this method.
     */
    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->exists($normalizedKey)) {
            try {
                return $this->fetch($normalizedKey);
            }
            catch (Exceptions\CacheEntryExpired) {
                // The entry can expire between exists() and fetch() when the clock moves on to the next second;
                // it is then built anew, as if it had not existed
            }
        }

        $value = $getter();

        $this->store($normalizedKey, $value, $ttl);

        return $value;
    }

    public function set(string|array $key, mixed $value, int $ttl = 0): void
    {
        $normalizedKey = $this->normalizeKey($key);

        $this->store($normalizedKey, $value, $ttl);
    }

    public function remove(array|string $key): void
    {
        $key = $this->normalizeKey($key);

        $this->delete($key);
    }

    public function contains(array|string $key): bool
    {
        $key = $this->normalizeKey($key);

        return $this->exists($key);
    }

    /**
     * @param string|string[] $key
     */
    public function normalizeKey(array|string $key): string
    {
        return is_array($key) ? implode("\0", $key) : $key;
    }

    public function transformKey(array|string $key): string
    {
        return $this->normalizeKey($key);
    }
}
