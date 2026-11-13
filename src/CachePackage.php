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

    public function initialize(ServiceConfigBuilder $config): void
    {
        // Bound by hand instead of with #[Service], which would also map MemoryCache to Cache, Clearable and BaseCache,
        // so a parameter typed Cache would quietly get a request-scoped memory cache. CacheManager looks this binding
        // up when the 'memory' cache is first requested.
        $config->addTypeBinding(MemoryCache::class, MemoryCacheInterface::class);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
