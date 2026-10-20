<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Búsqueda segura con parámetros enlazados (Prepared Statements de Doctrine)
     * para prevenir Inyección SQL (OWASP API8 / Top 10 A03: Injection).
     *
     * @return Product[]
     */
    public function searchCatalog(?string $search = null, ?string $category = null, bool $onlyActive = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC');

        if ($onlyActive) {
            $qb->andWhere('p.active = :active')
               ->setParameter('active', true);
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:search) OR LOWER(p.description) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($category !== null && trim($category) !== '') {
            $qb->andWhere('LOWER(p.category) = LOWER(:category)')
               ->setParameter('category', trim($category));
        }

        return $qb->getQuery()->getResult();
    }
}
