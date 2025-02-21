<?php

namespace App\CommandHandler;

use App\Command\ParseFileInBatchCommand;
use App\Entity\FileUpload;
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
class ParseFileInBatchCommandHandler
{
    private ?LockInterface $lock = null;
    private ?FileUpload $fileUpload = null;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly FileValidator $fileValidator,
        private readonly ClientProcessor $clientProcessor,
        private readonly FileProgressTracker $progressTracker,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(ParseFileInBatchCommand $command): void
    {
        $fileId = $command->getFileId();
        $lockKey = $this->createLockKey($fileId, $command->getStartLine(), $command->getEndLine());
        $this->lock = $this->lockFactory->createLock($lockKey);
        try {
            if (!$this->lock->acquire()) {
                throw new RuntimeException(
                    sprintf('Could not acquire lock for file ID: %s. Another process might be parsing it.', $fileId)
                );
            }

            $this->logger->info(sprintf('Lock acquired for file ID: %s', $fileId));

            $this->fileUpload = $this->fileValidator->validateAndGetFileUploadEntity($fileId);
            $filePath = $this->fileUpload->getFullPath();
            $this->clientProcessor->processClients(
                $command->getTotalRows(),
                $filePath,
                $fileId,
                $command->getStartLine(),
                $command->getEndLine()
            );

            $this->entityManager->flush();
        } catch (Exception $e) {
            if (!$e instanceof ProgressNotInitializedException) {
                $this->progressTracker->markError($fileId, $e->getMessage());
                $this->logger->error(
                    sprintf('Error processing file ID: %s. Error: %s', $fileId, $e->getMessage())
                );
            }
            throw $e;
        } finally {
            $this->releaseLock();
        }
    }

    public function createLockKey(string $fileId, int $startLine, int $endLine): string
    {
        return sprintf('parse_file_%s_lines_%d_to_%d', $fileId, $startLine, $endLine);
    }

    private function releaseLock(): void
    {
        if ($this->lock && $this->lock->isAcquired()) {
            $this->lock->release();
            $this->logger->info('Lock released.');
        }
    }

    public function __destruct()
    {
        $this->releaseLock();
    }
}