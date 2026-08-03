<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReplacedGame;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReplacedGame>
 */
final class ReplacedGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReplacedGame::class);
    }

    /**
     * Сколько таких игр выиграл каждый игрок: userId => число побед.
     * Для статистики: каждая строка = +1 игра и +1 победа победителю.
     *
     * @return array<int, int>
     */
    public function fetchWinCounts(): array
    {
        /** @var list<array{w: int, c: int}> $rows */
        $rows = $this->createQueryBuilder('g')
            ->select('IDENTITY(g.winner) AS w', 'COUNT(g.id) AS c')
            ->groupBy('g.winner')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['w']] = (int) $row['c'];
        }

        return $counts;
    }

    /**
     * Проигравшие в вытесненных играх этого турнира — они выбыли из сетки, но
     * остаются кандидатами на подсадку в свободный слот.
     *
     * @return list<User>
     */
    public function findLosersByTournament(Tournament $tournament): array
    {
        /** @var list<ReplacedGame> $games */
        $games = $this->findBy(['tournament' => $tournament]);

        $losers = [];
        foreach ($games as $game) {
            $loser = $game->getLoser();
            $losers[(int) $loser->getId()] = $loser;
        }

        return array_values($losers);
    }
}
