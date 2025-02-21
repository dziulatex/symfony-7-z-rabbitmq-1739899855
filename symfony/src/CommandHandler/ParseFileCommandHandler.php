<?php

namespace App\CommandHandler;

use App\Command\ParseFileCommand;
use App\Entity\FileUpload;
use App\Service\FileParser;
use App\Service\FileProcessing\BatchProcessor;
use App\Service\FileProcessing\ClientProcessor;
use App\Service\FileProcessing\Exception\ProgressNotInitializedException;
use App\Service\FileProcessing\FileProgressTracker;
use App\Service\FileProcessing\FileValidator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

#[AsMessageHandler]
class ParseFileCommandHandler
{
    private ?LockInterface $lock = null;
    private ?FileUpload $fileUpload = null;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly FileParser $fileParser,
        private readonly FileValidator $fileValidator,
        private readonly BatchProcessor $batchProcessor,
        private readonly ClientProcessor $clientProcessor,
        private readonly FileProgressTracker $progressTracker,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(ParseFileCommand $command): void
    {
        $fileId = $command->getFileId();
        $this->lock = $this->lockFactory->createLock($this->createLockKey($fileId));

        try {
            if (!$this->lock->acquire()) {
                throw new RuntimeException(
                    sprintf('Could not acquire lock for file ID: %s. Another process might be parsing it.', $fileId)
                );
            }

            $this->logger->info(sprintf('Lock acquired for file ID: %s', $fileId));

            $this->fileUpload = $this->fileValidator->validateAndGetFileUploadEntity($fileId);
            $filePath = $this->fileUpload->getFullPath();
            $rowCounter = $this->fileParser->getLineCount($filePath);
            $this->fileUpload->markAsProcessing();
            $this->progressTracker->initializeProgress($fileId, $rowCounter - 1); // Subtract header row
            if ($this->batchProcessor->needsBatchProcessing($rowCounter)) {
                $this->entityManager->flush();
                $this->batchProcessor->processBatches($command, $rowCounter);
            } else {
                $this->clientProcessor->processClients($rowCounter, $filePath, $fileId, 0, 0);
                $this->progressTracker->cleanupProgress($fileId);
            }
            $this->entityManager->flush();
        } catch (Exception $e) {
            if (!$e instanceof ProgressNotInitializedException) {
                $this->progressTracker->markError($fileId, $e->getMessage());
                $this->progressTracker->cleanupProgress($fileId);
                $this->logger->error(
                    sprintf('Error processing file ID: %s. Error: %s', $fileId, $e->getMessage())
                );
            }
            $this->releaseLock();
            throw $e;
        }
        $this->releaseLock();
    }

    private function createLockKey(string $fileId): string
    {
        return sprintf('parse_file_%s', $fileId);
    }

    private function releaseLock(): void
    {
        if ($this->lock && $this->lock->isAcquired()) {
            $this->lock->release();
            $this->logger->info('Lock released.');
        }
    }
}