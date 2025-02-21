<?php

namespace App\Service\FileProcessing;

use App\Command\ParseFileCommand;
use App\Command\ParseFileInBatchCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

use Symfony\Component\Uid\Uuid;

use function sprintf;

class BatchProcessor
{

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly int $rowLimit
    ) {
    }

    public function processBatches(ParseFileCommand $command, int $rowCounter): void
    {
        $this->logger->info(
            sprintf(
                'Large file detected with %d rows. Creating batch processing messages with %d rows per batch.',
                $rowCounter,
                $this->rowLimit
            )
        );

        $numberOfBatches = (int)ceil(($rowCounter) / $this->rowLimit);
        $currentStartLine = 2; // Start from line 2 to skip header

        for ($i = 0; $i < $numberOfBatches; $i++) {
            $batchStartLine = $currentStartLine;
            $batchEndLine = min($currentStartLine + $this->rowLimit - 1, $rowCounter + 1);

            $this->dispatchBatchCommand($command->getFileId(), $batchStartLine, $batchEndLine, $rowCounter);

            $currentStartLine = $batchEndLine + 1;
            if ($currentStartLine > $rowCounter + 1) {
                break;
            }
        }

        $this->logger->info(
            sprintf(
                'Successfully created %d batch commands for file ID: %s',
                $numberOfBatches,
                $command->getFileId()
            )
        );
    }

    public function needsBatchProcessing(int $rowCounter): bool
    {
        return $rowCounter > $this->rowLimit;
    }

    private function dispatchBatchCommand(
        Uuid $fileId,
        int $batchStartLine,
        int $batchEndLine,
        int $rowCounter,
    ): void {
        $batchCommand = new ParseFileInBatchCommand(
            $fileId,
            $batchStartLine,
            $batchEndLine,
            $rowCounter
        );

        $this->messageBus->dispatch($batchCommand);

        $this->logger->info(
            sprintf(
                'Created batch command for file ID: %s, processing lines %d to %d',
                $fileId,
                $batchStartLine,
                $batchEndLine
            )
        );
    }
}