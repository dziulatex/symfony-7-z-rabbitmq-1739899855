<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\FileUpload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

// Import for partial hydration

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * @param FileUpload|null $fileUpload
     * @param int $page
     * @param int $limit
     * @return array{clients: Client[], totalCount: int}
     */
    public function findClientsPaginated(?FileUpload $fileUpload, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('c');

        if ($fileUpload) {
            $qb->andWhere('c.fileUpload = :fileUpload')
                ->setParameter(
                    'fileUpload',
                    $fileUpload->getId()->toBinary()
                );
        }

        $qb->orderBy('c.fullName', 'ASC');


        // Separate query for total count (no hydration needed)
        $countQb = clone $qb;
        $totalCount = (int)$countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $clients = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult(); // Get the actual Client objects

        return [
            'clients' => $clients,
            'totalCount' => $totalCount,
        ];
    }
}