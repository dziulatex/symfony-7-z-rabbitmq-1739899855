<?php

namespace App\Tests\Integration\CommandHandler;

use App\Command\ParseFileInBatchCommand;
use App\CommandHandler\ParseFileInBatchCommandHandler;
use App\Entity\Client;
use App\Entity\FileUpload;
use App\Service\FileProcessing\Exception\ProgressNotInitializedException;
use App\TestFixtures\FileUploadFixture;
use App\Tests\Trait\TestUtilsTrait;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;
use App\Service\FileProcessing\FileProgressTracker;

use function sprintf;

class ParseFileInBatchCommandHandlerTest extends KernelTestCase
{
    use TestUtilsTrait;

    private EntityManagerInterface $entityManager;
    private ReferenceRepository $referenceRepository;
    private int $batchProcessorRowLimit;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->batchProcessorRowLimit = self::getContainer()->getParameter('batchProcessorRowLimit');

        // Load fixtures
        $this->referenceRepository = $this->loadFixtures([
            FileUploadFixture::class,
        ]);

        // Clear Redis
        self::getContainer()->get('Redis')->flushDB(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        self::getContainer()->get('Redis')->flushDB(true);
    }

    public function testSuccessfulBatchProcessing(): void
    {
        // Get large file from fixtures
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );

        // Create command for processing first batch (lines 2-101, assuming header is line 1)
        $command = new ParseFileInBatchCommand(
            $fileUpload->getId(),
            2, // Start after header
            101,
            $this->batchProcessorRowLimit + 1 // Total rows from fixture
        );

        /** @var ParseFileInBatchCommandHandler $handler */
        $handler = self::getContainer()->get(ParseFileInBatchCommandHandler::class);
        /** @var FileProgressTracker $tracker */
        $tracker = self::getContainer()->get(FileProgressTracker::class);
        $tracker->initializeProgress($fileUpload->getId(), $this->batchProcessorRowLimit + 1);
        // Process the batch
        $handler->__invoke($command);

        // Check that clients were created
        $clientRepository = $this->entityManager->getRepository(Client::class);
        $clients = $clientRepository->findAll();
        static::assertCount($this->batchProcessorRowLimit + 1, $clients);

        // Verify first client data
        $firstClient = $clients[0];
        static::assertEquals('Test User 1', $firstClient->getFullName());
        static::assertEquals('user1@example.com', $firstClient->getEmail());
        static::assertEquals('City 1', $firstClient->getCity());
    }

    public function testLockPreventsSimultaneousProcessing(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );

        $command = new ParseFileInBatchCommand(
            $fileUpload->getId(),
            2,
            101,
            $this->batchProcessorRowLimit + 1
        );

        /** @var LockFactory $lockFactory */
        $lockFactory = self::getContainer()->get(LockFactory::class);

        // Manually acquire the lock
        $lock = $lockFactory->createLock(
            sprintf('parse_file_%s_lines_%d_to_%d', $fileUpload->getId(), 2, 101)
        );
        $lock->acquire();

        /** @var ParseFileInBatchCommandHandler $handler */
        $handler = self::getContainer()->get(ParseFileInBatchCommandHandler::class);

        // Expect exception when trying to process while lock is held
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf('Could not acquire lock for file ID: %s', $fileUpload->getId())
        );

        $handler->__invoke($command);
    }

    public function testProgressNotInitialized(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );

        $command = new ParseFileInBatchCommand(
            $fileUpload->getId(),
            2,
            101,
            $this->batchProcessorRowLimit + 1
        );

        // Clear any existing progress
        /** @var FileProgressTracker $progressTracker */
        $progressTracker = self::getContainer()->get(FileProgressTracker::class);
        $progressTracker->cleanupProgress($fileUpload->getId());

        /** @var ParseFileInBatchCommandHandler $handler */
        $handler = self::getContainer()->get(ParseFileInBatchCommandHandler::class);

        $this->expectException(ProgressNotInitializedException::class);
        $handler->__invoke($command);
    }
}