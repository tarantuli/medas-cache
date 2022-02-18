<?php

declare(strict_types=1);

namespace Medas\Cache;

interface Cache
{
    public function get(string|array $key, callable $getter): mixed;

    public function remove(string|array $key): void;
}
