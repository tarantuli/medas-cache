<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Attributes\Service;

#[Service]
class ApcuCache extends BaseCache
{
    public function __construct(
        private string $namespace,
    )
    {
        $this->namespace = sha1($this->namespace) . ':';

        parent::__construct();
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
        $value = apcu_fetch($this->namespace . $key, $success);

        if (!$success) {
            throw new Exceptions\ApcuKeyDisappeared($key);
        }

        return $value;
    }

    public function store(string $key, mixed $value, int $ttl): void
    {
        apcu_store($this->namespace . $key, $value, $ttl);
    }

    public function clear(): void
    {
        foreach (new \APCUIterator("/^$this->namespace/", APC_ITER_KEY, 1000) as $item) {
            apcu_delete($item['key']);
        }
    }
}
