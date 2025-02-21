<?php

namespace App\Tests\Integration\Service;

use App\Service\FileParser;
use App\Service\Filesystem\FilesystemInterface;
use App\Service\Filesystem\LocalFilesystem;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileParserTest extends KernelTestCase
{
    private FileParser $fileParser;
    private LocalFilesystem $filesystem;
    private LoggerInterface $logger;
    private string $tempDir;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->filesystem = self::getContainer()->get(LocalFilesystem::class);
        $this->logger = self::getContainer()->get(LoggerInterface::class);
        $this->fileParser = self::getContainer()->get(FileParser::class);

        // Create temp directory
        $this->tempDir = sys_get_temp_dir() . '/file_parser_test_' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        array_map('unlink', glob("$this->tempDir/*.*"));
        rmdir($this->tempDir);

        parent::tearDown();
    }

    public function testGetLineCount(): void
    {
        // Create a test file
        $testContent = "header\nline1\nline2\nline3\n";
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, $testContent);

        $lineCount = $this->fileParser->getLineCount($filePath);
        self::assertEquals(4, $lineCount);
    }

    public function testGetLineCountWithEmptyFile(): void
    {
        $filePath = $this->tempDir . '/empty.txt';
        file_put_contents($filePath, '');

        $lineCount = $this->fileParser->getLineCount($filePath);
        self::assertEquals(0, $lineCount);
    }

    public function testGetLineCountWithNonexistentFile(): void
    {
        $this->expectException(FileException::class);
        $this->fileParser->getLineCount($this->tempDir . '/nonexistent.txt');
    }

    public function testParseCsvFileSuccess(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "1,John Doe,john@example.com,New York\n";
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";

        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 0));

        self::assertCount(2, $clients);
        self::assertEquals('John Doe', $clients[0]->getFullName());
        self::assertEquals('jane@example.com', $clients[1]->getEmail());
    }

    public function testParseCsvFileWithStartLine(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "1,John Doe,john@example.com,New York\n";
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";
        $csvContent .= "3,Bob Wilson,bob@example.com,Chicago\n";

        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 4, 0));
        self::assertCount(1, $clients);
        self::assertEquals('Bob Wilson', $clients[0]->getFullName());
    }

    public function testParseCsvFileWithInvalidHeader(): void
    {
        $csvContent = "Invalid,Header,Format\n";
        $csvContent .= "1,John Doe,New York\n";

        $filePath = $this->tempDir . '/invalid.csv';
        file_put_contents($filePath, $csvContent);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('CSV header does not match expected format');

        iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 0));
    }

    public function testParseCsvFileWithInvalidStartLine(): void
    {
        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, "ID,Full name,E-mail,City\n1,John,john@test.com,NYC\n");

        $this->expectException(InvalidArgumentException::class);
        iterator_to_array($this->fileParser->parseCsvFile($filePath, 1, 0));
    }

    public function testParseCsvFileWithInvalidEndLine(): void
    {
        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, "ID,Full name,E-mail,City\n1,John,john@test.com,NYC\n");

        $this->expectException(InvalidArgumentException::class);
        iterator_to_array($this->fileParser->parseCsvFile($filePath, 2, 1));
    }

    public function testHeaderIsFalse(): void
    {
        $emptyFile = $this->tempDir . '/empty.csv';
        file_put_contents($emptyFile, '');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Could not read header row from CSV file');

        iterator_to_array($this->fileParser->parseCsvFile($emptyFile, 0, 0));
    }

    public function testEndLineBreak(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "1,John Doe,john@example.com,New York\n";
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";
        $csvContent .= "3,Bob Wilson,bob@example.com,Chicago\n";

        $filePath = $this->tempDir . '/endline.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 3));
        self::assertCount(2, $clients);
        self::assertEquals('John Doe', $clients[0]->getFullName());
        self::assertEquals('Jane Smith', $clients[1]->getFullName());
    }


    public function testInvalidRowLength(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "1,John Doe,john@example.com\n"; // Missing city
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";

        $filePath = $this->tempDir . '/invalid_length.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 0));

        // Only valid row should be processed
        self::assertCount(1, $clients);
        self::assertEquals('Jane Smith', $clients[0]->getFullName());
    }

    public function testInvalidIdFormat(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "abc,John Doe,john@example.com,New York\n"; // Non-numeric ID
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";

        $filePath = $this->tempDir . '/invalid_id.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 0));

        // Only valid row should be processed
        self::assertCount(1, $clients);
        self::assertEquals('Jane Smith', $clients[0]->getFullName());
    }

    public function testInvalidIntegerFormat(): void
    {
        $csvContent = "ID,Full name,E-mail,City\n";
        $csvContent .= "1.5,John Doe,john@example.com,New York\n"; // Float ID
        $csvContent .= "2,Jane Smith,jane@example.com,Los Angeles\n";

        $filePath = $this->tempDir . '/invalid_integer.csv';
        file_put_contents($filePath, $csvContent);

        $clients = iterator_to_array($this->fileParser->parseCsvFile($filePath, 0, 0));

        // Only valid row should be processed
        self::assertCount(1, $clients);
        self::assertEquals('Jane Smith', $clients[0]->getFullName());
    }

    public function testFileException(): void
    {
        // Mock filesystem to throw an exception
        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockFilesystem->method('getStream')
            ->willThrowException(new \RuntimeException('Failed to open stream'));

        $parser = new FileParser($mockFilesystem, $this->logger);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Error parsing CSV file');

        iterator_to_array($parser->parseCsvFile('test.csv', 0, 0));
    }

    public function testGenericExceptionWrapping(): void
    {
        // Use a mock filesystem that throws a generic exception
        $mockFilesystem = $this->createMock(FilesystemInterface::class);
        $mockFilesystem->method('getStream')
            ->willThrowException(new RuntimeException('Generic error'));

        $parser = new FileParser($mockFilesystem, $this->logger);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Error parsing CSV file');

        iterator_to_array($parser->parseCsvFile('test.csv', 0, 0));
    }
}