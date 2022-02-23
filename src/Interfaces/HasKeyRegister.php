<?php

declare(strict_types=1);

namespace Medas\Cache\Interfaces;

interface HasKeyRegister
{
    public function registerKey(array|string $key, string $normalizedKey): void;
}
