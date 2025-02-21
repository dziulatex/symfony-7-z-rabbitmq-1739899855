<?php

namespace App\Service;

use App\Entity\FileUpload;
use App\Repository\FileUploadRepository;
use App\Service\Filesystem\FilesystemInterface;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;


use function strlen;

class FileUploader
{
    private $tempFile;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FileUploadRepository $fileUploadRepository,
        private readonly FilesystemInterface $filesystem,
    ) {
    }

    public function __destruct()
    {
        if (isset($this->tempFile)) {
            fclose($this->tempFile);
        }
    }

    public function upload(UploadedFile $file): array
    {
        try {
            $fileUpload = new FileUpload(
                extension: $file->guessExtension(),
                baseDirectory: $this->filesystem->getUploadsDirectory(),
            );

            // Check for duplicate file by MD5
            $md5 = md5_file($file->getPathname());
            if ($fileUploadFromDb = $this->fileUploadRepository->findOneBy(['md5Hash' => $md5])) {
                return [
                    'filename' => $fileUploadFromDb->getFilename(),
                    'path' => $fileUploadFromDb->getPath(),
                    'fullPath' => $fileUploadFromDb->getFullPath(),
                    'entity' => $fileUploadFromDb
                ];
            }

            // Upload the file using filesystem service
            $this->filesystem->uploadFile(
                $file->getPathname(),
                $fileUpload->getFullPath()
            );

            $fileUpload->generateMd5();
            $this->fileUploadRepository->save($fileUpload, true);
        } catch (Exception $e) {
            $this->cleanupTempFile();
            throw new Exception('An error occurred while uploading the file: ' . $e->getMessage());
        }

        $this->cleanupTempFile();

        return [
            'filename' => $fileUpload->getFilename(),
            'path' => $fileUpload->getPath(),
            'fullPath' => $fileUpload->getFullPath(),
            'entity' => $fileUpload
        ];
    }

    public function uploadFromUrl(string $url): array
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();

            $extension = $this->guessExtensionFromResponse($response, $url);
            $filename = Uuid::v4()->toString() . '.' . $extension;

            $uploadedFile = $this->createUploadedFile($content, $filename);

            return $this->upload($uploadedFile);
        } catch (Exception $e) {
            $this->cleanupTempFile();
            throw new Exception('Error downloading file from URL: ' . $e->getMessage());
        }
    }

    private function guessExtensionFromResponse($response, string $url): string
    {
        // Try to get extension from content type
        $contentType = $response->getHeaders()['content-type'][0] ?? '';

        // Map of common mime types to extensions
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'text/plain' => 'txt',
            'application/xml' => 'xml',
            'application/zip' => 'zip',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        // Try to get extension from content type
        if ($contentType && isset($mimeToExt[$contentType])) {
            return $mimeToExt[$contentType];
        }

        // Fallback to URL path extension
        $urlPath = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($urlPath, PATHINFO_EXTENSION);

        // If we found a valid extension in the URL, use it
        if ($extension && strlen($extension) <= 4) {
            return strtolower($extension);
        }

        // Default fallback
        return 'bin';
    }

    private function createUploadedFile(string $content, string $filename): UploadedFile
    {
        $this->tempFile = tmpfile();
        fwrite($this->tempFile, $content);
        $tempFilePath = stream_get_meta_data($this->tempFile)['uri'];

        return new UploadedFile(
            $tempFilePath,
            $filename,
            null,
            null,
            true
        );
    }

    private function cleanupTempFile(): void
    {
        if (isset($this->tempFile)) {
            fclose($this->tempFile);
            $this->tempFile = null;
        }
    }
}