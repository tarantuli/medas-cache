<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\{AsSingleton, BasePackage};

class CachePackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return $this->dependenciesByClass([
        ]);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
