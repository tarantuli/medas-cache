<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Cache\FilesystemCache;
use PHPUnit\Framework\TestCase;

class FilesystemCacheTest extends TestCase
{
    public function testAddItem(): void
    {
        $cache = $this->getCache();

        $value = $cache->get('testAddItem', fn() => 'test-value');
        self::assertEquals('test-value', $value);

        $value = $cache->get('testAddItem', fn() => 'new-test-value');
        self::assertEquals('test-value', $value);
    }

    private function getCache(): FilesystemCache
    {
        $cache = new FileSystemCache(__DIR__ . '/../../var/cache');
        $cache->clear();
        return $cache;
    }

    public function testSerializeClosures(): void
    {
        $cache = $this->getCache();

        $closure = fn() => 'a value';

        $singleValue = $cache->get('testSerializeClosure', fn() => $closure);
        self::assertInstanceOf(\Closure::class, $singleValue);

        $singleValue = $cache->get('testSerializeClosure', fn() => $closure);
        self::assertInstanceOf(\Closure::class, $singleValue);

        $multipleValues = $cache->get('testSerializeClosures', fn() => [$closure, $closure]);
        self::assertInstanceOf(\Closure::class, $multipleValues[0]);

        $multipleValues = $cache->get('testSerializeClosures', fn() => [$closure, $closure]);
        self::assertInstanceOf(\Closure::class, $multipleValues[0]);
    }
}
