<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable};

/**
 * Wraps a cache and records every key it stores a value for in a JSON file, `key_index` in the base directory, so
 * the keys can be looked up later, e.g. to see what a cache holds.
 *
 * The index maps the identifier the inner cache stores the entry under, from `transformKey()`, to the key as it was
 * given. There's no method that returns the index: it can only be read from the file.
 *
 * @assumes The base directory exists; the decorator doesn't create it.
 * @assumes Only one process at a time adds keys. The index is read once and written as a whole, so keys added by
 *          another process in the meantime are lost.
 * @sideEffect Rewrites the whole index file for every key that isn't in it yet.
 * @sideEffect Deletes the index file when it can't be decoded, and starts with an empty index.
 * @state Keeps the index in memory for the lifetime of the instance.
 */
class KeyRegisterDecorator implements Cache, Clearable
{
    private array $keys;

    /**
     * @param string $baseDirectory The directory to keep `key_index` in, usually the inner cache's own directory
     */
    public function __construct(
        private readonly Cache  & Interfaces\TransformsKeys $cache,
        private readonly string $baseDirectory,
    )
    {
        $this->readExistingKeys();
    }

    private function readExistingKeys(): void
    {
        $keyFile = $this->keyFilePath();
        $keys = file_exists($keyFile) ? json_decode(file_get_contents($keyFile), true) : [];

        if (!is_array($keys)) {
            // The key file is corrupt
            unlink($keyFile);

            $this->keys = [];
        }
        else {
            $this->keys = $keys;
        }
    }

    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $alreadyExisted = $this->cache->contains($key);
        $value = $this->cache->get($key, $getter, $ttl);

        if (!$alreadyExisted) {
            $this->registerKey($key);
        }

        return $value;
    }

    public function set(array|string $key, mixed $value, int $ttl = 0): void
    {
        $this->cache->set($key, $value, $ttl);
        $this->registerKey($key);
    }

    /**
     * Removes the entry from the inner cache, but leaves its key in the index.
     */
    public function remove(array|string $key): void
    {
        $this->cache->remove($key);
    }

    public function contains(array|string $key): bool
    {
        return $this->cache->contains($key);
    }

    /**
     * Adds the key to the index, without storing a value for it.
     */
    public function registerKey(array|string $key): void
    {
        $transformedKey = $this->cache->transformKey($key);

        if (array_key_exists($transformedKey, $this->keys)) {
            return;
        }

        $this->keys[$transformedKey] = $key;

        file_put_contents($this->keyFilePath(), json_encode($this->keys, JSON_PRETTY_PRINT));
    }

    /**
     * Empties the index, then clears the inner cache when it implements `Clearable`.
     */
    public function clear(): void
    {
        $this->keys = [];

        file_put_contents($this->keyFilePath(), '{}');

        if ($this->cache instanceof Clearable) {
            $this->cache->clear();
        }
    }

    private function keyFilePath(): string
    {
        return $this->baseDirectory . DIRECTORY_SEPARATOR . 'key_index';
    }
}
