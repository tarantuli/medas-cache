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

    public function store(string $key, string $value): void
    {
        if (!file_exists($this->namespace)) {
            mkdir($this->namespace);
        }

        file_put_contents($this->getPath($key), $value);
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
