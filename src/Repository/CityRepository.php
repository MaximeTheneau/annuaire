<?php

namespace App\Repository;

use App\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * @return City[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.department', 'd')
            ->andWhere('c.name LIKE :q OR d.name LIKE :q OR d.code LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
