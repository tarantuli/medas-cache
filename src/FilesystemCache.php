<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\FileSystem\DirectoryManager;

class FilesystemCache extends BaseCache
{
    public function exists(string $key): bool
    {
        return file_exists($this->getPath($key));
    }

    private function getPath(string $key): string
    {
        return $this->namespace . DIRECTORY_SEPARATOR . $key;
    }

    public function fetch(string $key): string
    {
        return file_get_contents($this->getPath($key));
    }

    public function store(string|array $key, string $normalizedKey, string $value): void
    {
        if (!file_exists($this->namespace)) {
            mkdir($this->namespace);
        }

        file_put_contents($this->getPath($normalizedKey), $value);
        $this->registerKey($key, $normalizedKey);
    }

    private function registerKey(array|string $key, string $normalizedKey): void
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
    }
}
