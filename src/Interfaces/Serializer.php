<?php

declare(strict_types=1);

namespace Medas\Cache\Interfaces;

interface Serializer
{
    public function serialize(mixed $value): string;

    public function unserialize(string $value): mixed;
}
