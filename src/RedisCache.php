<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\Serializer;

class RedisCache extends BaseCache
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $namespace,
        Serializer|null         $serializer = null,
    )
    {
        $this->redis->connect('127.0.0.1');
        $this->redis->setOption(\Redis::OPT_PREFIX, $this->namespace . ':');
        parent::__construct($serializer);
    }

    public function __destruct()
    {
        $this->redis->close();
    }

    public function exists(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function fetch(string $key): mixed
    {
        return $this->serializer->unserialize($this->redis->get($key));
    }

    public function store(string $key, mixed $value): void
    {
        $this->redis->set($key, $this->serializer->serialize($value));
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function isSupported(): bool
    {
        return class_exists(\Redis::class);
    }

    public function clear(): void
    {
        if ($keys = $this->redis->keys($this->redis->getOption(\Redis::OPT_PREFIX) . ':*')) {
            $this->redis->unlink(... $keys);
        }
    }
}
