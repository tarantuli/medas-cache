<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\FilesystemCache;

class FilesystemCacheTest extends BaseCacheTest
{
    protected function getCache(): FilesystemCache
    {
        $cache = new FileSystemCache(__DIR__ . '/../../var/cache');
        $cache->clear();
        return $cache;
    }
}
