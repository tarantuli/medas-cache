<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable};

abstract class BaseCache implements Cache, Clearable, Interfaces\TransformsKeys
{
    abstract public function isSupported(): bool;

    abstract public function delete(string $key): void;

    abstract public function exists(string $key): bool;

    abstract public function fetch(string $key): mixed;

    abstract public function store(string $key, mixed $value, int $ttl): void;

    public function __construct()
    {
        if (!$this->isSupported()) {
            throw new Exceptions\CacheTypeNotSupported(static::class);
        }
    }

    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->exists($normalizedKey)) {
            $value = $this->fetch($normalizedKey);
        }
        else {
            $value = $getter();

            $this->store($normalizedKey, $value, $ttl);
        }

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

    public function normalizeKey(array|string $key): string
    {
        return is_array($key) ? implode("\0", $key) : $key;
    }

    public function transformKey(array|string $key): string
    {
        return $this->normalizeKey($key);
    }
}
