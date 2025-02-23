<?php

namespace App\Service\FileProcessing;

use App\Entity\FileUpload;
use App\Repository\FileUploadRepository;
use App\Service\Filesystem\FilesystemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use RuntimeException;

use Symfony\Component\Uid\Uuid;

use function sprintf;

class FileValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getFilePathFromEntity(string $fileId): string
    {
        $query = $this->entityManager->createQuery(
            'SELECT CONCAT(f.path, \'/\', f.filename) AS fullPath
             FROM App\Entity\FileUpload f
             WHERE f.id = :fileId'
        );
        $query->setParameter('fileId', Uuid::fromString($fileId));

        return $query->getSingleScalarResult();
    }
}