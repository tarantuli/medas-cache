<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{
    AsSingleton,
    BasePackage,
    Interfaces\MemoryCache as MemoryCacheInterface,
    Interfaces\ServiceConfigBuilder
};
use Medas\FileSystem\FileSystemPackage;

class CachePackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return [
            FileSystemPackage::instance(),
        ];
    }

    /**
     * Binds `MemoryCache` to @class(Medas\Core\Interfaces\MemoryCache), which `CacheManager` looks up when the
     * `'memory'` cache is first requested.
     *
     * It's bound by hand instead of with `#[Service]`, which would also map it to `Cache`, `Clearable` and
     * `BaseCache`, so a parameter typed `Cache` would quietly get a request-scoped memory cache.
     */
    public function initialize(ServiceConfigBuilder $config): void
    {
        $config->addTypeBinding(MemoryCache::class, MemoryCacheInterface::class);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
