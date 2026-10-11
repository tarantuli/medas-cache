<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Attributes\Service;
use Medas\FileSystem\DirectoryCreator;

/**
 * Stores values in APCu shared memory, under a prefix derived from the namespace and the current generation.
 *
 * APCu memory belongs to the web server process (Apache, PHP-FPM): a CLI process such as a post-install script gets
 * its own, empty memory, so it can't clear the web server's entries. Instead, the generation is kept in a small marker
 * file, in a directory registered in var/dirs-to-clear. When the post-install script clears that directory,
 * the next request finds no marker, starts a new generation, and so stops seeing the entries of the previous one.
 * Those are left for APCu to evict when it needs the memory.
 *
 * Reading the marker costs one small file read when the cache is constructed.
 *
 * APCu unserializes objects on every fetch, so values are also kept in a memory cache for the rest of the request: a
 * value asked for several times per request is only fetched and unserialised once.
 */
#[Service]
class ApcuCache extends BaseCache
{
    /**
     * Whether APCu can be used in this process. The constructor throws when it can't, so this lets a bootstrap choose
     * another cache up front - e.g. on the command line, where APCu is usually disabled (apc.enable_cli).
     */
    public static function isAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    private readonly string $namespacePrefix;
    private readonly string $generationFile;
    private readonly string $generationDirectory;
    private string $keyPrefix;

    /**
     * @param string $generationDirectory Relative to the project root, which medas/core makes the working directory
     */
    public function __construct(
        string                       $namespace,
        private readonly MemoryCache $memoryCache = new MemoryCache(),
        string                       $generationDirectory = 'var/apcu-generations',
    )
    {
        parent::__construct();

        $this->namespacePrefix = sha1($namespace) . ':';
        $this->generationDirectory = $generationDirectory;
        $this->generationFile = $generationDirectory . DIRECTORY_SEPARATOR . sha1($namespace);
        $this->keyPrefix = $this->namespacePrefix . $this->generation() . ':';
    }

    public function isSupported(): bool
    {
        return self::isAvailable();
    }

    /**
     * Overridden so a value in the cache costs a single APCu fetch, instead of a check whether it exists
     * followed by a fetch.
     */
    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->memoryCache->exists($normalizedKey)) {
            return $this->memoryCache->fetch($normalizedKey);
        }

        $entry = $this->fetchEntry($normalizedKey);

        if ($entry !== null) {
            return $entry[0];
        }

        $value = $getter();

        $this->store($normalizedKey, $value, $ttl);

        return $value;
    }

    public function delete(string $key): void
    {
        $this->memoryCache->delete($key);

        apcu_delete($this->keyPrefix . $key);
    }

    public function exists(string $key): bool
    {
        // Fetching right away means a fetch() that follows is served from the memory cache
        return $this->memoryCache->exists($key) || $this->fetchEntry($key) !== null;
    }

    public function fetch(string $key): mixed
    {
        if ($this->memoryCache->exists($key)) {
            return $this->memoryCache->fetch($key);
        }

        $entry = $this->fetchEntry($key);

        if ($entry === null) {
            throw new Exceptions\ApcuKeyDisappeared($key);
        }

        return $entry[0];
    }

    /**
     * Fetches the entry from APCu and keeps its value in the memory cache. The value is wrapped in an array, so a
     * cached false or null can be told apart from a missing entry.
     *
     * @return array{mixed}|null
     */
    private function fetchEntry(string $key): array|null
    {
        $value = apcu_fetch($this->keyPrefix . $key, $success);

        if (!$success) {
            return null;
        }

        // APCu doesn't tell the remaining time to live; the memory cache only lasts for the request anyway
        $this->memoryCache->store($key, $value, 0);

        return [$value];
    }

    public function store(string $key, mixed $value, int $ttl): void
    {
        $this->memoryCache->store($key, $value, $ttl);

        apcu_store($this->keyPrefix . $key, $value, $ttl);
    }

    /**
     * Deletes the entries of all generations of this namespace from this process's APCu memory, and removes the
     * marker, so other processes (e.g. the web server, when this runs on the command line) start a new generation too.
     */
    public function clear(): void
    {
        // Passing the iterator lets APCu delete the matches itself, instead of deleting while iterating
        apcu_delete(new \APCUIterator('/^' . preg_quote($this->namespacePrefix, '/') . '/', APC_ITER_KEY));

        $this->memoryCache->clear();

        if (is_file($this->generationFile)) {
            @unlink($this->generationFile);
        }

        $this->keyPrefix = $this->namespacePrefix . $this->generation() . ':';
    }

    /**
     * Returns the current generation from the marker file, starting a new one when there is no marker.
     */
    private function generation(): string
    {
        $generation = @file_get_contents($this->generationFile);

        if ($generation !== false && $generation !== '') {
            return $generation;
        }

        return $this->startGeneration();
    }

    private function startGeneration(): string
    {
        new DirectoryCreator()->create($this->generationDirectory);

        $this->registerDirectoryToClear();

        // Mode "x" only creates the file when it doesn't exist, so of concurrent requests only one starts the new
        // generation, and the others use it
        $handle = @fopen($this->generationFile, 'x');

        if ($handle !== false) {
            $generation = bin2hex(random_bytes(8));

            fwrite($handle, $generation);

            fclose($handle);

            return $generation;
        }

        // Another process created the marker just now; it may not have written to it yet
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $generation = @file_get_contents($this->generationFile);

            if ($generation !== false && $generation !== '') {
                return $generation;
            }

            usleep(1000);
        }

        // Without a readable marker, use a generation of our own: entries written with it are simply never read again
        return bin2hex(random_bytes(8));
    }

    /**
     * Registers the generation directory, the same way FileSystemCache registers its directory, so the post-install
     * script clears it.
     */
    private function registerDirectoryToClear(): void
    {
        $directory = realpath($this->generationDirectory);

        if ($directory === false) {
            return;
        }

        $path = 'var/dirs-to-clear';
        $fileName = $path . DIRECTORY_SEPARATOR . sha1($directory);

        if (!file_exists($fileName)) {
            new DirectoryCreator()->create($path);

            file_put_contents($fileName, $directory . "\n");
        }
    }
}
