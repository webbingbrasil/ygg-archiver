<?php

namespace Ygg\Archiver\Types;

use Exception;
use Ygg\Archiver\Exception\FileOpenException;
use Ygg\Archiver\Exception\UnsuportedArchiveException;
use ZipArchive;

class ZipType implements TypeInterface
{
    private $archive;

    /**
     * ZipType constructor.
     * @param string $path
     * @param bool $create
     * @throws Exception
     */
    public function __construct(string $path, bool $create = false)
    {
        if (!class_exists('ZipArchive')) {
            throw new UnsuportedArchiveException('zip');
        }

        $this->archive = new ZipArchive();
        $res = $this->archive->open($path, ($create ? ZipArchive::CREATE : null));

        if ($res !== true) {
            throw new FileOpenException($this->getErrorMessage($res));
        }
    }

    /**
     * Add a file to the opened Archive
     *
     * @param string $pathToFile
     * @param string $pathInArchive
     */
    public function addFile(string $pathToFile, string $pathInArchive): void
    {
        $this->archive->addFile($pathToFile, $pathInArchive);
    }

    /**
     * Add an empty directory
     *
     * @param string $dirName
     */
    public function addEmptyDir(string $dirName): void
    {
        $this->archive->addEmptyDir($dirName);
    }

    /**
     * Add a file to the opened Archive using its contents
     *
     * @param string $name
     * @param string $content
     */
    public function addFromString(string $name, string $content): void
    {
        $this->archive->addFromString($name, $content);
    }

    /**
     * Remove a path permanently from the Archive
     *
     * @param string $path
     */
    public function delete(string $path): void
    {
        $this->archive->deleteName($path);
    }

    /**
     * Get the content of a file
     *
     * @param string $path
     *
     * @return string
     */
    public function getFileContent(string $path): string
    {
        return $this->archive->getFromName($path);
    }

    /**
     * Get the stream of a file
     *
     * @param string $path
     *
     * @return mixed
     */
    public function getStream(string $path)
    {
        return $this->archive->getStream($path);
    }

    /**
     * Will loop over every item in the archive and will execute the callback on them
     * Will provide the filename for every item
     *
     * @param callable $callback
     */
    public function each(callable $callback): void
    {
        for ($i = 0; $i < $this->archive->numFiles; ++$i) {
            //skip if folder
            $stats = $this->archive->statIndex($i);
            if ($stats['size'] === 0 && $stats['crc'] === 0) {
                continue;
            }
            $callback($this->archive->getNameIndex($i), $this->archive->statIndex($i));
        }
    }

    /**
     * Checks whether the file is in the archive
     *
     * @param string $path
     *
     * @return bool
     */
    public function contains(string $path): bool
    {
        return $this->archive->locateName($path) !== false;
    }

    /**
     * Sets the password to be used for decompressing
     * function named usePassword for clarity
     *
     * @param string $password
     *
     * @return bool
     */
    public function usePassword(string $password): bool
    {
        return $this->archive->setPassword($password);
    }

    /**
     * Returns the status of the archive as a string
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->archive->getStatusString();
    }

    /**
     * Closes the archive and saves it
     */
    public function close(): void
    {
        @$this->archive->close();
    }

    /**
     * Return a formatted error message
     * @param mixed $resultCode
     * @return string|null
     */
    private function getErrorMessage($resultCode): ?string
    {
        switch ($resultCode) {
            case ZipArchive::ER_EXISTS:
                return 'ZipArchive::ER_EXISTS - File already exists.';
            case ZipArchive::ER_INCONS:
                return 'ZipArchive::ER_INCONS - Zip archive inconsistent.';
            case ZipArchive::ER_MEMORY:
                return 'ZipArchive::ER_MEMORY - Malloc failure.';
            case ZipArchive::ER_NOENT:
                return 'ZipArchive::ER_NOENT - No such file.';
            case ZipArchive::ER_NOZIP:
                return 'ZipArchive::ER_NOZIP - Not a zip archive.';
            case ZipArchive::ER_OPEN:
                return 'ZipArchive::ER_OPEN - Can\'t open file.';
            case ZipArchive::ER_READ:
                return 'ZipArchive::ER_READ - Read error.';
            case ZipArchive::ER_SEEK:
                return 'ZipArchive::ER_SEEK - Seek error.';
            default:
                return 'An unknown error [$resultCode] has occurred.';
        }
    }
}
