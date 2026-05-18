<?php

declare(strict_types=1);

namespace Medas\Cache\Interfaces;

interface TransformsKeys
{
    public function transformKey(array|string $key): string;
}
