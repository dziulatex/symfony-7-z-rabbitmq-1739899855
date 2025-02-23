<?php

namespace App\Service\FileProcessing;

use App\Entity\Client;
use App\Entity\FileUpload;
use App\Repository\ClientRepository;
use App\Service\FileParser;
use App\Service\FileProcessing\Exception\ProgressNotInitializedException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

use Symfony\Component\Uid\Uuid;

use function sprintf;

class ClientProcessor
{
    private const DB_FLUSH_SIZE = 200;

    public function __construct(
        private readonly FileParser $fileParser,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileProgressTracker $progressTracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function processClients(
        int $rowAmount,
        string $directory,
        string $fileName,
        string $fileId,
        int $startLine,
        int $endLine,
    ): void {
        if (!$this->progressTracker->isProgressInitialized($fileId)) {
            throw new ProgressNotInitializedException(
                'Progress is not initialized, so probably there was error in some of the batches.'
            );
        }

        $clients = $this->fileParser->parseCsvFile($directory, $fileName, $startLine, $endLine);
        $parsedClientCount = 0;
        $updatedClientCount = 0;
        $newClientCount = 0;
        // Calculate how many rows represent 1%
        $onePercentRows = max(1, (int)($rowAmount / 100));
        $lastProgressUpdate = 0;

        foreach ($clients as $client) {
            $parsedClientCount++;
            $this->processClient($client, $updatedClientCount, $newClientCount, $fileId);

            // Check if we need to flush DB
            if (($parsedClientCount % self::DB_FLUSH_SIZE) === 0) {
                $this->flushAndClear();
            }

            // Update progress when we've processed another 1%
            if (($parsedClientCount - $lastProgressUpdate) >= $onePercentRows) {
                if ($this->progressTracker->isProgressInitialized($fileId)) {
                    $this->progressTracker->updateProgress($fileId, $parsedClientCount - $lastProgressUpdate);
                    $lastProgressUpdate = $parsedClientCount;
                } else {
                    throw new ProgressNotInitializedException(
                        'Progress is not initialized, so probably there was error in some of the batches.'
                    );
                }
            }
        }
        // Handle remaining records that haven't been counted in progress
        $remainingCount = $parsedClientCount - $lastProgressUpdate;
        if ($remainingCount > 0) {
            $this->progressTracker->updateProgress($fileId, $remainingCount);
        }
        $this->flushAndClear();
        $this->logProcessingResults($fileId, $parsedClientCount, $updatedClientCount, $newClientCount);
    }

    private function processClient(Client $client, int &$updatedClientCount, int &$newClientCount, string $fileId): void
    {
        if ($client->isInvalid()) {
            return;
        }
        $ref = $this->entityManager->getReference(FileUpload::class, Uuid::fromString($fileId));
        $existingClient = $this->clientRepository->find($client->getId());
        if ($existingClient instanceof Client) {
            $existingClient->setFullName($client->getFullName());
            $existingClient->setEmail($client->getEmail());
            $existingClient->setCity($client->getCity());
            $existingClient->setFileUpload($ref);
            $this->entityManager->persist($existingClient);
            $updatedClientCount++;
        } else {
            $client->setFileUpload($ref);
            $this->entityManager->persist($client);
            $newClientCount++;
        }
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function logProcessingResults(
        string $fileId,
        int $parsedClientCount,
        int $updatedClientCount,
        int $newClientCount
    ): void {
        $this->logger->info(
            sprintf(
                'File parsing and client processing completed for file ID: %s. ' .
                'Parsed %d clients. Updated %d clients. Created %d new clients.',
                $fileId,
                $parsedClientCount,
                $updatedClientCount,
                $newClientCount
            )
        );
    }
}