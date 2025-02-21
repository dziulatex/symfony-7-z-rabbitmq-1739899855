<?php

namespace App\Tests\Integration\CommandHandler;

use App\Command\ParseFileCommand;
use App\CommandHandler\ParseFileCommandHandler;
use App\CommandHandler\ParseFileInBatchCommandHandler;
use App\Entity\Client;
use App\Entity\FileUpload;
use App\Service\FileProcessing\FileProcessingStatus;
use App\TestFixtures\FileUploadFixture;
use App\Tests\Trait\TestUtilsTrait;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

use function sprintf;

class ParseFileCommandHandlerTest extends KernelTestCase
{
    use TestUtilsTrait;

    protected EntityManagerInterface $entityManager;
    protected ReferenceRepository $referenceRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->referenceRepository = $this->loadFixtures([
            FileUploadFixture::class
        ]);
        $this->rowLimit = self::getContainer()->getParameterBag()->get('batchProcessorRowLimit');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    protected function getReference(string $name, string $class): FileUpload
    {
        return $this->referenceRepository->getReference($name, $class);
    }

    public function testInvokeWithLockAlreadyAcquired(): void
    {
        // Get small file from fixtures
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_SMALL_FILE, FileUpload::class);
        $command = new ParseFileCommand($fileUpload->getId());
        $lockFactory = self::getContainer()->get(LockFactory::class);
        $handler = self::getContainer()->get(ParseFileCommandHandler::class);
        // Acquire lock before running handler
        $lock = $lockFactory->createLock('parse_file_' . $fileUpload->getId());
        $lock->acquire();

        try {
            // Expect exception about lock not being available
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                sprintf(
                    'Could not acquire lock for file ID: %s. Another process might be parsing it.',
                    $fileUpload->getId()
                )
            );

            $handler->__invoke($command);
        } finally {
            // Always release the lock after test
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }

        // File state should not have changed since handler failed to acquire lock
        static::assertEquals(FileProcessingStatus::NEW, $fileUpload->getState());
    }

    public function testInvokeSmallFile(): void
    {
        // Get small file from fixtures
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_SMALL_FILE, FileUpload::class);
        $this->entityManager->persist($fileUpload);
        $command = new ParseFileCommand($fileUpload->getId());

        // Get the handler from the container
        /** @var ParseFileCommandHandler $handler */
        $handler = self::getContainer()->get(ParseFileCommandHandler::class);

        // Execute the handler
        $handler->__invoke($command);

        // Refresh entity from database
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_SMALL_FILE, FileUpload::class);

        // Assert the file was processed
        static::assertEquals(FileProcessingStatus::PROCESSED, $fileUpload->getState());

        // Assert clients were created in database
        $clientRepository = $this->entityManager->getRepository(Client::class);
        $clients = $clientRepository->findAll();

        static::assertCount($this->rowLimit - 1, $clients);
        static::assertEquals('Test User 1', $clients[0]->getFullName());
        static::assertEquals('user1@example.com', $clients[0]->getEmail());
    }

    public function testInvokeWithValidationError(): void
    {
        // Get small file from fixtures and delete the actual file
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_SMALL_FILE, FileUpload::class);
        unlink($fileUpload->getFullPath());

        $command = new ParseFileCommand($fileUpload->getId());
        $handler = self::getContainer()->get(ParseFileCommandHandler::class);

        $this->expectException(RuntimeException::class);
        $handler->__invoke($command);

        // Refresh entity from database
        $this->entityManager->refresh($fileUpload);

        // Assert the file is marked as error
        static::assertEquals(FileProcessingStatus::ERROR, $fileUpload->getState());
    }

    public function testInvokeLargeFile(): void
    {
        // Get large file from fixtures
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_LARGE_FILE, FileUpload::class);

        // File should start in NEW state
        static::assertEquals(FileProcessingStatus::NEW, $fileUpload->getState());

        $command = new ParseFileCommand($fileUpload->getId());

        /** @var ParseFileCommandHandler $handler */
        $handler = self::getContainer()->get(ParseFileCommandHandler::class);
        $handler->__invoke($command);

        // Refresh entity
        $fileUpload = $this->getReference(FileUploadFixture::REFERENCE_LARGE_FILE, FileUpload::class);

        // For large files, the handler should set appropriate processing state
        // What state should it actually be? PROCESSING? IN_PROGRESS?
        // This depends on your actual command handler implementation
        static::assertEquals(FileProcessingStatus::PROCESSING, $fileUpload->getState());
    }
}