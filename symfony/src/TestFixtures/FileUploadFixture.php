<?php

namespace App\TestFixtures;

use App\Entity\FileUpload;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use RuntimeException;

use function dirname;
use function sprintf;

class FileUploadFixture extends Fixture implements FixtureGroupInterface
{
    public const REFERENCE_SMALL_FILE = 'file_upload_small';
    public const REFERENCE_LARGE_FILE = 'file_upload_large';
    public const REFERENCE_HUGE_FILE = 'file_upload_huge';
    public const TEST_BASE_DIR = '/tmp/test_uploads';

    public function __construct(
        private readonly int $batchProcessorRowLimit
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Ensure test directory exists
        if (!file_exists(self::TEST_BASE_DIR)) {
            if (!mkdir(self::TEST_BASE_DIR, 0777, true) && !is_dir(self::TEST_BASE_DIR)) {
                throw new RuntimeException(sprintf('Directory "%s" could not be created', self::TEST_BASE_DIR));
            }
        }

        // Create small file upload
        $smallFileUpload = $this->createFileUpload('csv', self::TEST_BASE_DIR);
        $this->createCsvFile($smallFileUpload->getFullPath(), $this->batchProcessorRowLimit - 1);
        $smallFileUpload->generateMd5();
        $manager->persist($smallFileUpload);

        // Create large file upload - use batch processor limit plus some extra rows to ensure it triggers batch processing
        $largeFileUpload = $this->createFileUpload('csv', self::TEST_BASE_DIR);
        $this->createCsvFile($largeFileUpload->getFullPath(), $this->batchProcessorRowLimit + 1);
        $largeFileUpload->generateMd5();
        $manager->persist($largeFileUpload);

        $hugeFileForAsyncTests = $this->createFileUpload('csv', self::TEST_BASE_DIR);
        $this->createCsvFile($hugeFileForAsyncTests->getFullPath(), $this->batchProcessorRowLimit * 100);
        $hugeFileForAsyncTests->generateMd5();
        $manager->persist($hugeFileForAsyncTests);

        $manager->flush();

        // Store references
        $this->addReference(self::REFERENCE_SMALL_FILE, $smallFileUpload);
        $this->addReference(self::REFERENCE_LARGE_FILE, $largeFileUpload);
        $this->addReference(self::REFERENCE_HUGE_FILE, $hugeFileForAsyncTests);
    }

    private function createFileUpload(string $extension, string $baseDirectory): FileUpload
    {
        $fileUpload = new FileUpload($extension, $baseDirectory);

        // Ensure target directory exists
        $targetDir = dirname($fileUpload->getFullPath());
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new RuntimeException(sprintf('Directory "%s" could not be created', $targetDir));
            }
        }

        return $fileUpload;
    }

    private function createCsvFile(string $filePath, int $rowCount): void
    {
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not create file: %s', $filePath));
        }

        try {
            // Write header
            fputcsv($handle, ['ID', 'Full name', 'E-mail', 'City']);

            // Write test data
            for ($i = 1; $i <= $rowCount; $i++) {
                fputcsv($handle, [
                    $i,
                    "Test User $i",
                    "user$i@example.com",
                    "City $i"
                ]);
            }
        } finally {
            fclose($handle);
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('Failed to create file: %s', $filePath));
        }
    }

    public function getDependencies(): array
    {
        return [];
    }

    public static function getGroups(): array
    {
        return ['test'];
    }
}