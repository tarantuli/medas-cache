<?php

declare(strict_types=1);

namespace Medas\Cache;

class NoopCache extends BaseCache
{
    public function exists(string $key): bool
    {
        return false;
    }

    public function fetch(string $key): string
    {
        throw new \Exception('this cache should never fetch');
    }

    public function store(array|string $key, string $normalizedKey, string $value): void
    {
        // Do nothing
    }

    public function delete(string $key): void
    {
        // Do nothing
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function clear(): void
    {
        // Do nothing
    }
}
