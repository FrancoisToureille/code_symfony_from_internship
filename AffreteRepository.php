<?php

namespace App\Repository;

use App\Entity\Affrete;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Affrete>
 *
 * @method Affrete|null find($id, $lockMode = null, $lockVersion = null)
 * @method Affrete|null findOneBy(array $criteria, array $orderBy = null)
 * @method Affrete[]    findAll()
 * @method Affrete[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AffreteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Affrete::class);
    }

    public function save(Affrete $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Affrete $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    public function findByFilters(?string $label, ?string $companyName, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('1=1'); // Add a dummy condition to make it easier to add more conditions

        if ($label !== null) {
            $qb->andWhere('a.label= :label')
                ->setParameter('label', $label);
        }

        if ($companyName !== null) {
            $qb->andWhere('a.companyName LIKE :companyName')
                ->setParameter('companyName', '%' . $companyName . '%');
        }

        // Paginate the results
        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return Affrete[] Returns an array of Affrete objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Affrete
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
