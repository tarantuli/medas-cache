<?php

declare(strict_types=1);

namespace Medas\Cache\Interfaces;

interface NormalizesKeys
{
    public function normalizeKey(array|string $key): string;
}
