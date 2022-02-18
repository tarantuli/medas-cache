<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Cache\Exceptions\CacheTypeNotSupportedException;

abstract class BaseCache implements Cache, Clearable
{
    abstract public function exists(string $key): bool;

    abstract public function fetch(string $key): mixed;

    abstract public function store(string $key, mixed $value): void;

    abstract public function delete(string $key): void;

    abstract public function isSupported(): bool;

    public function __construct(protected string $namespace)
    {
        if (!$this->isSupported()) {
            throw new CacheTypeNotSupportedException(static::class);
        }
    }

    public function get(array|string $key, callable $getter): mixed
    {
        $key = $this->normalizeKey($key);

        if ($this->exists($key)) {
            $value = $this->fetch($key);
        } else {
            $value = $getter();
            $this->store($key, $value);
        }

        return $value;
    }

    private function normalizeKey(array|string $key): string
    {
        return sha1(is_array($key) ? implode("\0", $key) : $key);
    }

    public function remove(array|string $key): void
    {
        $key = $this->normalizeKey($key);
        $this->delete($key);
    }
}
