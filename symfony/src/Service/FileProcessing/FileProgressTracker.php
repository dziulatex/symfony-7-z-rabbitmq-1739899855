<?php

namespace App\Service\FileProcessing;

use App\Repository\FileUploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Redis;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Throwable;

use function count;
use function sprintf;

class FileProgressTracker
{
    private const PROGRESS_KEY_PREFIX = 'file_processing_progress_';
    private const TOTAL_ROWS_KEY_PREFIX = 'file_total_rows_';
    public const CHANNEL_PREFIX = 'file_status_channel_';

    public function __construct(
        private readonly Redis $redis,
        private readonly LoggerInterface $logger,
        private readonly FileUploadRepository $fileUploadRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }

    public function initializeProgress(string $fileId, int $totalRows): void
    {
        $progressKey = $this->getProgressKey($fileId);
        $totalRowsKey = $this->getTotalRowsKey($fileId);

        // Start a Redis transaction
        $transaction = $this->redis->multi();

        // Queue the commands
        $transaction->set($progressKey, 0);
        $transaction->set($totalRowsKey, $totalRows);

        // Execute the transaction and wait for results
        $results = $transaction->exec();

        // Verify both operations were successful
        if ($results === false || count($results) !== 2) {
            throw new RuntimeException('Failed to initialize progress in Redis');
        }

        // Now wait for the publish operation to complete
        $this->publishStatus($fileId, [
            'status' => FileProcessingStatus::PROCESSING->value,
            'progress' => 0,
            'total_rows' => $totalRows,
            'processed_rows' => 0
        ]);
    }

    public function updateProgress(string $fileId, int $processedRows): void
    {
        $progressKey = $this->getProgressKey($fileId);
        $totalRowsKey = $this->getTotalRowsKey($fileId);

        $luaScript = $this->getProgressUpdateScript();

        try {
            $percentage = $this->redis->eval(
                $luaScript,
                [$progressKey, $totalRowsKey, $processedRows],
                2
            );

            $this->logger->info(
                sprintf('Processing progress for file ID %s: %d%%', $fileId, $percentage)
            );

            $status = [
                'status' => FileProcessingStatus::PROCESSING->value,
                'progress' => $percentage,
                'processed_rows' => $processedRows
            ];
            if ($percentage === 100) {
                $fileUpload = $this->fileUploadRepository->find($fileId);
                if ($fileUpload) {
                    $fileUpload->markAsProcessed();
                    $this->entityManager->flush();

                    $status['status'] = FileProcessingStatus::PROCESSED->value;
                    $this->publishStatus($fileId, $status);
                    $this->cleanupProgress($fileId);
                }
            } else {
                $this->publishStatus($fileId, $status);
            }
        } catch (Throwable $e) {
            $this->logger->error('Error updating progress: ' . $e->getMessage());

            $fileUpload = $this->fileUploadRepository->find($fileId);
            if ($fileUpload) {
                $fileUpload->markAsError('Error processing file: ' . $e->getMessage());
                $this->entityManager->flush();

                $this->publishStatus($fileId, [
                    'status' => FileProcessingStatus::ERROR->value,
                    'errorMessage' => $e->getMessage()
                ]);

                $this->cleanupProgress($fileId);
            }
        }
    }

    public function markError(string $fileId, string $errorMessage): void
    {
        $fileUpload = $this->fileUploadRepository->find(Uuid::fromString($fileId));
        if ($fileUpload) {
            $fileUpload->markAsError($errorMessage);
            $this->entityManager->flush();
            $this->publishStatus($fileId, [
                'status' => FileProcessingStatus::ERROR->value,
                'errorMessage' => $errorMessage
            ]);

            $this->cleanupProgress($fileId);
        }
    }

    public function getStatus(string $fileId): array
    {
        $progressKey = $this->getProgressKey($fileId);
        $totalRowsKey = $this->getTotalRowsKey($fileId);

        // Check database first for file status
        $fileUpload = $this->fileUploadRepository->find($fileId);

        if (!$fileUpload) {
            throw new InvalidArgumentException('File not found');
        }

        // If file is in error state, return error status
        if (!$this->redis->exists($progressKey)) {
            if ($fileUpload->getErrorMessage()) {
                return [
                    'status' => FileProcessingStatus::ERROR->value,
                    'errorMessage' => $fileUpload->getErrorMessage(),
                    'progress' => 0
                ];
            }

            // If file is processed, return completed status
            if ($fileUpload->isProcessed()) {
                return [
                    'status' => FileProcessingStatus::PROCESSED->value,
                    'progress' => 100
                ];
            }
            if ($fileUpload->isNew()) {
                return [
                    'status' => FileProcessingStatus::NEW->value,
                    'progress' => 0
                ];
            }
            if ($fileUpload->isWaiting()) {
                return [
                    'status' => FileProcessingStatus::WAITING->value,
                    'progress' => 0
                ];
            }
        }

        // Get current progress from Redis
        $current = (int)$this->redis->get($progressKey);
        $total = (int)$this->redis->get($totalRowsKey);

        // Check if total is 0 or if keys don't exist
        if ($total === 0 || !$this->redis->exists($totalRowsKey)) {
            return [
                'status' => FileProcessingStatus::PROCESSING->value,
                'progress' => 0,
                'processed_rows' => $current,
                'total_rows' => $total
            ];
        }

        $percentage = min(100, floor(($current / $total) * 100));

        return [
            'status' => FileProcessingStatus::PROCESSING->value,
            'progress' => $percentage,
            'processed_rows' => $current,
            'total_rows' => $total
        ];
    }

    private function publishStatus(string $fileId, array $data): void
    {
        $channel = self::CHANNEL_PREFIX . $fileId;
        $message = json_encode($data);

        if ($message === false) {
            $this->logger->error('Failed to encode status message for file ID: ' . $fileId);
            return;
        }

        $this->redis->publish($channel, $message);
    }

    public function cleanupProgress(string $fileId): void
    {
        $this->redis->del([
            $this->getProgressKey($fileId),
            $this->getTotalRowsKey($fileId)
        ]);
    }

    public function getProgressKey(string $fileId): string
    {
        return self::PROGRESS_KEY_PREFIX . $fileId;
    }

    public function getTotalRowsKey(string $fileId): string
    {
        return self::TOTAL_ROWS_KEY_PREFIX . $fileId;
    }

    public function isProgressInitialized(string $fileId): bool
    {
        $progressKey = $this->getProgressKey($fileId);
        $totalRowsKey = $this->getTotalRowsKey($fileId);

        return $this->redis->exists($progressKey) && $this->redis->exists($totalRowsKey);
    }

    public function getProgress(string $fileId): int
    {
        $progressKey = $this->getProgressKey($fileId);
        $totalRowsKey = $this->getTotalRowsKey($fileId);

        // Get current progress from Redis
        $current = (int)$this->redis->get($progressKey);
        $total = (int)$this->redis->get($totalRowsKey);

        // If total is 0 or key doesn't exist, return 0
        if ($total === 0 || !$this->redis->exists($totalRowsKey)) {
            return 0;
        }

        return (int)min(100, floor(($current / $total) * 100));
    }

    private function getProgressUpdateScript(): string
    {
        return <<<LUA
            local progress_key = KEYS[1]
            local total_key = KEYS[2]
            local increment = ARGV[1]
            
            local current = redis.call('INCRBY', progress_key, increment)
            local total = redis.call('GET', total_key)
            
            local percentage = math.floor((current / tonumber(total)) * 100)
            percentage = math.min(percentage, 100)
            
            return percentage
        LUA;
    }
}