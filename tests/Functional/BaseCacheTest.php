<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\ServiceManager\Cache\Interfaces\Cache;
use PHPUnit\Framework\TestCase;

abstract class BaseCacheTest extends TestCase
{
    public function testAddItem(): void
    {
        $cache = $this->getCache();

        $value = $cache->get('testAddItem', fn() => 'test-value');
        self::assertEquals('test-value', $value);

        $value = $cache->get('testAddItem', fn() => 'new-test-value');
        self::assertEquals('test-value', $value);
    }

    abstract protected function getCache(): Cache;

    public function testSerializeClosures(): void
    {
        $cache = $this->getCache();

        $closure = fn() => 'a value';

        $this->expectException(\Exception::class);
        $cache->get('testSerializeClosure', fn() => $closure);
    }
}
