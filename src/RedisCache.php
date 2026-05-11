<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Attributes\Service;

#[Service]
class RedisCache extends BaseCache
{
    private string $keyPrefix;

    public function __construct(
        private readonly \Redis $redis,
        string                  $namespace,
    )
    {
        $this->keyPrefix = sha1($namespace) . ':';

        parent::__construct();
    }

    public function isSupported(): bool
    {
        return extension_loaded('redis');
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->keyPrefix . $key);
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($this->keyPrefix . $key) > 0;
    }

    public function fetch(string $key): mixed
    {
        $value = $this->redis->get($this->keyPrefix . $key);

        if ($value === false) {
            throw new Exceptions\RedisKeyDisappeared($key);
        }

        return unserialize($value);
    }

    public function store(string $key, mixed $value, int $ttl): void
    {
        $serialized = serialize($value);

        if ($ttl > 0) {
            $this->redis->setex($this->keyPrefix . $key, $ttl, $serialized);
        }
        else {
            $this->redis->set($this->keyPrefix . $key, $serialized);
        }
    }

    public function clear(): void
    {
        $pattern = $this->keyPrefix . '*';
        $cursor = null;

        do {
            $keys = $this->redis->scan($cursor, $pattern, 1000);

            if ($keys !== false && $keys !== []) {
                $this->redis->del(...$keys);
            }
        } while ($cursor !== 0);
    }
}
