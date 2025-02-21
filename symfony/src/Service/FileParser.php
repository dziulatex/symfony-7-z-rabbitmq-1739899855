<?php

namespace App\Service;

use App\Entity\Client;
use App\Service\Filesystem\FilesystemInterface;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

use function count;
use function is_resource;
use function sprintf;

class FileParser
{
    public function __construct(
        private readonly FilesystemInterface $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Gets the total number of lines in a file.
     *
     * @param string $filePath The path to the file.
     * @return int The number of lines in the file.
     * @throws FileException If the file cannot be read.
     */
    public function getLineCount(string $filePath): int
    {
        $stream = null;
        $lineCount = 0;

        try {
            $stream = $this->filesystem->getStream($filePath);

            while (!feof($stream)) {
                $line = fgets($stream);
                if ($line !== false) {
                    $lineCount++;
                }
            }

            return $lineCount;
        } catch (Exception $e) {
            if (!$e instanceof FileException) {
                throw new FileException(
                    sprintf('Error reading file "%s": %s', $filePath, $e->getMessage()),
                    0,
                    $e
                );
            }
            throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param string $filePath
     * @param int $startLine Start from line number (0 means start from beginning after header)
     * @param int $endLine End at line number (0 means process until end of file)
     * @return Generator<Client>
     */
    private function parseCsvFileGenerator(string $filePath, int $startLine, int $endLine): Generator
    {
        // Validate startLine if it's not 0
        if ($startLine !== 0 && $startLine < 2) {
            throw new InvalidArgumentException('startLine must be greater than 1 as line 1 is the header');
        }

        // Validate endLine against startLine if endLine is not 0
        if ($endLine !== 0) {
            $effectiveStartLine = ($startLine === 0) ? 2 : $startLine;
            if ($endLine < $effectiveStartLine) {
                throw new InvalidArgumentException('endLine must be greater than or equal to effective startLine');
            }
        }

        $stream = null;

        try {
            $stream = $this->filesystem->getStream($filePath);
            $header = fgetcsv($stream); // Read the header row

            if ($header === false) {
                throw new FileException(sprintf('Could not read header row from CSV file "%s"', $filePath));
            }

            // Validate header (optional, but good practice)
            $expectedHeader = ['ID', 'Full name', 'E-mail', 'City'];
            if ($header !== $expectedHeader) {
                throw new FileException(
                    sprintf(
                        'CSV header does not match expected format. Expected: "%s", Got: "%s"',
                        implode(',', $expectedHeader),
                        implode(',', $header)
                    )
                );
            }

            $rowNumber = 1; // Initialize row counter (header is row 1)

            // Skip rows if startLine is specified (not 0)
            if ($startLine > 0) {
                while ($rowNumber < $startLine - 1 && fgetcsv($stream) !== false) {
                    $rowNumber++;
                }
            }

            while (($row = fgetcsv($stream)) !== false) {
                $rowNumber++; // Increment row number for each row processed

                // Stop if we've reached endLine (only if endLine is not 0)
                if ($endLine !== 0 && $rowNumber > $endLine) {
                    break;
                }

                if (count($row) !== 4) {
                    $this->logger->info(
                        sprintf(
                            'Invalid row length in CSV file "%s" at row %d. Expected 4 columns, got %d. Data: "%s"',
                            $filePath,
                            $rowNumber,
                            count($row),
                            implode(',', $row)
                        )
                    );
                    continue;
                }

                [$id, $fullName, $email, $city] = $row;

                if (!is_numeric($id)) {
                    $this->logger->info(
                        sprintf(
                            'Invalid ID format in CSV file "%s" at row %d. ID is not numeric. Data: "%s"',
                            $filePath,
                            $rowNumber,
                            implode(',', $row)
                        )
                    );
                    continue;
                }

                if (!ctype_digit($id)) { // Check if $id is a string of digits (representing an integer)
                    $this->logger->info(
                        sprintf(
                            'Invalid Integer format in CSV file "%s" at row %d. ID is not a valid Integer: "%s". Data: "%s"',
                            $filePath,
                            $rowNumber,
                            $id,
                            implode(',', $row)
                        )
                    );
                    continue;
                }

                $client = new Client($id);
                $client->setFullName($fullName);
                $client->setEmail($email);
                $client->setCity($city);

                yield $client;
            }
        } catch (Exception $e) {
            if (!$e instanceof FileException) {
                throw new FileException(sprintf('Error parsing CSV file "%s": %s', $filePath, $e->getMessage()), 0, $e);
            }
            throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param string $filePath
     * @param int $startLine
     * @param int $endLine
     * @return Generator<Client>
     */
    public function parseCsvFile(string $filePath, int $startLine, int $endLine): Generator
    {
        return $this->parseCsvFileGenerator($filePath, $startLine, $endLine);
    }
}