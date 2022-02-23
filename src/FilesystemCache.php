<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Cache\Interfaces\HasKeyRegister;
use Medas\FileSystem\DirectoryManager;

class FilesystemCache extends MemoryCache implements HasKeyRegister
{
    public function exists(string $key): bool
    {
        return parent::exists($key) || file_exists($this->getPath($key));
    }

    private function getPath(string $key): string
    {
        return $this->namespace . DIRECTORY_SEPARATOR . $key;
    }

    public function fetch(string $key): mixed
    {
        if (parent::exists($key)) {
            return parent::fetch($key);
        }

        $serializedValue = file_get_contents($this->getPath($key));
        $value = $this->serializer->unserialize($serializedValue);

        parent::store($key, $value);

        return $value;
    }

    public function store(string $key, mixed $value): void
    {
        parent::store($key, $value);

        if (!file_exists($this->namespace)) {
            mkdir($this->namespace);
        }

        $serializedValue = $this->serializer->serialize($value);
        file_put_contents($this->getPath($key), $serializedValue);
    }

    public function registerKey(array|string $key, string $normalizedKey): void
    {
        $keyFile = $this->getPath('key_index');
        $keys = file_exists($keyFile) ? json_decode(file_get_contents($keyFile), true) : [];

        if (array_key_exists($normalizedKey, $keys)) {
            return;
        }

        $keys[$normalizedKey] = $key;
        file_put_contents($keyFile, json_encode($keys, JSON_PRETTY_PRINT));
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            unlink($path);
        }

        parent::delete($key);
    }

    public function isSupported(): bool
    {
        if (!file_exists($this->namespace)) {
            service(DirectoryManager::class)->create($this->namespace);
        }

        return is_dir($this->namespace) && is_writeable($this->namespace);
    }

    public function clear(): void
    {
        $files = service(DirectoryManager::class)->recursiveFind($this->namespace, '//');

        foreach ($files as $file) {
            unlink($file);
        }

        parent::clear();
    }
}
