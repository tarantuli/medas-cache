<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\CacheManager;
use Medas\Cache\FilesystemCache;
use Medas\ServiceManager\Interfaces\Cache;
use PHPUnit\Framework\TestCase;

class CacheManagerTest extends TestCase
{
    public function testCreate(): void
    {
        $cache = service(CacheManager::class)->create(FilesystemCache::class, $this::class);

        self::assertInstanceOf(Cache::class, $cache);
    }
}
