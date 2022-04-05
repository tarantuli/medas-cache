<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\RedisCache;

class RedisCacheTest extends BaseCacheTest
{
    protected function getCache(): RedisCache
    {
        $cache = new RedisCache(new \Redis(), RedisCache::class);
        $cache->clear();

        return $cache;
    }
}
