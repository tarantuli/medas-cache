<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\Attributes\Service;
use Opis\Closure\SerializableClosure;

#[Service]
class Serializer implements Interfaces\Serializer
{
    public function serialize(mixed $value): string
    {
        if ($value instanceof \Closure) {
            $value = new SerializableClosure($value);
        }

        return serialize($value);
    }

    public function unserialize(string $value): mixed
    {
        $value = unserialize($value);

        if ($value instanceof SerializableClosure) {
            $value = $value->getClosure();
        }

        return $value;
    }
}
