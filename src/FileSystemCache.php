<?php

declare(strict_types=1);

namespace Medas\Cache;

use Medas\Core\Interfaces\{FileSystemCache as FileSystemCacheInterface, Serializer};
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
        Serializer|null              $serializer = null,
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

        $this->registerDirToClear();

        parent::__construct($serializer);
    }

    private function registerDirToClear(): void
    {
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

        $serializedValue = $this->fileWriter->read($this->getPath($key));
        $value = $this->serializer->unserialize($serializedValue);

        $this->memoryCache->store($key, $value);

        return $value;
    }

    public function exists(string $key): bool
    {
        return $this->memoryCache->exists($key) || file_exists($this->getPath($key));
    }

    public function store(string $key, mixed $value): void
    {
        $this->memoryCache->store($key, $value);

        $serializedValue = $this->serializer->serialize($value);
        $path = $this->getPath($key);

        $this->directoryManager->create(pathinfo($path, PATHINFO_DIRNAME));
        $this->fileWriter->write($path, $serializedValue);
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);

        if (file_exists($path)) {
            unlink($path);
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
        if (!file_exists($this->baseDirectory)) {
            $this->directoryManager->create($this->baseDirectory);
        }

        return is_dir($this->baseDirectory) && is_writeable($this->baseDirectory);
    }

    public function clear(): void
    {
        $files = $this->fileFinder->find($this->baseDirectory, '//');

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
