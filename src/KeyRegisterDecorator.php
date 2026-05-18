<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable};

class KeyRegisterDecorator implements Cache, Clearable
{
    private array $keys;

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

    public function remove(array|string $key): void
    {
        $this->cache->remove($key);
    }

    public function contains(array|string $key): bool
    {
        return $this->cache->contains($key);
    }

    public function registerKey(array|string $key): void
    {
        $transformedKey = $this->cache->transformKey($key);

        if (array_key_exists($transformedKey, $this->keys)) {
            return;
        }

        $this->keys[$transformedKey] = $key;

        file_put_contents($this->keyFilePath(), json_encode($this->keys, JSON_PRETTY_PRINT));
    }

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
