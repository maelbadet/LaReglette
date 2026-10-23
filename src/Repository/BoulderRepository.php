<?php

namespace App\Repository;

use App\Entity\Boulder;
use App\Entity\User;
use App\Entity\Sector;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Boulder>
 */
class BoulderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Boulder::class);
    }

    /** @return list<Boulder> */
    public function findAvailable(array $filters, ?User $user = null, ?Sector $sector = null, bool $withAscents = false): array
    {
        $query = $this->createQueryBuilder('b')->addSelect('s')->join('b.sector', 's')
            ->andWhere('b.openedAt <= :now')->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')
            ->setParameter('now', new DateTimeImmutable())->orderBy('b.openedAt', 'DESC')->addOrderBy('b.id', 'DESC');
        if (null !== $sector) {
            $query->andWhere('b.sector = :sector')->setParameter('sector', $sector);
        }
        foreach (['sector', 'color', 'grade'] as $field) {
            if (null !== ($filters[$field] ?? null) && '' !== $filters[$field]) {
                $query->andWhere('b.' . $field . ' = :filter_' . $field)->setParameter('filter_' . $field, $filters[$field]);
            }
        }
        if (null !== ($filters['gradeMin'] ?? null)) {
            $query->andWhere('b.grade >= :gradeMin')->setParameter('gradeMin', $filters['gradeMin']);
        }
        if (null !== ($filters['gradeMax'] ?? null)) {
            $query->andWhere('b.grade <= :gradeMax')->setParameter('gradeMax', $filters['gradeMax']);
        }
        if ($filters['unfinished'] ?? false) {
            if (null === $user) {
                throw new \InvalidArgumentException('Le filtre unfinished nécessite un utilisateur.');
            }
            $query->leftJoin('b.ascents', 'a', 'WITH', 'a.user = :user')->andWhere('a.id IS NULL')->setParameter('user', $user);
        }
        if ($withAscents) {
            $query->leftJoin('b.ascents', 'success')->addSelect('success')
                ->addOrderBy('success.completedAt', 'DESC')->addOrderBy('success.id', 'DESC');
        }

        return $query->getQuery()->getResult();
    }

    public function statistics(Boulder $boulder): array
    {
        return ['ascents' => $boulder->getSuccessCount(), 'climbers' => $boulder->getClimberCount(), 'rating' => $boulder->getAverageRating()];
    }

    /** @return list<Boulder> */
    public function findOpenedBy(User $opener): array
    {
        return $this->createQueryBuilder('b')->addSelect('s')->join('b.sector', 's')
            ->andWhere('b.openedBy = :opener')->setParameter('opener', $opener)
            ->orderBy('b.openedAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Boulder> */
    public function findAvailableOpenedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('b')->addSelect('s')->join('b.sector', 's')
            ->andWhere('b.openedAt < :cutoff')->andWhere('b.openedAt <= :now')
            ->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')
            ->setParameter('cutoff', $cutoff)->setParameter('now', $now)
            ->orderBy('b.openedAt', 'ASC')->addOrderBy('b.id', 'ASC')
            ->getQuery()->getResult();
    }

    /** Only called by the asynchronous statistics handler. */
    public function calculateStatistics(Boulder $boulder): array
    {
        return $this->getEntityManager()->createQuery('SELECT COUNT(a.id) AS ascents, COUNT(DISTINCT IDENTITY(a.user)) AS climbers, AVG(a.rating) AS rating FROM App\\Entity\\Ascent a WHERE a.boulder = :boulder')
            ->setParameter('boulder', $boulder)->getSingleResult();
    }

    //    /**
    //     * @return Boulder[] Returns an array of Boulder objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Boulder
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    # liste des blocs en place (possibilité de filtre).
    public function GetAvailableBoudlers(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.openedAt <= :now')
            ->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('b.openedAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->getQuery()->getResult();
    }

    # Les blocs en place que le grimpeur connecté n'a pas encore réussis.
    /** @return list<Boulder> */
    public function getBoulderUndoByUSer(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.ascents', 'a', 'WITH', 'a.user = :user')
            ->andWhere('a.id IS NULL')
            ->andWhere('b.openedAt <= :now')
            ->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('b.openedAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->getQuery()->getResult();
    }

    #  Les blocs en place sans aucune réussite depuis au moins deux semaines
    /** @return list<Boulder> */
    public function getBoulderUnSuccessSinceTimelaps(DateTimeImmutable $dateTimeImmutable, ?DateTimeImmutable $now = null): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.ascents', 'a', 'WITH', 'a.completedAt >= :since')
            ->andWhere('a.id IS NULL')
            ->andWhere('b.openedAt <= :since')
            ->andWhere('b.openedAt <= :now')
            ->andWhere('b.dismantledAt IS NULL OR b.dismantledAt > :now')
            ->setParameter('since', $dateTimeImmutable)
            ->setParameter('now', $now ?? new DateTimeImmutable())
            ->orderBy('b.openedAt', 'ASC')->addOrderBy('b.id', 'ASC')
            ->getQuery()->getResult();
    }
}
