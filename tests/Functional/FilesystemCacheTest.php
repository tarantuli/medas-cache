<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\FilesystemCache;
use PHPUnit\Framework\TestCase;

class FilesystemCacheTest extends TestCase
{
    public function testAddItem(): void
    {
        $cache = new FileSystemCache(__DIR__ . '/../../var/cache');
        $cache->clear();

        $value = $cache->get('testAddItem', fn() => 'test-value');
        self::assertEquals('test-value', $value);

        $value = $cache->get('testAddItem', fn() => 'new-test-value');
        self::assertEquals('test-value', $value);
    }
}
