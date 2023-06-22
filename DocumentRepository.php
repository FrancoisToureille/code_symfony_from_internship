<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 *
 * @method Document|null find($id, $lockMode = null, $lockVersion = null)
 * @method Document|null findOneBy(array $criteria, array $orderBy = null)
 * @method Document[]    findAll()
 * @method Document[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByDocumentsTypeAffrete($affrete, $type, $dateMin): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.validityEnd >= :dateMin')
            ->andWhere('e.affrete = :affrete')
            ->andWhere('e.type = :type')
            ->setParameter('affrete', $affrete)
            ->setParameter('type', $type)
            ->setParameter('dateMin', $dateMin)
            ->orderBy('e.validityEnd', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDocumentsEtat($affrete, $dateMin, $etat): array
    {
        return $this->createQueryBuilder('attente')
            ->where('attente.validityEnd >= :dateMin')
            ->andWhere('attente.affrete = :affrete')
            ->andWhere('attente.analysisState = :etat')
            ->setParameter('affrete', $affrete)
            ->setParameter('dateMin', $dateMin)
            ->setParameter('etat', $etat)
            ->getQuery()
            ->getResult();
    }

    public function findDocumentsEtatType($affrete, $type, $dateMin, $etat): array
    {
        return $this->createQueryBuilder('documentTypeEtat')
            ->where('documentTypeEtat.validityEnd >= :dateMin')
            ->andWhere('documentTypeEtat.affrete = :affrete')
            ->andWhere('documentTypeEtat.type = :type')
            ->andWhere('documentTypeEtat.analysisState = :etat')
            ->setParameter('etat', $etat)
            ->setParameter('affrete', $affrete)
            ->setParameter('type', $type)
            ->setParameter('dateMin', $dateMin)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Document[] Returns an array of Document objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Document
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
