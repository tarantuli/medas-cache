<?php

declare(strict_types=1);

namespace Medas\Cache;

class MemoryCache extends BaseCache
{
    private array $data = [];

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function fetch(string $key): string
    {
        return $this->data[$key];
    }

    public function store(array|string $key, string $normalizedKey, string $value): void
    {
        $this->data[$normalizedKey] = $value;
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
