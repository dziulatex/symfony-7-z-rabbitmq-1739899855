<?php

namespace App\Service\Filesystem;

use App\Service\Filesystem\Exception\FileOperationException;

interface FilesystemInterface
{
    public const ACCESS_READ = 'rb';
    public const ACCESS_WRITE = 'w';
    public const ACCESS_EXECUTE = 'x';

    /**
     * Move a file from source to target path
     */
    public function moveFile(string $sourcePath, string $targetPath): void;

    /**
     * Upload a file to the filesystem
     *
     * @param string $sourcePath Source file path
     * @param string $targetPath Target file path
     * @param bool $generateUniquePath Whether to generate a unique path if target exists
     * @return string The final path where the file was uploaded
     */
    public function uploadFile(string $sourcePath, string $targetPath, bool $generateUniquePath = false): string;

    /**
     * Check if a file is accessible with given mode(s)
     *
     * @param string $path File path
     * @param string|array $mode Access mode(s)
     * @return bool True if file is accessible
     */
    public function isAccessible(string $path, string|array $mode = self::ACCESS_READ): bool;

    /**
     * Get a file stream
     *
     * @param string $path File path
     * @param string $mode File open mode
     * @return resource File stream
     * @throws FileOperationException If the operation fails
     */
    public function getStream(string $path, string $mode = self::ACCESS_READ);

    /**
     * Delete a file
     *
     * @param string $path File path
     * @throws FileOperationException If the operation fails
     */
    public function deleteFile(string $path): void;

    /**
     * Ensure directory exists and is writable
     */
    public function ensureDirectoryExists(string $directory): void;

    /**
     * Get the base uploads directory
     */
    public function getUploadsDirectory(): string;
}