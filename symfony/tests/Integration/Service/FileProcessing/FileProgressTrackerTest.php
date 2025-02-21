<?php

namespace App\Tests\Integration\Service\FileProcessing;

use App\Entity\FileUpload;
use App\Service\FileProcessing\FileProcessingStatus;
use App\Service\FileProcessing\FileProgressTracker;
use App\TestFixtures\FileUploadFixture;
use App\Tests\Trait\TestUtilsTrait;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class FileProgressTrackerTest extends KernelTestCase
{
    use TestUtilsTrait;

    private FileProgressTracker $tracker;
    private ReferenceRepository $referenceRepository;
    private DatabaseToolCollection $databaseToolCollection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tracker = self::getContainer()->get(FileProgressTracker::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->referenceRepository = $this->loadFixtures([FileUploadFixture::class]);
        $this->batchProcessorRowLimit = self::getContainer()->getParameter('batchProcessorRowLimit');
        // Clear Redis before each test
        self::getContainer()->get('Redis')->flushDB(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up Redis after each test
        self::getContainer()->get('Redis')->flushDB(true);
    }

    public function testInitializeProgress(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );

        $this->tracker->initializeProgress($fileUpload->getId(), $this->batchProcessorRowLimit + 1);

        self::assertTrue($this->tracker->isProgressInitialized($fileUpload->getId()));

        $status = $this->tracker->getStatus($fileUpload->getId());
        self::assertEquals(FileProcessingStatus::PROCESSING->value, $status['status']);
        self::assertEquals(0, $status['progress']);
        self::assertEquals($this->batchProcessorRowLimit + 1, $status['total_rows']);
        self::assertEquals(0, $status['processed_rows']);
    }

    public function testUpdateProgress(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );
        $totalRows = 100;

        $this->tracker->initializeProgress($fileUpload->getId(), $totalRows);
        $this->tracker->updateProgress($fileUpload->getId(), 50);

        $status = $this->tracker->getStatus($fileUpload->getId());
        self::assertEquals(FileProcessingStatus::PROCESSING->value, $status['status']);
        self::assertEquals(50, $status['progress']);
        self::assertEquals(50, $status['processed_rows']);
    }

    public function testCompleteProgress(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );
        $fileUpload->markAsProcessing();
        $this->entityManager->flush();
        $this->tracker->initializeProgress($fileUpload->getId(), $this->batchProcessorRowLimit + 1);
        $this->tracker->updateProgress($fileUpload->getId(), 100);

        $status = $this->tracker->getStatus($fileUpload->getId());
        self::assertEquals(FileProcessingStatus::PROCESSED->value, $status['status']);
        self::assertEquals(100, $status['progress']);

        // Check that Redis keys were cleaned up
        self::assertFalse($this->tracker->isProgressInitialized($fileUpload->getId()));
    }

    public function testMarkError(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_LARGE_FILE,
            FileUpload::class
        );
        $errorMessage = 'Test error message';

        $this->tracker->initializeProgress($fileUpload->getId(), 100);
        $this->tracker->markError($fileUpload->getId(), $errorMessage);

        $status = $this->tracker->getStatus($fileUpload->getId());
        self::assertEquals(FileProcessingStatus::ERROR->value, $status['status']);
        self::assertEquals($errorMessage, $status['errorMessage']);

        // Check that Redis keys were cleaned up
        self::assertFalse($this->tracker->isProgressInitialized($fileUpload->getId()));
    }

    public function testGetStatusForNonExistingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->tracker->getStatus(Uuid::v4()->toRfc4122());
    }

    public function testGetStatusForNewFile(): void
    {
        /** @var FileUpload $fileUpload */
        $fileUpload = $this->referenceRepository->getReference(
            FileUploadFixture::REFERENCE_SMALL_FILE,
            FileUpload::class
        );

        $status = $this->tracker->getStatus($fileUpload->getId());
        self::assertEquals(FileProcessingStatus::NEW->value, $status['status']);
        self::assertEquals(0, $status['progress']);
    }

    public function testCleanupProgress(): void
    {
        $fileId = Uuid::v4()->toRfc4122();
        $totalRows = 100;

        $this->tracker->initializeProgress($fileId, $totalRows);
        self::assertTrue($this->tracker->isProgressInitialized($fileId));

        $this->tracker->cleanupProgress($fileId);
        self::assertFalse($this->tracker->isProgressInitialized($fileId));
    }

    public function testGetProgress(): void
    {
        $fileId = Uuid::v4()->toRfc4122();
        $totalRows = 200;

        $this->tracker->initializeProgress($fileId, $totalRows);
        $this->tracker->updateProgress($fileId, 50);

        $progress = $this->tracker->getProgress($fileId);
        self::assertEquals(25, $progress); // 50/200 * 100 = 25%
    }
}