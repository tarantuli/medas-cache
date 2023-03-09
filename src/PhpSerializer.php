<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\Interfaces\Serializer;
use Medas\ServiceManager\Interfaces\Type;

class PhpSerializer implements Serializer
{
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    public function unserialize(mixed $value, Type $type = null): mixed
    {
        return unserialize($value);
    }
}
