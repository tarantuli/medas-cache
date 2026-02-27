<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{AsSingleton, BasePackage};
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

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
