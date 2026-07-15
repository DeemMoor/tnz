<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Exception\RegistrationException;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Жеребьёвка: из итогового ростера строит сетки на двух столах.
 * Стол 1 набивается до 16, остаток идёт на стол 2. Внутри стола — случайные
 * пары single-elimination с автопроходами (bye) при нехватке до степени двойки.
 */
final class DrawService
{
    private const int TABLE_SIZE = 16;

    public function __construct(
        private readonly TournamentEntryRepository $entries,
        private readonly BracketMatchRepository $matches,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Провести жеребьёвку турнира.
     *
     * @return array{table1: int, table2: int} число игроков на столах
     *
     * @throws RegistrationException
     */
    public function draw(Tournament $tournament): array
    {
        if ($this->matches->countByTournament($tournament) > 0) {
            throw new RegistrationException('Жеребьёвка уже проведена', 409);
        }
        if ($tournament->getStatus() !== TournamentStatus::Checkin) {
            throw new RegistrationException('Сначала закройте чекин, потом жеребьёвка', 409);
        }

        $roster = $this->entries->findByStatusOrdered($tournament, EntryStatus::Registered);
        if ($roster === []) {
            throw new RegistrationException('Нет игроков для жеребьёвки', 422);
        }

        // Игроки в случайном порядке.
        $entriesByUserId = [];
        $players = [];
        foreach ($roster as $entry) {
            $players[] = $entry->getUser();
            $entriesByUserId[$entry->getUser()->getId()] = $entry;
        }
        shuffle($players);

        // Стол 1 — до 16, остаток — стол 2.
        $table1 = \array_slice($players, 0, self::TABLE_SIZE);
        $table2 = \array_slice($players, self::TABLE_SIZE, self::TABLE_SIZE);

        foreach ($table1 as $u) {
            $entriesByUserId[$u->getId()]->setTableNumber(1);
        }
        foreach ($table2 as $u) {
            $entriesByUserId[$u->getId()]->setTableNumber(2);
        }

        $this->generateTable($tournament, 1, $table1);
        if ($table2 !== []) {
            $this->generateTable($tournament, 2, $table2);
        }

        $tournament->setStatus(TournamentStatus::Drawn);
        $this->em->flush();

        return ['table1' => \count($table1), 'table2' => \count($table2)];
    }

    /**
     * Построить сетку одного стола и раскидать байи.
     *
     * @param list<User> $players
     */
    private function generateTable(Tournament $tournament, int $tableNumber, array $players): void
    {
        $count = \count($players);
        if ($count === 0) {
            return;
        }
        if ($count === 1) {
            // Единственный игрок — автоматически чемпион стола (без матчей-соперников).
            $match = new BracketMatch($tournament, $tableNumber, 1, 0);
            $match->setPlayer1($players[0]);
            $match->setWinner($players[0]);
            $this->em->persist($match);

            return;
        }

        $bracketSize = 1;
        while ($bracketSize < $count) {
            $bracketSize <<= 1; // ближайшая степень двойки ≥ count
        }
        $rounds = (int) log($bracketSize, 2);
        $round1Slots = intdiv($bracketSize, 2);
        $realMatches = $count - $round1Slots; // пары «игрок vs игрок»
        $byes = $bracketSize - $count;        // игроки с автопроходом

        // Создаём пустые матчи всех туров.
        /** @var array<int, array<int, BracketMatch>> $grid */
        $grid = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $slots = intdiv($bracketSize, 2 ** $r);
            for ($s = 0; $s < $slots; $s++) {
                $match = new BracketMatch($tournament, $tableNumber, $r, $s);
                $this->em->persist($match);
                $grid[$r][$s] = $match;
            }
        }

        // Заполняем первый тур: сначала реальные пары, потом байи.
        $idx = 0;
        $slot = 0;
        for ($i = 0; $i < $realMatches; $i++) {
            $grid[1][$slot]->setPlayer1($players[$idx++]);
            $grid[1][$slot]->setPlayer2($players[$idx++]);
            $slot++;
        }
        for ($k = 0; $k < $byes; $k++) {
            $byePlayer = $players[$idx++];
            $grid[1][$slot]->setPlayer1($byePlayer);
            $grid[1][$slot]->setWinner($byePlayer); // автопроход
            $slot++;
        }

        // Победителей автопроходов сразу двигаем в следующий тур.
        if ($rounds >= 2) {
            for ($s = 0; $s < $round1Slots; $s++) {
                $match = $grid[1][$s];
                if ($match->getStatus() === MatchStatus::Done && $match->getWinner() !== null) {
                    $this->placeIntoNext($grid, 1, $s, $match->getWinner());
                }
            }
        }
    }

    /**
     * Поставить победителя матча (round, slot) на его место в следующем туре.
     *
     * @param array<int, array<int, BracketMatch>> $grid
     */
    private function placeIntoNext(array $grid, int $round, int $slot, User $winner): void
    {
        $nextRound = $round + 1;
        if (!isset($grid[$nextRound])) {
            return; // это был финал
        }
        $nextSlot = intdiv($slot, 2);
        $next = $grid[$nextRound][$nextSlot];
        if ($slot % 2 === 0) {
            $next->setPlayer1($winner);
        } else {
            $next->setPlayer2($winner);
        }
    }
}
