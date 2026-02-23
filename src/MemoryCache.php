<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{Attributes\Service, Interfaces\MemoryCache as MemoryCacheInterface};

#[Service]
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

    public function store(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
        $this->ttls[$key] = $ttl > 0 ? time() + $ttl : null;
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
}
