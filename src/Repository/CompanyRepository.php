<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\City;
use App\Entity\Category;
use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * @return Company[]
     */
    public function findByCity(City $city): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->andWhere('a.city = :city')
            ->setParameter('city', $city)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Company[]
     */
    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->join('a.city', 'city')
            ->join('city.department', 'department')
            ->andWhere('c.category = :category')
            ->setParameter('category', $category)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Company[]
     */
    public function findByCategoryAndDepartment(Category $category, Department $department): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->join('a.city', 'city')
            ->join('city.department', 'department')
            ->andWhere('c.category = :category')
            ->andWhere('department = :department')
            ->setParameter('category', $category)
            ->setParameter('department', $department)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Company[]
     */
    public function findByCategoryAndCity(Category $category, City $city): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->join('a.city', 'city')
            ->join('city.department', 'department')
            ->andWhere('c.category = :category')
            ->andWhere('city = :city')
            ->setParameter('category', $category)
            ->setParameter('city', $city)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByCity(Department $department): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(a.city) as cityId, COUNT(c.id) as total')
            ->join('c.address', 'a')
            ->join('a.city', 'city')
            ->andWhere('city.department = :department')
            ->setParameter('department', $department)
            ->groupBy('a.city')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $binary = $row['cityId'];
            // IDENTITY() may return raw binary(16) or the converted hex string depending on Doctrine hydration
            $key = is_string($binary) && strlen($binary) === 16 ? bin2hex($binary) : (string) $binary;
            $counts[$key] = (int) $row['total'];
        }

        return $counts;
    }

    public function findOneBySlugCityDepartment(string $slug, City $city): ?Company
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->andWhere('c.slug = :slug')
            ->andWhere('a.city = :city')
            ->setParameter('slug', $slug)
            ->setParameter('city', $city)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Company[]
     */
    public function searchByQuery(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.address', 'a')
            ->join('a.city', 'city')
            ->join('city.department', 'department')
            ->leftJoin('c.category', 'category')
            ->andWhere('c.name LIKE :q OR city.name LIKE :q OR department.name LIKE :q OR category.name LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
