<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{
    Interfaces\FileSystemCache as FileSystemCacheInterface,
    Interfaces\Serializer,
    Serializers\PhpSerializer
};
use Medas\FileSystem\{DirectoryCreator, Exceptions\FailedToWriteFile, FileFinder, PathNormalizer};

/**
 * Stores each entry in its own file: a first line with the expiry time (a timestamp, or "never"), followed by the
 * serialized value.
 *
 * Entries are read without locking. That's safe because they're written atomically: to a temporary file first, which
 * is then renamed over the entry, so a reader sees either the old or the new entry, never a partially written one.
 * Reading an entry is a single file read; values read are kept in a memory cache for the rest of the request.
 *
 * Paths are derived from a hash of the key within the base directory, so they can't point outside it.
 */
class FileSystemCache extends BaseCache implements FileSystemCacheInterface
{
    private const string ENTRY_EXTENSION = '.entry';
    private const string NEVER_EXPIRES = 'never';

    /**
     * How long to keep retrying to replace or remove an entry. On Windows that fails while another process has the
     * file open, which is only ever briefly.
     */
    private const float MAX_RETRY_TIME = 1.0;

    private DirectoryCreator $directoryManager;
    private FileFinder $fileFinder;
    private PathNormalizer $pathNormalizer;

    public function __construct(
        private string               $baseDirectory,
        private Serializer|null      $serializer = null,
        private readonly MemoryCache $memoryCache = new MemoryCache(),
        private readonly bool        $clearCacheOnInstall = true,
    )
    {
        // Create new instances instead of injecting services
        // because caches are used by the service manager quite early on
        $this->directoryManager = new DirectoryCreator();
        $this->fileFinder = new FileFinder();
        $this->pathNormalizer = new PathNormalizer();
        $this->baseDirectory = $this->pathNormalizer->normalize($this->baseDirectory);

        if (!file_exists($this->baseDirectory)) {
            $this->directoryManager->create($this->baseDirectory);
        }

        if ($this->clearCacheOnInstall) {
            $this->registerDirToClear();
        }

        if ($this->serializer === null) {
            $this->serializer = new PhpSerializer();
        }

        parent::__construct();
    }

    private function registerDirToClear(): void
    {
        // medas/core will set the working directory equal to the project root, so this path should be relative to the project root
        $path = 'var/dirs-to-clear';
        $fileName = $path . DIRECTORY_SEPARATOR . sha1($this->baseDirectory);

        if (!file_exists($fileName)) {
            $this->directoryManager->create($path);

            file_put_contents($fileName, $this->baseDirectory . "\n");
        }
    }

    /**
     * Overridden so a value in the cache costs a single file read, instead of one to check whether it exists
     * and another to fetch it.
     */
    public function get(array|string $key, callable $getter, int $ttl = 0): mixed
    {
        $normalizedKey = $this->normalizeKey($key);

        if ($this->memoryCache->exists($normalizedKey)) {
            return $this->memoryCache->fetch($normalizedKey);
        }

        $entry = $this->readEntry($normalizedKey);

        if ($entry !== null) {
            return $entry[0];
        }

        $value = $getter();

        $this->store($normalizedKey, $value, $ttl);

        return $value;
    }

    public function fetch(string $key): mixed
    {
        if ($this->memoryCache->exists($key)) {
            return $this->memoryCache->fetch($key);
        }

        $entry = $this->readEntry($key);

        if ($entry === null) {
            throw new Exceptions\CacheEntryExpired($key);
        }

        return $entry[0];
    }

    public function exists(string $key): bool
    {
        // Reading the entry right away means a fetch() that follows is served from the memory cache
        return $this->memoryCache->exists($key) || $this->readEntry($key) !== null;
    }

    /**
     * Reads the entry from its file and keeps its value in the memory cache. The value is wrapped in an array, so a
     * cached null can be told apart from a missing entry.
     *
     * @return array{mixed}|null Null when there is no entry, or it has expired or can't be read
     */
    private function readEntry(string $key): array|null
    {
        // No separate existence check: a missing file is just a failed read
        $contents = @file_get_contents($this->getPath($key));

        if ($contents === false) {
            return null;
        }

        $headerEnd = strpos($contents, "\n");

        if ($headerEnd === false) {
            return null;
        }

        $expiresAt = substr($contents, 0, $headerEnd);
        $ttl = 0;

        if ($expiresAt !== self::NEVER_EXPIRES) {
            $ttl = (int) $expiresAt - time();

            if ($ttl <= 0) {
                return null;
            }
        }

        try {
            $value = $this->serializer->unserialize(substr($contents, $headerEnd + 1));
        }
        catch (\Throwable) {
            // A corrupted entry is treated as a missing one, so it's simply rebuilt
            return null;
        }

        $this->memoryCache->store($key, $value, $ttl);

        return [$value];
    }

    public function store(string $key, mixed $value, int $ttl): void
    {
        $this->memoryCache->store($key, $value, $ttl);

        $contents = ($ttl > 0 ? (string) (time() + $ttl) : self::NEVER_EXPIRES)
            . "\n"
            . $this->serializer->serialize($value);

        $path = $this->getPath($key);

        $this->directoryManager->create(pathinfo($path, PATHINFO_DIRNAME));
        $this->writeAtomically($path, $contents);
    }

    private function writeAtomically(string $path, string $contents): void
    {
        // Unique per writer, so concurrent writers of the same entry don't write into each other's file
        $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temporaryPath, $contents) === false) {
            throw new FailedToWriteFile($temporaryPath);
        }

        if (!$this->retry(fn() => @rename($temporaryPath, $path))) {
            @unlink($temporaryPath);

            throw new FailedToWriteFile($path);
        }
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);

        $this->memoryCache->delete($key);

        if (!$this->retry(fn() => !file_exists($path) || @unlink($path))) {
            throw new FailedToWriteFile($path);
        }
    }

    /**
     * Calls $operation until it returns true, backing off between attempts, for at most MAX_RETRY_TIME seconds.
     */
    private function retry(\Closure $operation): bool
    {
        $deadline = microtime(true) + self::MAX_RETRY_TIME;
        $delay = 1000;

        while (!$operation()) {
            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep($delay);

            $delay = min($delay * 2, 50000);
        }

        return true;
    }

    public function isSupported(): bool
    {
        return is_dir($this->baseDirectory) && is_writeable($this->baseDirectory);
    }

    public function clear(): void
    {
        $files = $this->fileFinder->find($this->baseDirectory, '/.+/');

        foreach ($files as $file) {
            @unlink($file);
        }

        $this->memoryCache->clear();
    }

    public function baseDirectory(): string
    {
        return $this->baseDirectory;
    }

    public function clearCacheOnInstall(): bool
    {
        return $this->clearCacheOnInstall;
    }

    public function transformKey(array|string $key): string
    {
        return $this->getPath($this->normalizeKey($key));
    }

    private function getPath(string $key): string
    {
        $hashedKey = sha1($key);

        return $this->baseDirectory
            . DIRECTORY_SEPARATOR
            . substr($hashedKey, 0, 1)
            . DIRECTORY_SEPARATOR
            . substr($hashedKey, 1, 1)
            . DIRECTORY_SEPARATOR
            . substr($hashedKey, 2)
            . self::ENTRY_EXTENSION;
    }
}
