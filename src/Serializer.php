<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\Attributes\Service;

#[Service]
class Serializer implements Interfaces\Serializer
{
    public function serialize(mixed $value): string
    {
        return serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
