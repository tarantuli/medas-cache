<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Cache\Exceptions\CacheTypeNotSupportedException;
use Medas\Cache\Interfaces\HasKeyRegister;
use Medas\ServiceManager\Interfaces\{Cache, Clearable};

abstract class BaseCache implements Cache, Clearable
{
    abstract public function exists(string $key): bool;

    abstract public function fetch(string $key): mixed;

    abstract public function store(string $key, mixed $value): void;

    abstract public function delete(string $key): void;

    abstract public function isSupported(): bool;

    protected Interfaces\Serializer $serializer;

    public function __construct(
        protected string      $namespace,
        Interfaces\Serializer $serializer = null,
    )
    {
        if (!$this->isSupported()) {
            throw new CacheTypeNotSupportedException(static::class);
        }

        $this->serializer = $serializer ?? service(Interfaces\Serializer::class);
    }

    public function get(array|string $key, callable $getter): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->exists($normalizedKey)) {
            $value = $this->fetch($normalizedKey);
        } else {
            $value = $getter();
            $this->store($normalizedKey, $value);

            if ($this instanceof HasKeyRegister) {
                $this->registerKey($key, $normalizedKey);
            }
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
