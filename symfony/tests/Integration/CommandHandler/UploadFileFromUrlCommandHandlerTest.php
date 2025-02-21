<?php

namespace App\Tests\Integration\CommandHandler;

use App\Command\UploadFileFromUrlCommand;
use App\CommandHandler\UploadFileFromUrlCommandHandler;
use App\Service\FileUploader;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UploadFileFromUrlCommandHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testInvokeSuccess(): void
    {
        $url = 'http://example.com/file.csv';
        $command = new UploadFileFromUrlCommand($url);
        $expectedFullPath = '/path/to/uploaded/file.csv';

        $fileUploaderMock = $this->createMock(FileUploader::class);
        $fileUploaderMock->expects(static::once())
            ->method('uploadFromUrl')
            ->with($url)
            ->willReturn(['fullPath' => $expectedFullPath]);
        self::$kernel->getContainer()->set(FileUploader::class, $fileUploaderMock);

        $handler = self::$kernel->getContainer()->get(UploadFileFromUrlCommandHandler::class);
        $result = $handler->__invoke($command);

        static::assertEquals($expectedFullPath, $result);
    }

    public function testInvokeFailure(): void
    {
        $url = 'http://example.com/file.csv';
        $command = new UploadFileFromUrlCommand($url);
        $exceptionMessage = 'Failed to upload file';

        $fileUploaderMock = $this->createMock(FileUploader::class);
        $fileUploaderMock->expects(static::once())
            ->method('uploadFromUrl')
            ->with($url)
            ->willThrowException(new Exception($exceptionMessage));

        self::$kernel->getContainer()->set(FileUploader::class, $fileUploaderMock);

        $handler = self::$kernel->getContainer()->get(UploadFileFromUrlCommandHandler::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($exceptionMessage);

        $handler->__invoke($command);
    }
}
