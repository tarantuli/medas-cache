<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{Attributes\Service, Interfaces\Serializer};

#[Service]
class ApcuCache extends BaseCache
{
    public function __construct(
        Serializer|null $serializer = null,
        private string  $namespace,
    )
    {
        $this->namespace = sha1($this->namespace) . ':';

        parent::__construct($serializer);
    }

    public function isSupported(): bool
    {
        return apcu_enabled();
    }

    public function delete(string $key): void
    {
        apcu_delete($this->namespace . $key);
    }

    public function exists(string $key): bool
    {
        return apcu_exists($this->namespace . $key);
    }

    public function fetch(string $key): mixed
    {
        return apcu_fetch($this->namespace . $key);
    }

    public function store(string $key, mixed $value): void
    {
        apcu_store($this->namespace . $key, $value);
    }

    public function clear(): void
    {
        foreach (new \APCUIterator("/^$this->namespace/", APC_ITER_KEY, 1000) as $key) {
            apcu_delete($key);
        }
    }
}
