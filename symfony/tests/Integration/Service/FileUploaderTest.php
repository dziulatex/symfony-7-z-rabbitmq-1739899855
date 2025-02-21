<?php

namespace App\Tests\Integration\Service;

use App\Entity\FileUpload;
use App\Repository\FileUploadRepository;
use App\Service\FileUploader;
use App\Service\Filesystem\LocalFilesystem;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FileUploaderTest extends KernelTestCase
{
    private FileUploader $uploader;
    private FileUploadRepository $repository;
    private LocalFilesystem $filesystem;
    private string $uploadPath;

    protected function setUp(): void
    {
        self::bootKernel();

        // Get services from container
        $this->repository = self::getContainer()->get(FileUploadRepository::class);
        $this->filesystem = self::getContainer()->get(LocalFilesystem::class);
        $this->uploader = self::getContainer()->get(FileUploader::class);

        // Get the uploads directory
        $this->uploadPath = $this->filesystem->getUploadsDirectory();

        // Ensure directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up any files in the test uploads directory
        if (is_dir($this->uploadPath)) {
            array_map('unlink', glob($this->uploadPath . '/*.*'));
        }

        parent::tearDown();
    }

    public function testUploadDuplicateFileByMd5(): void
    {
        // Create first test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $file1 = new UploadedFile(
            $tempFile,
            'test1.txt',
            'text/plain',
            null,
            true
        );

        // Upload first file
        $result1 = $this->uploader->upload($file1);

        // Create second file with same content (same MD5)
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile2, 'test content');

        $file2 = new UploadedFile(
            $tempFile2,
            'test2.txt', // Different name but same content
            'text/plain',
            null,
            true
        );

        // Upload second file
        $result2 = $this->uploader->upload($file2);

        // Should return same file details as first upload
        self::assertEquals($result1['filename'], $result2['filename']);
        self::assertEquals($result1['path'], $result2['path']);
        self::assertEquals($result1['fullPath'], $result2['fullPath']);
    }
}