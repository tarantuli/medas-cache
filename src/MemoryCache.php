<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\MemoryCache as MemoryCacheInterface;

class MemoryCache extends BaseCache implements MemoryCacheInterface
{
    private array $data = [];

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function fetch(string $key): mixed
    {
        return $this->data[$key];
    }

    public function store(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
