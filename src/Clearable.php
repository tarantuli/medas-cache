<?php

declare(strict_types=1);

namespace Medas\Cache;

interface Clearable
{
    public function clear(): void;
}
