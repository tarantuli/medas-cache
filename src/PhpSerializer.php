<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Serializer, Type};

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
