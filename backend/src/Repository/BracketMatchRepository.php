<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Enum\MatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BracketMatch>
 */
final class BracketMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BracketMatch::class);
    }

    /**
     * Результаты всех реально сыгранных матчей (оба игрока присутствовали
     * и есть победитель) — для статистики. Байи (без второго игрока) не в счёт.
     *
     * @return list<array{p1: int, p2: int, w: int|null}>
     */
    public function fetchPlayedResults(): array
    {
        /** @var list<array{p1: int, p2: int, w: int|null}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.player1) AS p1', 'IDENTITY(m.player2) AS p2', 'IDENTITY(m.winner) AS w')
            ->andWhere('m.status = :done')
            ->andWhere('m.player1 IS NOT NULL')
            ->andWhere('m.player2 IS NOT NULL')
            ->andWhere('m.walkover = false') // техпобеды в статистику не идут
            ->setParameter('done', MatchStatus::Done)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Матчи одного стола турнира, по порядку тур/позиция.
     *
     * @return list<BracketMatch>
     */
    public function findByTournamentAndTable(Tournament $tournament, int $tableNumber): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.tournament = :t')
            ->andWhere('m.tableNumber = :tn')
            ->setParameter('t', $tournament)
            ->setParameter('tn', $tableNumber)
            ->orderBy('m.round', 'ASC')
            ->addOrderBy('m.slot', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByTournament(Tournament $tournament): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.tournament = :t')
            ->setParameter('t', $tournament)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Все матчи турнира, упорядоченные по столу/туру/позиции (для сетки).
     *
     * @return list<BracketMatch>
     */
    public function findByTournamentOrdered(Tournament $tournament): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.tournament = :t')
            ->setParameter('t', $tournament)
            ->orderBy('m.tableNumber', 'ASC')
            ->addOrderBy('m.round', 'ASC')
            ->addOrderBy('m.slot', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Матч конкретного стола/тура/позиции (для продвижения победителя).
     */
    public function findOneBySlot(Tournament $tournament, int $tableNumber, int $round, int $slot): ?BracketMatch
    {
        return $this->findOneBy([
            'tournament' => $tournament,
            'tableNumber' => $tableNumber,
            'round' => $round,
            'slot' => $slot,
        ]);
    }
}
