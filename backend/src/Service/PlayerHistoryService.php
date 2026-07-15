<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\MatchStatus;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentEntryRepository;

/**
 * История выступлений игрока по турнирам: до какой стадии дошёл, побед/поражений.
 */
final class PlayerHistoryService
{
    public function __construct(
        private readonly TournamentEntryRepository $entries,
        private readonly BracketMatchRepository $matches,
        private readonly TournamentSchedule $schedule,
    ) {
    }

    /**
     * @return list<array{id: int, number: int, date: string, status: string, tableNumber: int, stage: string, games: int, wins: int, losses: int}>
     */
    public function forUser(User $user): array
    {
        $result = [];

        foreach ($this->entries->findBy(['user' => $user]) as $entry) {
            $table = $entry->getTableNumber();
            if ($table === null) {
                continue; // не участвовал в сетке (не дошёл до жеребьёвки)
            }

            $tournament = $entry->getTournament();
            $tableMatches = $this->matches->findByTournamentAndTable($tournament, $table);
            if ($tableMatches === []) {
                continue;
            }

            $rMax = 0;
            foreach ($tableMatches as $m) {
                $rMax = max($rMax, $m->getRound());
            }

            $wins = 0;
            $losses = 0;
            $maxRoundReached = 0;
            $wonFinal = false;

            foreach ($tableMatches as $m) {
                $isP1 = $m->getPlayer1() === $user;
                $isP2 = $m->getPlayer2() === $user;
                if (!$isP1 && !$isP2) {
                    continue;
                }

                // Стадия: самый дальний тур, где игрок вообще присутствовал (в т.ч. bye).
                $maxRoundReached = max($maxRoundReached, $m->getRound());

                // Победы/поражения считаем только по реально сыгранным матчам.
                if ($m->getStatus() === MatchStatus::Done && $m->getPlayer1() !== null && $m->getPlayer2() !== null) {
                    if ($m->getWinner() === $user) {
                        $wins++;
                        if ($m->getRound() === $rMax) {
                            $wonFinal = true;
                        }
                    } else {
                        $losses++;
                    }
                }
            }

            $stage = $wonFinal ? 'Чемпион стола' : $this->roundLabel($maxRoundReached, $rMax);

            $result[] = [
                'id' => $tournament->getId(),
                'number' => $this->schedule->number($tournament),
                'date' => $tournament->getDate()->format('Y-m-d'),
                'status' => $tournament->getStatus()->value,
                'tableNumber' => $table,
                'stage' => $stage,
                'games' => $wins + $losses,
                'wins' => $wins,
                'losses' => $losses,
            ];
        }

        // Свежие турниры сверху.
        usort($result, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $result;
    }

    private function roundLabel(int $round, int $rMax): string
    {
        if ($round < 1) {
            return '—';
        }
        $matchesInRound = 2 ** ($rMax - $round);

        return $matchesInRound === 1 ? 'Финал' : \sprintf('1/%d финала', $matchesInRound);
    }
}
