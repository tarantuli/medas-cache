<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\AsSingleton;
use Medas\FileSystem\FileSystemPackage;
use Medas\ServiceManager\BasePackage;

class CachePackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return $this->dependenciesByClass([
            FileSystemPackage::class,
        ]);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
