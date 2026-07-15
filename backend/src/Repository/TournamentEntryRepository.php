<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentEntry>
 */
final class TournamentEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentEntry::class);
    }

    public function findOneByTournamentAndUser(Tournament $tournament, User $user): ?TournamentEntry
    {
        return $this->findOneBy(['tournament' => $tournament, 'user' => $user]);
    }

    public function countByStatus(Tournament $tournament, EntryStatus $status): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.tournament = :t')
            ->andWhere('e.status = :s')
            ->setParameter('t', $tournament)
            ->setParameter('s', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Записи заданного статуса в порядке регистрации (для основы/очереди).
     *
     * @return list<TournamentEntry>
     */
    public function findByStatusOrdered(Tournament $tournament, EntryStatus $status): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.tournament = :t')
            ->andWhere('e.status = :s')
            ->setParameter('t', $tournament)
            ->setParameter('s', $status)
            ->orderBy('e.registeredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Первый в очереди ожидания (самый ранний по времени регистрации).
     */
    public function findFirstWaitlisted(Tournament $tournament): ?TournamentEntry
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.tournament = :t')
            ->andWhere('e.status = :s')
            ->setParameter('t', $tournament)
            ->setParameter('s', EntryStatus::Waitlisted)
            ->orderBy('e.registeredAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
