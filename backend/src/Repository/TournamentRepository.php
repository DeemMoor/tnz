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
     *
     * Прошедшие турниры не показываем, даже если они так и не были завершены
     * (зависли из-за сбоя) — иначе такой турнир вечно висел бы на главной и
     * заслонял следующий. День самого турнира ещё считается актуальным (>=).
     */
    public function findNearestUpcoming(?\DateTimeImmutable $today = null): ?Tournament
    {
        $today ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('t')
            ->andWhere('t.status != :finished')
            ->andWhere('t.date >= :today')
            ->setParameter('finished', TournamentStatus::Finished)
            ->setParameter('today', $today->setTime(0, 0))
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

    /**
     * Завершённые турниры, новые сверху (для страницы чемпионов).
     *
     * @return list<Tournament>
     */
    public function findFinishedOrderedByDateDesc(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :finished')
            ->setParameter('finished', TournamentStatus::Finished)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
