<?php

declare(strict_types=1);

namespace Medas\CacheTest\Functional;

use Medas\Core\Interfaces\{Cache, NotCacheable};
use PHPUnit\Framework\TestCase;

abstract class BaseCacheTestClass extends TestCase
{
    abstract protected function getCache(): Cache;

    public function testAddItem(): void
    {
        $cache = $this->getCache();
        $value = $cache->get('testAddItem', fn() => 'test-value');

        self::assertEquals('test-value', $value);

        $value = $cache->get('testAddItem', fn() => 'new-test-value');

        self::assertEquals('test-value', $value);
    }

    public function testSerializeClosures(): void
    {
        $cache = $this->getCache();
        $closure = fn() => 'a value';

        $this->expectException(\Exception::class);

        $cache->get('testSerializeClosure', fn() => $closure);
    }

    public function testNotCacheableIsReturnedButNotStored(): void
    {
        $cache = $this->getCache();

        $notCacheable = new class implements NotCacheable
        {
        };

        self::assertSame($notCacheable, $cache->get('testNotCacheable', fn() => $notCacheable));
        self::assertFalse($cache->contains('testNotCacheable'));
    }

    public function testNotCacheableRemovesOlderValue(): void
    {
        $cache = $this->getCache();

        $cache->set('testNotCacheableReplaces', 'old-value');

        $cache->set('testNotCacheableReplaces', new class implements NotCacheable
        {
        });

        self::assertFalse($cache->contains('testNotCacheableReplaces'));

        self::assertEquals(
            'new-value',
            $cache->get('testNotCacheableReplaces', fn() => 'new-value')
        );
    }
}
