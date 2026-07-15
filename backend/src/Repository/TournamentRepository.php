<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tournament;
use App\Enum\TournamentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
final class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * Ближайший незавершённый турнир (по дате). Для главной / регистрации.
     */
    public function findNearestUpcoming(): ?Tournament
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status != :finished')
            ->setParameter('finished', TournamentStatus::Finished)
            ->orderBy('t.date', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Tournament>
     */
    public function findAllOrderedByDateDesc(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
