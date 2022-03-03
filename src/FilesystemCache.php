<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Cache\Interfaces\HasKeyRegister;
use Medas\FileSystem\DirectoryManager;
use Medas\ServiceManager\Interfaces\Serializer;

class FilesystemCache extends MemoryCache implements HasKeyRegister
{
    private DirectoryManager $directoryManager;

    public function __construct(string $namespace, ?Serializer $serializer = null)
    {
        parent::__construct($namespace, $serializer);
        $this->directoryManager = new DirectoryManager();
    }

    public function exists(string $key): bool
    {
        return parent::exists($key) || file_exists($this->getPath($key));
    }

    private function getPath(string $key): string
    {
        return $this->namespace
            . DIRECTORY_SEPARATOR . substr($key, 0, 1)
            . DIRECTORY_SEPARATOR . substr($key, 1, 1)
            . DIRECTORY_SEPARATOR . $key;
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

        $serializedValue = $this->serializer->serialize($value);

        $path = $this->getPath($key);
        $this->directoryManager->create(pathinfo($path, PATHINFO_DIRNAME));
        file_put_contents($path, $serializedValue);
    }

    public function registerKey(array|string $key, string $normalizedKey): void
    {
        $keyFile = $this->namespace . DIRECTORY_SEPARATOR . 'key_index';
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
            $this->directoryManager->create($this->namespace);
        }

        return is_dir($this->namespace) && is_writeable($this->namespace);
    }

    public function clear(): void
    {
        $files = $this->directoryManager->recursiveFind($this->namespace, '//');

        foreach ($files as $file) {
            unlink($file);
        }

        parent::clear();
    }
}
