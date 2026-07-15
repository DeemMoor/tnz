<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Repository\BracketMatchRepository;
use App\Service\TournamentSchedule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичная турнирная сетка: оба стола, туры, матчи, победители.
 */
final class BracketController extends AbstractController
{
    public function __construct(
        private readonly BracketMatchRepository $matches,
        private readonly TournamentSchedule $schedule,
    ) {
    }

    #[Route('/api/tournaments/{id}/bracket', name: 'api_tournament_bracket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function bracket(Tournament $tournament): JsonResponse
    {
        $all = $this->matches->findByTournamentOrdered($tournament);

        // Группируем по столу → туру.
        /** @var array<int, array<int, list<BracketMatch>>> $byTable */
        $byTable = [];
        /** @var array<int, int> $maxRound */
        $maxRound = [];
        foreach ($all as $m) {
            $byTable[$m->getTableNumber()][$m->getRound()][] = $m;
            $maxRound[$m->getTableNumber()] = max($maxRound[$m->getTableNumber()] ?? 0, $m->getRound());
        }
        ksort($byTable);

        $tables = [];
        foreach ($byTable as $tableNumber => $rounds) {
            ksort($rounds);
            $rMax = $maxRound[$tableNumber];
            $roundViews = [];
            foreach ($rounds as $round => $roundMatches) {
                $roundViews[] = [
                    'round' => $round,
                    'label' => $this->roundLabel($round, $rMax),
                    'matches' => array_map($this->matchView(...), $roundMatches),
                ];
            }
            $tables[] = [
                'tableNumber' => $tableNumber,
                'rounds' => $roundViews,
            ];
        }

        return $this->json([
            'tournament' => [
                'id' => $tournament->getId(),
                'number' => $this->schedule->number($tournament),
                'date' => $tournament->getDate()->format('Y-m-d'),
                'status' => $tournament->getStatus()->value,
            ],
            'tables' => $tables,
        ]);
    }

    /**
     * Название тура по стандарту: Финал / 1/2 / 1/4 / 1/8 финала.
     * Считаем по числу матчей в туре: matches = 2^(rMax - round).
     */
    private function roundLabel(int $round, int $rMax): string
    {
        $matchesInRound = 2 ** ($rMax - $round);

        return $matchesInRound === 1 ? 'Финал' : \sprintf('1/%d финала', $matchesInRound);
    }

    /**
     * @return array<string, mixed>
     */
    private function matchView(BracketMatch $m): array
    {
        return [
            'id' => $m->getId(),
            'slot' => $m->getSlot(),
            'player1' => $this->playerView($m->getPlayer1()),
            'player2' => $this->playerView($m->getPlayer2()),
            'winnerId' => $m->getWinner()?->getId(),
            'status' => $m->getStatus()->value,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function playerView(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return ['id' => $user->getId(), 'name' => $user->getName()];
    }
}
