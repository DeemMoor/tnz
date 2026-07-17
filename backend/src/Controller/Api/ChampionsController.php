<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Enum\MatchStatus;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentRepository;
use App\Service\TournamentSchedule;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичная страница чемпионов: кто выиграл финал стола в каждом турнире.
 * История ведётся за всё время (по всем турнирам/годам).
 */
final class ChampionsController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournaments,
        private readonly BracketMatchRepository $matches,
        private readonly TournamentSchedule $schedule,
        private readonly UserPresenter $presenter,
    ) {
    }

    #[Route('/api/champions', name: 'api_champions', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $result = [];

        foreach ($this->tournaments->findFinishedOrderedByDateDesc() as $tournament) {
            $champions = $this->championsOf($tournament);
            if ($champions === []) {
                continue;
            }
            $result[] = [
                'number' => $this->schedule->number($tournament),
                'date' => $tournament->getDate()->format('Y-m-d'),
                'champions' => $champions,
            ];
        }

        return $this->json(['tournaments' => $result]);
    }

    /**
     * Чемпион(ы) турнира — победители финалов столов (финал = матч с максимальным
     * туром на столе).
     *
     * @return list<array{tableNumber: int, name: string, avatarUrl: string|null}>
     */
    private function championsOf(Tournament $tournament): array
    {
        $matches = $this->matches->findByTournamentOrdered($tournament);

        // Максимальный тур (финал) для каждого стола.
        $maxRound = [];
        foreach ($matches as $m) {
            $table = $m->getTableNumber();
            $maxRound[$table] = max($maxRound[$table] ?? 0, $m->getRound());
        }

        $champions = [];
        foreach ($matches as $m) {
            if ($m->getRound() !== ($maxRound[$m->getTableNumber()] ?? -1)) {
                continue; // не финал стола
            }
            if ($m->getStatus() !== MatchStatus::Done || $m->getWinner() === null) {
                continue;
            }
            $champions[] = [
                'tableNumber' => $m->getTableNumber(),
                'name' => $m->getWinner()->getDisplayName(),
                'avatarUrl' => $this->presenter->avatarUrl($m->getWinner()),
            ];
        }

        // По номеру стола.
        usort($champions, static fn (array $a, array $b) => $a['tableNumber'] <=> $b['tableNumber']);

        return $champions;
    }
}
