<?php

namespace App\Service\Filesystem;

use App\Service\Filesystem\Exception\FileOperationException;
use RuntimeException;

use function dirname;
use function is_array;
use function sprintf;

class LocalFilesystem implements FilesystemInterface
{
    public function __construct(
        public readonly string $uploadsDirectory
    ) {
    }

    public function moveFile(string $sourcePath, string $targetPath): void
    {
        if (!$this->isAccessible($sourcePath)) {
            throw new FileOperationException(sprintf('Source file "%s" is not readable', $sourcePath));
        }

        // Ensure target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new FileOperationException(sprintf('Directory "%s" could not be created', $targetDir));
        }

        // Perform the move operation
        if (!@rename($sourcePath, $targetPath)) {
            // If rename fails (e.g., across different filesystems), try copy and delete
            if (!@copy($sourcePath, $targetPath)) {
                throw new FileOperationException(
                    sprintf('Could not move file from "%s" to "%s"', $sourcePath, $targetPath)
                );
            }

            // Delete the original file after successful copy
            if (!@unlink($sourcePath)) {
                throw new FileOperationException(
                    sprintf('Could not delete original file "%s" after copy', $sourcePath)
                );
            }
        }

        // Ensure the file was actually created
        if (!$this->isAccessible($targetPath)) {
            throw new FileOperationException(sprintf('Failed to move file to "%s"', $targetPath));
        }
    }

    public function isAccessible(string $path, string|array $mode = self::ACCESS_READ): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $modes = is_array($mode) ? $mode : [$mode];

        foreach ($modes as $m) {
            switch ($m) {
                case self::ACCESS_READ:
                    if (!is_readable($path)) {
                        return false;
                    }
                    break;

                case self::ACCESS_WRITE:
                    if (!is_writable($path)) {
                        return false;
                    }
                    break;

                case self::ACCESS_EXECUTE:
                    if (!is_executable($path)) {
                        return false;
                    }
                    break;

                default:
                    throw new RuntimeException(sprintf('Invalid access mode "%s"', $m));
            }
        }

        return true;
    }

    public function getStream(string $path, string $mode = self::ACCESS_READ)
    {
        // For write/append modes, check if directory exists
        if (str_contains($mode, 'w') || str_contains($mode, 'a')) {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new FileOperationException(sprintf('Directory "%s" could not be created', $dir));
            }
        } else {
            // For read modes, verify file exists and is readable
            if (!$this->isAccessible($path)) {
                throw new FileOperationException(sprintf('File "%s" is not accessible for reading', $path));
            }
        }

        $stream = @fopen($path, $mode);
        if ($stream === false) {
            throw new FileOperationException(sprintf('Could not open file "%s" in mode "%s"', $path, $mode));
        }

        return $stream;
    }

    public function uploadFile(string $sourcePath, string $targetPath, bool $generateUniquePath = false): string
    {
        // Ensure target directory exists
        $this->ensureDirectoryExists(dirname($targetPath));

        $finalPath = $targetPath;
        if ($generateUniquePath && file_exists($targetPath)) {
            $pathInfo = pathinfo($targetPath);
            $i = 1;
            do {
                $finalPath = sprintf(
                    '%s/%s_%d.%s',
                    $pathInfo['dirname'],
                    $pathInfo['filename'],
                    $i++,
                    $pathInfo['extension']
                );
            } while (file_exists($finalPath));
        }

        $this->moveFile($sourcePath, $finalPath);

        return $finalPath;
    }

    public function deleteFile(string $path): void
    {
        if (!$this->isAccessible($path, [self::ACCESS_READ, self::ACCESS_WRITE])) {
            throw new FileOperationException(sprintf('File "%s" is not accessible for deletion', $path));
        }

        if (!@unlink($path)) {
            throw new FileOperationException(sprintf('Could not delete file "%s"', $path));
        }

        if (file_exists($path)) {
            throw new FileOperationException(sprintf('Failed to delete file "%s"', $path));
        }
    }

    /**
     * Ensure directory exists and is writable
     */
    public function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new FileOperationException(sprintf('Directory "%s" could not be created', $directory));
            }
        }

        if (!is_writable($directory)) {
            throw new FileOperationException(sprintf('Directory "%s" is not writable', $directory));
        }
    }

    public function getUploadsDirectory(): string
    {
        return $this->uploadsDirectory;
    }
}