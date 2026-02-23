<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\{
    Interfaces\FileSystemCache as FileSystemCacheInterface,
    Interfaces\Serializer,
    Serializers\PhpSerializer
};
use Medas\FileSystem\{
    DirectoryCreator,
    FileFinder,
    LockingFileWriter,
    PathNormalizer,
    PathValidator
};

class FileSystemCache extends BaseCache implements FileSystemCacheInterface
{
    private DirectoryCreator $directoryManager;
    private FileFinder $fileFinder;
    private PathNormalizer $pathNormalizer;
    private LockingFileWriter $fileWriter;

    public function __construct(
        private string               $baseDirectory,
        private Serializer|null      $serializer = null,
        private readonly MemoryCache $memoryCache = new MemoryCache(),
    )
    {
        // Create new instances instead of injecting services
        // because caches are used by the service manager quite early on
        $this->directoryManager = new DirectoryCreator();
        $this->fileFinder = new FileFinder();
        $this->pathNormalizer = new PathNormalizer();
        $this->baseDirectory = $this->pathNormalizer->normalize($this->baseDirectory);
        $pathValidator = new PathValidator(new PathNormalizer(), [$this->baseDirectory]);

        $this->fileWriter = new LockingFileWriter(
            $pathValidator,
            $this->baseDirectory . DIRECTORY_SEPARATOR . 'locks'
        );

        if (!file_exists($this->baseDirectory)) {
            $this->directoryManager->create($this->baseDirectory);
        }

        $this->registerDirToClear();

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

    public function fetch(string $key): mixed
    {
        if ($this->memoryCache->exists($key)) {
            return $this->memoryCache->fetch($key);
        }

        $path = $this->getPath($key);

        // Read value from the file
        $serializedValue = $this->fileWriter->read($path);
        $value = $this->serializer->unserialize($serializedValue);

        // Store in the memory cache
        // We need to read the TTL file again because we need its value to store the value in the memory cache
        $ttlPath = $path . '.ttl';
        $expiredAt = $this->fileWriter->read($ttlPath);
        $ttl = $expiredAt === 'never' ? 0 : (int) ($expiredAt - time());

        $this->memoryCache->store($key, $value, $ttl);

        return $value;
    }

    public function exists(string $key): bool
    {
        if ($this->memoryCache->exists($key)) {
            return true;
        }

        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return false;
        }

        $ttlPath = $path . '.ttl';
        $expiredAt = $this->fileWriter->read($ttlPath);

        if ($expiredAt === 'never') {
            return true;
        }

        return (int) ($expiredAt - time()) > 0;
    }

    public function store(string $key, mixed $value, int $ttl): void
    {
        $this->memoryCache->store($key, $value, $ttl);

        $serializedValue = $this->serializer->serialize($value);
        $path = $this->getPath($key);

        $this->directoryManager->create(pathinfo($path, PATHINFO_DIRNAME));
        $this->fileWriter->write($path, $serializedValue);

        $ttlFile = $path . '.ttl';

        $this->fileWriter->write($ttlFile, $ttl > 0 ? (string) (time() + $ttl) : 'never');
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            unlink($path);
        }

        $ttlFile = $path . '.ttl';

        if (file_exists($ttlFile)) {
            unlink($ttlFile);
        }

        $this->memoryCache->delete($key);
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
            . substr($hashedKey, 2);
    }

    public function isSupported(): bool
    {
        return is_dir($this->baseDirectory) && is_writeable($this->baseDirectory);
    }

    public function clear(): void
    {
        $files = $this->fileFinder->find($this->baseDirectory, '/.+/');

        foreach ($files as $file) {
            unlink($file);
        }

        $this->memoryCache->clear();
    }

    public function baseDirectory(): string
    {
        return $this->baseDirectory;
    }
}
