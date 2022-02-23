<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\ServiceManager\Attributes\Service;
use Medas\ServiceManager\Interfaces\Cache;
use Medas\ServiceManager\Interfaces\Clearable;

#[Service]
class CacheManager
{
    /** @var Cache[] */
    private array $caches = [];

    private Interfaces\Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer();
    }

    /**
     * The return value  is an object of type $type. This is specified in PhpStorm in .phpstorm.meta.php
     */
    public function create(string $type, string $namespace): object
    {
        $cache = new $type($namespace, $this->serializer);

        $this->register($cache);

        return $cache;
    }

    public function register(Cache $cache): void
    {
        $this->caches[] = $cache;
    }

    public function clear(): void
    {
        foreach ($this->caches as $cache) {
            if ($cache instanceof Clearable) {
                $cache->clear();
            }
        }
    }
}
