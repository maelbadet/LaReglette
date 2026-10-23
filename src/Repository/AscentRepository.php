<?php

namespace App\Repository;

use App\Entity\Ascent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ascent>
 */
class AscentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ascent::class);
    }

//    /**
//     * @return Ascent[] Returns an array of Ascent objects
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

//    public function findOneBySomeField($value): ?Ascent
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    /** @return list<Ascent> */
    public function getAscentByUser(int $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.boulder', 'b')->addSelect('b')
            ->andWhere('a.user = :user')->setParameter('user', $user)
            ->orderBy('a.completedAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->getQuery()->getResult();
    }
}
