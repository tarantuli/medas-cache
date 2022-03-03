<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\{AsSingleton,BasePackage,Cache\CacheManager};
use Medas\FileSystem\FileSystemPackage;

class CachePackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return $this->dependenciesByClass([
            FileSystemPackage::class
        ]);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }

    public function postComposerInstall(): void
    {
        service(CacheManager::class)->clearAll();
    }
}
