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
        $this->wrapClosure($value);

        if (is_array($value)) {
            array_walk_recursive($value, function (&$leaf) { $this->wrapClosure($leaf);});
        }

        return serialize($value);
    }

    private function wrapClosure(mixed &$value): void
    {
        if ($value instanceof \Closure) {
            $value = new SerializableClosure($value);
        }
    }

    public function unserialize(string $value): mixed
    {
        $value = unserialize($value);

        $this->unwrapClosure($value);

        if (is_array($value)) {
            array_walk_recursive($value, function (&$leaf) { $this->unwrapClosure($leaf);});
        }

        return $value;
    }

    private function unwrapClosure(mixed &$value): void
    {
        if ($value instanceof SerializableClosure) {
            $value = $value->getClosure();
        }
    }
}
