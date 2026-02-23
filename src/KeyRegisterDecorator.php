<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{Cache, Clearable};

readonly class KeyRegisterDecorator implements Interfaces\HasKeyRegister, Cache, Clearable
{
    public function __construct(
        private Cache  $cache,
        private string $baseDirectory,
    )
    {
    }

    public function get(array|string $key, callable $getter): mixed
    {
        return $this->cache->get($key, $getter);
    }

    public function set(array|string $key, mixed $value): void
    {
        $this->cache->set($key, $value);
    }

    public function remove(array|string $key): void
    {
        $this->cache->remove($key);
    }

    public function registerKey(array|string $key, string $normalizedKey): void
    {
        $keys = $this->getKeys();

        if (array_key_exists($normalizedKey, $keys)) {
            return;
        }

        $keys[$normalizedKey] = $key;

        file_put_contents($this->keyFilePath(), json_encode($keys, JSON_PRETTY_PRINT));
    }

    private function getKeys(): array
    {
        $keyFile = $this->keyFilePath();
        $keys = file_exists($keyFile) ? json_decode(file_get_contents($keyFile), true) : [];

        if ($keys === null) {
            // The key file is corrupt
            unlink($keyFile);

            $keys = [];
        }

        return $keys;
    }

    public function clear(): void
    {
        file_put_contents($this->keyFilePath(), '{}');
    }

    private function keyFilePath(): string
    {
        return $this->baseDirectory . DIRECTORY_SEPARATOR . 'key_index';
    }
}
