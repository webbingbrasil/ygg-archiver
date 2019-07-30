<?php

namespace Ygg\Archiver\Types;

interface TypeInterface
{

    /**
     * TypeInterface constructor.
     * @param string $path
     * @param bool   $new
     */
    public function __construct(string $path, bool $new = false);

    /**
     * Add a file to the opened Archive
     *
     * @param string $pathToFile
     * @param string $pathInArchive
     */
    public function addFile(string $pathToFile, string $pathInArchive): void;

    /**
     * Add a file to the opened Archive using its contents
     *
     * @param string $name
     * @param string $content
     */
    public function addFromString(string $name, string $content): void;

    /**
     * Add an empty directory
     *
     * @param string $dirName
     */
    public function addEmptyDir(string $dirName): void;

    /**
     * Remove a path permanently from the Archive
     *
     * @param string $path
     */
    public function delete(string $path): void;

    /**
     * Get the content of a file
     *
     * @param string $path
     *
     * @return string
     */
    public function getFileContent(string $path): string;

    /**
     * Get the stream of a file
     *
     * @param string $path
     *
     * @return mixed
     */
    public function getStream(string $path);

    /**
     * Will loop over every item in the archive and will execute the callback on them
     * Will provide the filename for every item
     *
     * @param callable $callback
     */
    public function each(callable $callback): void;

    /**
     * Checks whether the path is in the archive
     *
     * @param string $path
     *
     * @return bool
     */
    public function contains(string $path): bool;

    /**
     * Sets the password to be used for decompressing
     *
     * @param string $password
     *
     * @return bool
     */
    public function usePassword(string $password): bool;

    /**
     * Returns the status of the archive as a string
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Closes the archive and saves it
     */
    public function close(): void;
}
