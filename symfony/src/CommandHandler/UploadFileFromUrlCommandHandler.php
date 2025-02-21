<?php

namespace App\CommandHandler;

use App\Command\UploadFileFromUrlCommand;
use App\Service\FileUploader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Exception;

#[AsMessageHandler]
class UploadFileFromUrlCommandHandler
{
    private FileUploader $fileUploader;
    private LoggerInterface $logger;

    public function __construct(
        FileUploader $fileUploader,
        LoggerInterface $logger
    ) {
        $this->fileUploader = $fileUploader;
        $this->logger = $logger;
    }

    public function __invoke(UploadFileFromUrlCommand $command): string
    {
        try {
            $uploadResult = $this->fileUploader->uploadFromUrl($command->getUrl());
            return $uploadResult['fullPath'];
        } catch (Exception $e) {
            // Log the exception
            $this->logger->error('Error uploading file from URL: {error}', [
                'error' => $e->getMessage(),
                'url' => $command->getUrl(),
            ]);
            throw $e;
        }
    }
}