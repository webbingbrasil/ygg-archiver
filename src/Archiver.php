<?php

namespace Ygg\Archiver;

use Exception;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;
use Ygg\Archiver\Exception\FileNotFoundException;
use Ygg\Archiver\Exception\FileOpenException;
use Ygg\Archiver\Types\TypeInterface;
use Ygg\Archiver\Types\ZipType;

/**
 * Class Archiver
 * @package Ygg\Archiver
 */
class Archiver
{

    /**
     * Constant for extracting
     */
    private const WHITELIST = 1;
    /**
     * Constant for extracting
     */
    private const BLACKLIST = 2;
    /**
     * Constant for matching only strictly equal file names
     */
    private const EXACT_MATCH = 4;
    /**
     * @var string Represents the current location in the archive
     */
    private $currentFolder = '';
    /**
     * @var Filesystem Handler to the file system
     */
    private $filesystem;
    /**
     * @var TypeInterface Handler to the archive
     */
    private $repository;
    /**
     * @var string The path to the current zip file
     */
    private $archivePath;

    /**
     * Archiver constructor.
     */
    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (is_object($this->repository)) {
            $this->repository->close();
        }
    }

    /**
     * Create a archive instance
     *
     * @param string $path
     * @param string $type
     * @return Archiver
     * @throws Exception
     */
    public function make(string $path, string $type = ZipType::class): self
    {
        $new = $this->createArchivePath($path);
        if (!is_subclass_of($type, TypeInterface::class)) {
            throw new InvalidArgumentException('Class for '.$type.' must implement TypeInterface');
        }
        try {
            $this->repository = new $type($path, $new);
        } catch (Exception $e) {
            throw $e;
        }
        $this->archivePath = $path;

        return $this;
    }

    /**
     * @param array $files
     * @param int   $methodFlags
     * @return callable
     */
    private function getMatchingMethod(array $files, int $methodFlags): callable
    {
        if ($methodFlags & self::EXACT_MATCH) {
            return static function ($haystack) use ($files) {
                return in_array($haystack, $files, true);
            };
        }

        return static function ($haystack) use ($files) {
            return $this->startsWith($haystack, $files);
        };
    }

    /**
     * Extracts the opened archive to the specified location <br/>
     * you can provide an array of files and folders and define if they should be a white list
     * or a black list to extract.
     *
     * @param string $path
     * @param array  $files
     * @param int    $methodFlags
     */
    public function extractTo(string $path, array $files = [], int $methodFlags = self::BLACKLIST): void
    {
        if (!$this->filesystem->exists($path) && !$this->filesystem->makeDirectory($path, 0755, true)) {
            throw new RuntimeException('Failed to create folder');
        }

        $matchingMethod = $this->getMatchingMethod($files, $methodFlags);

        if ($methodFlags & self::WHITELIST) {
            $this->extractFilesInternal($path, $matchingMethod);
            return;
        }

        // blacklist - extract files that do not match with $matchingMethod
        $this->extractFilesInternal($path, static function ($filename) use ($matchingMethod) {
            return !$matchingMethod($filename);
        });
    }

    /**
     * Extracts matching files/folders from the opened archive to the specified location.
     *
     * @param string $path The path to extract to
     * @param string $regex regular expression used to match files. See @link http://php.net/manual/en/reference.pcre.pattern.syntax.php
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function extractMatchingRegexTo(string $path, string $regex): void
    {
        if (empty($regex)) {
            throw new InvalidArgumentException('Missing pass valid regex parameter');
        }

        $this->extractFilesInternal($path, static function ($filename) use ($regex) {
            $match = preg_match($regex, $filename);
            if ($match) {
                return true;
            }

            if (!$match) {
                throw new RuntimeException('regular expression match on '.$filename.' failed with error. Please check if pattern is valid regular expression.');
            }

            return false;
        });
    }

    /**
     * Gets the content of a single file if available
     *
     * @param $path
     * @return string|null
     * @throws FileNotFoundException
     */
    public function getFileContent($path): ?string
    {
        if ($this->repository->contains($path) === false) {
            throw new FileNotFoundException(sprintf('The file %s cannot be found', $path));
        }

        return $this->repository->getFileContent($path);
    }

    /**
     * Add files to archive
     *
     * @param string ...$paths
     * @return Archiver
     */
    public function add(string ...$paths): self
    {
        foreach ($paths as $path) {
            $this->addFile($path);
        }

        return $this;
    }

    /**
     * Add an empty dir to archive
     *
     * @param $dirName
     * @return Archiver
     */
    public function addEmptyDir($dirName): self
    {
        $this->repository->addEmptyDir($dirName);

        return $this;
    }

    /**
     * Add a file to the archive using its contents
     *
     * @param string $filename
     * @param string $content
     * @return Archiver
     */
    public function addFromString(string $filename, string $content): self
    {
        $this->addFromString($this->getInternalPath().$filename, $content);

        return $this;
    }

    /**
     * Get the status of archive
     * @return string
     */
    public function getStatus(): string
    {
        return $this->repository->getStatus();
    }

    /**
     * Remove files or folders from archive
     * @param string ...$paths
     * @return Archiver
     */
    public function remove(string ...$paths): self
    {
        $self = $this;
        $this->repository->each(static function ($file) use ($paths, $self) {
            if ($this->startsWith($file, $paths)) {
                $self->getArchive()->delete($file);
            }
        });

        return $this;
    }

    /**
     * Returns the path of archive
     *
     * @return string|null
     */
    public function getArchivePath(): ?string
    {
        return $this->archivePath;
    }

    /**
     * Sets the password to be used for decompressing
     *
     * @param string $password
     * @return bool
     */
    public function usePassword(string $password): bool
    {
        return $this->repository->usePassword($password);
    }

    /**
     * Closes the archive file
     */
    public function close(): void
    {
        if (null !== $this->repository) {
            $this->repository->close();
        }
        $this->archivePath = '';
    }

    /**
     * Sets the internal folder to the given path.
     * Useful for extracting only a segment of a archive file.
     *
     * @param string $path
     *
     * @return Archiver
     */
    public function folder(string $path): self
    {
        $this->currentFolder = $path;

        return $this;
    }

    /**
     * Resets the internal folder to the root of the archive file.
     *
     * @return Archiver
     */
    public function home(): self
    {
        $this->currentFolder = '';

        return $this;
    }

    /**
     * Deletes the archive file
     */
    public function delete(): void
    {
        if (null !== $this->repository) {
            $this->repository->close();
        }
        $this->filesystem->delete($this->archivePath);
        $this->archivePath = '';
    }

    /**
     * Get the type of the archive
     *
     * @return string
     */
    public function getArchiveType(): string
    {
        return get_class($this->repository);
    }

    /**
     * Get the current internal folder pointer
     *
     * @return string
     */
    public function getCurrentFolderPath(): string
    {
        return $this->currentFolder;
    }

    /**
     * Checks if a file is present in the archive
     *
     * @param string $path
     *
     * @return bool
     */
    public function contains(string $path): bool
    {
        return $this->repository->contains($path);
    }

    /**
     * @return TypeInterface
     */
    public function getArchive(): TypeInterface
    {
        return $this->repository;
    }

    /**
     * @return Filesystem
     */
    public function getFileHandler(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Gets the path to the internal folder
     *
     * @return string
     */
    public function getInternalPath(): string
    {
        return empty($this->currentFolder) ? '' : $this->currentFolder.'/';
    }

    /**
     * Return a list of filtered files
     * @param string|null $regex
     * @return array
     */
    public function filterFiles(string $regex = null): array
    {
        $filesList = [];
        $filter = static function ($file) use (&$filesList, $regex) {
            $match = preg_match($regex, $file);
            if ($match) {
                $filesList[] = $file;
            }

            if (!$match) {
                throw new RuntimeException('Regular expression match on '.$file.' failed with error. Please check if pattern is valid regular expression.');
            }
        };
        $this->repository->each($filter);

        return $filesList;
    }

    /**
     * List all files in archive
     * @return array
     */
    public function listFiles(): array
    {
        $filesList = [];
        $filter = static function ($file) use (&$filesList) {
            $filesList[] = $file;
        };
        $this->repository->each($filter);

        return $filesList;
    }

    /**
     * @return string
     */
    private function getCurrentFolderWithTrailingSlash(): string
    {
        if (empty($this->currentFolder)) {
            return '';
        }
        $lastChar = mb_substr($this->currentFolder, -1);
        if ($lastChar !== '/' || $lastChar !== '\\') {
            return $this->currentFolder.'/';
        }
        return $this->currentFolder;
    }

    /**
     * Create archive file in disk
     *
     * @param $path
     * @return bool
     * @throws FileOpenException
     */
    private function createArchivePath($path): bool
    {
        if (!$this->filesystem->exists($path)) {
            $dirname = dirname($path);
            if (!$this->filesystem->exists($dirname) && !$this->filesystem->makeDirectory($dirname, 0755, true)) {
                throw new RuntimeException('Failed to create folder');
            }

            if (!$this->filesystem->isWritable($dirname)) {
                throw new FileOpenException(sprintf('The path %s is not writeable', $path));
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $pathToDir
     */
    private function addDir(string $pathToDir): void
    {
        // First go over the files in this directory and add them to the repository.
        foreach ($this->filesystem->files($pathToDir) as $file) {
            $this->addFile($pathToDir.'/'.basename($file));
        }

        // Now let's visit the subdirectories and add them, too.
        foreach ($this->filesystem->directories($pathToDir) as $dir) {
            $old_folder = $this->currentFolder;
            $this->currentFolder = empty($this->currentFolder) ? basename($dir) : $this->currentFolder.'/'.basename($dir);
            $this->addDir($pathToDir.'/'.basename($dir));
            $this->currentFolder = $old_folder;
        }
    }

    /**
     * Add file to archive
     *
     * @param string      $path
     * @param string|null $fileName
     */
    private function addFile(string $path, string $fileName = null): void
    {
        if (!$fileName) {
            $info = pathinfo($path);
            $fileName = isset($info['extension']) ?
                $info['filename'].'.'.$info['extension'] :
                $info['filename'];
        }
        $this->repository->addFile($path, $this->getInternalPath().$fileName);
    }

    /**
     * @param string   $path
     * @param callable $matchingMethod
     */
    private function extractFilesInternal(string $path, callable $matchingMethod): void
    {
        $self = $this;
        $this->repository->each(static function ($fileName) use ($path, $matchingMethod, $self) {
            $currentPath = $self->getCurrentFolderWithTrailingSlash();
            if (!empty($currentPath) && !$this->startsWith($fileName, $currentPath)) {
                return;
            }
            $filename = str_replace($self->getInternalPath(), '', $fileName);
            if ($matchingMethod($filename)) {
                $self->extractOneFileInternal($fileName, $path);
            }
        });
    }

    /**
     * @param string $fileName
     * @param string $path
     */
    private function extractOneFileInternal(string $fileName, string $path): void
    {
        $finalPath = ltrim(str_replace($this->getInternalPath(), '', $fileName), '/.');
        $finalPath = $path.DIRECTORY_SEPARATOR.$finalPath;
        $dir = pathinfo($finalPath, PATHINFO_DIRNAME);
        if (!$this->filesystem->exists($dir) && !$this->filesystem->makeDirectory($dir, 0755, true, true)) {
            throw new RuntimeException('Failed to create folders');
        }

        $fileStream = $this->getArchive()->getStream($fileName);
        $this->getFileHandler()->put($finalPath, $fileStream);
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    private function startsWith($haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && strpos($haystack, (string)$needle) === 0) {
                return true;
            }
        }

        return false;
    }
}
