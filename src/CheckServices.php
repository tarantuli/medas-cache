<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable, ServiceManager};

/**
 * Wraps a cache and refuses values that contain a service object.
 *
 * Before a value is stored, it's searched recursively: arrays item by item, and objects through `__serialize()` when
 * they have it, or through all their initialised properties otherwise. Each object is searched once, so values
 * with circular references are fine. An object counts as a service when the service manager has an implementing
 * class for its class.
 *
 * The check needs the service manager, so it's skipped until `serviceManagerIsInitialized()` has been called.
 *
 * @assumes The project or development environment that wants the check calls `serviceManagerIsInitialized()` once
 *          the service manager can resolve types. Until then, values are passed on unchecked.
 * @state Remembers whether the service manager is initialised, and the service manager once it's been looked up.
 * @performance Walks the whole value with reflection on every store, so it costs more the larger the value is.
 */
class CheckServices implements Cache, Clearable
{
    private ServiceManager|null $serviceManager = null;
    private bool $serviceManagerIsInitialized = false;

    public function __construct(
        /** The cache that stores the values once they've been checked. */
        private readonly Cache $inner,
    )
    {
    }

    /**
     * Turns the check on.
     */
    public function serviceManagerIsInitialized(): void
    {
        $this->serviceManagerIsInitialized = true;
    }

    /**
     * Checks the value from `$getter` before the inner cache stores it. A value that's already cached isn't checked
     * again.
     *
     * @throws Exceptions\ValueContainsService When the value from `$getter` contains a service.
     */
    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $wrappedGetter = function () use ($getter, $key) {
            $value = $getter();

            $this->check($key, $value);

            return $value;
        };

        return $this->inner->get($key, $wrappedGetter, $ttl);
    }

    /**
     * @throws Exceptions\ValueContainsService When the value contains a service.
     */
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

        $this->checkValue($key, $value, new \SplObjectStorage());
    }

    /**
     * @param \SplObjectStorage $visited The objects already searched, so circular references don't recurse endlessly
     */
    private function checkValue(string|array $key, mixed $value, \SplObjectStorage $visited): void
    {
        if (is_object($value)) {
            if ($visited->contains($value)) {
                return;
            }

            $visited->attach($value);

            if ($this->serviceManager->findImplementingClass($value::class) !== null) {
                throw new Exceptions\ValueContainsService($key, $value);
            }

            if (method_exists($value, '__serialize')) {
                foreach ($value->__serialize() as $item) {
                    $this->checkValue($key, $item, $visited);
                }
            }
            else {
                $reflection = new \ReflectionClass($value);

                foreach ($reflection->getProperties() as $property) {
                    if ($property->isInitialized($value)) {
                        $this->checkValue($key, $property->getValue($value), $visited);
                    }
                }
            }
        }
        elseif (is_iterable($value)) {
            foreach ($value as $item) {
                $this->checkValue($key, $item, $visited);
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

    /**
     * Clears the inner cache when it implements `Clearable`.
     */
    public function clear(): void
    {
        if ($this->inner instanceof Clearable) {
            $this->inner->clear();
        }
    }
}
