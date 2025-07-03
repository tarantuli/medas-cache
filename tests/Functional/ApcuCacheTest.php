<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\ApcuCache;

class ApcuCacheTest extends BaseCacheTestClass
{
    protected function getCache(): ApcuCache
    {
        $cache = new ApcuCache(__CLASS__);

        $cache->clear();

        return $cache;
    }
}
