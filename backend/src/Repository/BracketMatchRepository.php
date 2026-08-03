<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\User;
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

    /**
     * Выбывшие из сетки: проиграли реальный матч (не техпобеда) и больше нигде
     * в турнире не живы — не ждут своего матча и ничего не выигрывали.
     * Это кандидаты на подсадку в свободный слот другого стола.
     *
     * @return list<User>
     */
    public function findEliminatedPlayers(Tournament $tournament): array
    {
        $all = $this->findByTournamentOrdered($tournament);

        /** @var array<int, User> $losers */
        $losers = [];
        /** @var array<int, true> $alive */
        $alive = [];

        foreach ($all as $m) {
            $p1 = $m->getPlayer1();
            $p2 = $m->getPlayer2();

            if ($m->getStatus() !== MatchStatus::Done) {
                // Матч ещё не сыгран — оба его игрока в игре.
                foreach ([$p1, $p2] as $p) {
                    if ($p !== null) {
                        $alive[(int) $p->getId()] = true;
                    }
                }

                continue;
            }

            $winner = $m->getWinner();
            if ($winner !== null) {
                $alive[(int) $winner->getId()] = true;
            }

            // Проигравший реально сыгранного матча (байи и техпобеды не в счёт).
            if ($p1 !== null && $p2 !== null && !$m->isWalkover()) {
                $loser = $winner === $p1 ? $p2 : $p1;
                $losers[(int) $loser->getId()] = $loser;
            }
        }

        foreach (array_keys($alive) as $id) {
            unset($losers[$id]);
        }

        return array_values($losers);
    }

    /**
     * Есть ли у игрока хоть одно место в сетке этого турнира (любой стол/тур).
     * Используется, чтобы отличить "своего" (уже где-то в сетке) от нового
     * walk-in-игрока при подсадке в bye-слот.
     */
    public function hasAppearance(Tournament $tournament, User $user): bool
    {
        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.tournament = :t')
            ->andWhere('m.player1 = :u OR m.player2 = :u')
            ->setParameter('t', $tournament)
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
