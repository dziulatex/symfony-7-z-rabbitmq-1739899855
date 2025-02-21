<?php

namespace App\Repository;

use App\Entity\FileUpload;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;


use RuntimeException;
use Symfony\Component\Uid\Uuid;

use function count;
use function ceil;

/**
 * @extends ServiceEntityRepository<FileUpload>
 *
 * @method FileUpload|null find($id, $lockMode = null, $lockVersion = null)
 * @method FileUpload|null findOneBy(array $criteria, array $orderBy = null)
 * @method FileUpload[]    findAll()
 * @method FileUpload[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileUploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileUpload::class);
    }

    public function getFilePathFromEntity(Uuid $fileId): array
    {
        $query = $this->getEntityManager()->createQuery(
            'SELECT f.path, f.filename
         FROM App\Entity\FileUpload f
         WHERE f.id = :fileId'
        );
        $query->setParameter('fileId', $fileId->toBinary());
        $result = $query->getSingleResult(AbstractQuery::HYDRATE_ARRAY);  // Use HYDRATE_ARRAY for a simple array result

        if (!$result) {
            throw new RuntimeException("File with ID $fileId not found."); // Or handle however you prefer
        }


        return [
            'path' => $result['path'],
            'fileName' => $result['filename'],
        ];
    }

    /**
     * Find files uploaded on a specific date
     */
    public function findByUploadDate(DateTime $date): array
    {
        $startDate = clone $date;
        $startDate->setTime(0, 0, 0);

        $endDate = clone $date;
        $endDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->andWhere('f.uploadedAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find files uploaded in a specific directory path
     */
    public function findByPath(string $path): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.path = :path')
            ->setParameter('path', $path)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find files uploaded between two dates
     */
    public function findByDateRange(DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.uploadedAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find files by filename pattern (using LIKE)
     */
    public function findByFilenamePattern(string $pattern): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.filename LIKE :pattern')
            ->setParameter('pattern', '%' . $pattern . '%')
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent uploads with limit
     */
    public function findRecentUploads(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.uploadedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count files uploaded on a specific date
     */
    public function countUploadsByDate(DateTime $date): int
    {
        $startDate = clone $date;
        $startDate->setTime(0, 0, 0);

        $endDate = clone $date;
        $endDate->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.uploadedAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete files older than specified date
     */
    public function deleteOlderThan(DateTime $date): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->andWhere('f.uploadedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Find duplicates based on filename
     */
    public function findDuplicateFilenames(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.filename')
            ->groupBy('f.filename')
            ->having('COUNT(f.id) > 1')
            ->getQuery()
            ->getResult();
    }

    public function findByFiltersPaginated(
        ?string $filename,
        ?string $startDate,
        ?string $endDate,
        ?string $path,
        int $page,
        int $perPage
    ): array {
        $queryBuilder = $this->createFilterQueryBuilder($filename, $startDate, $endDate, $path);
        return $this->paginate($queryBuilder, $page, $perPage);
    }

    private function createFilterQueryBuilder(
        ?string $filename,
        ?string $startDate,
        ?string $endDate,
        ?string $path
    ): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('f')
            ->orderBy('f.uploadedAt', 'DESC');

        if ($filename) {
            $queryBuilder
                ->andWhere('f.filename LIKE :filename')
                ->setParameter('filename', '%' . $filename . '%');
        }

        if ($startDate) {
            $start = new DateTime($startDate);
            $start->setTime(0, 0);
            $queryBuilder
                ->andWhere('f.uploadedAt >= :startDate')
                ->setParameter('startDate', $start);
        }

        if ($endDate) {
            $end = new DateTime($endDate);
            $end->setTime(23, 59, 59);
            $queryBuilder
                ->andWhere('f.uploadedAt <= :endDate')
                ->setParameter('endDate', $end);
        }

        if ($path) {
            $queryBuilder
                ->andWhere('f.path LIKE :path')
                ->setParameter('path', '%' . $path . '%');
        }

        return $queryBuilder;
    }

    private function paginate(QueryBuilder $queryBuilder, int $page, int $perPage): array
    {
        $firstResult = $perPage * ($page - 1);

        $queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($perPage);

        $paginator = new Paginator($queryBuilder);

        return [
            'files' => $paginator,
            'totalItems' => count($paginator),
            'lastPage' => ceil($paginator->count() / $perPage), // Use $paginator->count() instead of count($paginator)
            'currentPage' => $page,
        ];
    }

    public function save(FileUpload $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FileUpload $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}