<?php

namespace App\Service\FileProcessing;

use App\Entity\FileUpload;
use App\Repository\FileUploadRepository;
use App\Service\Filesystem\FilesystemInterface;
use RuntimeException;

use function sprintf;

class FileValidator
{
    public function __construct(
        private readonly FileUploadRepository $fileUploadRepository,
        private readonly FilesystemInterface $filesystem
    ) {
    }

    public function validateAndGetFileUploadEntity(string $fileId): FileUpload
    {
        $fileUpload = $this->fileUploadRepository->find($fileId);

        if (!$fileUpload instanceof FileUpload) {
            throw new RuntimeException(sprintf('FileUpload entity not found for file ID: %s', $fileId));
        }

        if (!$this->filesystem->isAccessible($fileUpload->getFullPath())) {
            throw new RuntimeException(
                sprintf(
                    'File path "%s" from FileUpload ID: %s is not accessible.',
                    $fileUpload->getFullPath(),
                    $fileId
                )
            );
        }

        return $fileUpload;
    }
}