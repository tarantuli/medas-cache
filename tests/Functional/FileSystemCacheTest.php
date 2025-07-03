<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\FileSystemCache;

class FileSystemCacheTest extends BaseCacheTestClass
{
    protected function getCache(): FileSystemCache
    {
        $cache = new FileSystemCache(__DIR__ . '/../../var/cache');

        $cache->clear();

        return $cache;
    }
}
