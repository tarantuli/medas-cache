<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Cache\Exceptions\CacheTypeNotSupportedException;

abstract class BaseCache implements Cache, Clearable
{
    abstract public function exists(string $key): bool;

    abstract public function fetch(string $key): string;

    abstract public function store(string|array $key, string $normalizedKey, string $value): void;

    abstract public function delete(string $key): void;

    abstract public function isSupported(): bool;

    private Interfaces\Serializer $serializer;

    public function __construct(protected string $namespace)
    {
        if (!$this->isSupported()) {
            throw new CacheTypeNotSupportedException(static::class);
        }

        $this->serializer = service(Interfaces\Serializer::class);
    }

    public function get(array|string $key, callable $getter): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->exists($normalizedKey)) {
            $serializedValue = $this->fetch($normalizedKey);
            $value = $this->serializer->unserialize($serializedValue);
        } else {
            $value = $getter();
            $serializedValue = $this->serializer->serialize($value);
            $this->store($key, $normalizedKey, $serializedValue);
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
