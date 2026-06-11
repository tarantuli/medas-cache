<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\Cache;
use Medas\ServiceManager\ServiceManager;

class CheckServices implements Cache
{
    private ServiceManager|null $serviceManager = null;
    private bool $serviceManagerIsInitialized = false;

    public function __construct(
        private readonly Cache $inner,
    )
    {
    }

    public function serviceManagerIsInitialized(): void
    {
        $this->serviceManagerIsInitialized = true;
    }

    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $wrappedGetter = function () use ($getter, $key) {
            $value = $getter();

            $this->check($key, $value);

            return $value;
        };

        return $this->inner->get($key, $wrappedGetter, $ttl);
    }

    public function set(string|array $key, mixed $value, int $ttl = 0): void
    {
        $this->check($key, $value);
        $this->inner->set($key, $value, $ttl);
    }

    private function check(string|array $key, mixed $value): void
    {
        if (!$this->serviceManagerIsInitialized) {
            return;
        }

        if ($this->serviceManager === null) {
            $this->serviceManager = sm();
        }

        if (is_object($value) && $this->serviceManager->findImplementingClass($value::class) !== null) {
            throw new Exceptions\ValueContainsService($key);
        }

        if (is_object($value)) {
            $reflection = new \ReflectionClass($value);

            foreach ($reflection->getProperties() as $property) {
                if ($property->isInitialized($value)) {
                    $this->check($key, $property->getValue($value));
                }
            }
        }

        if (is_iterable($value)) {
            foreach ($value as $item) {
                $this->check($key, $item);
            }
        }
    }

    public function remove(array|string $key): void
    {
        $this->inner->remove($key);
    }

    public function contains(array|string $key): bool
    {
        return $this->inner->contains($key);
    }
}
