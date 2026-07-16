<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\BracketMatchRepository;
use App\Repository\UserRepository;

/**
 * Сводная статистика игроков из сыгранных матчей.
 * Очки = число побед (+1 за победу). Байи не учитываются (не сыгранная игра).
 */
final class StatsService
{
    public function __construct(
        private readonly BracketMatchRepository $matches,
        private readonly UserRepository $users,
        private readonly UserPresenter $presenter,
    ) {
    }

    /**
     * Таблица лидеров: по одному ряду на игрока, у кого есть сыгранные матчи.
     * Сортировка: очки ↓, победы ↓, имя ↑.
     *
     * @return list<array{userId: int, name: string, avatarUrl: string|null, games: int, wins: int, losses: int, points: int}>
     */
    public function leaderboard(): array
    {
        /** @var array<int, int> $played счётчик сыгранных матчей по игроку */
        $played = [];
        /** @var array<int, int> $wins счётчик побед по игроку */
        $wins = [];

        foreach ($this->matches->fetchPlayedResults() as $row) {
            $played[$row['p1']] = ($played[$row['p1']] ?? 0) + 1;
            $played[$row['p2']] = ($played[$row['p2']] ?? 0) + 1;
            if ($row['w'] !== null) {
                $wins[$row['w']] = ($wins[$row['w']] ?? 0) + 1;
            }
        }

        if ($played === []) {
            return [];
        }

        // Имена и аватары одним запросом.
        $names = [];
        $avatars = [];
        foreach ($this->users->findBy(['id' => array_keys($played)]) as $user) {
            /** @var User $user */
            $names[$user->getId()] = $user->getDisplayName();
            $avatars[$user->getId()] = $this->presenter->avatarUrl($user);
        }

        $rows = [];
        foreach ($played as $userId => $games) {
            $w = $wins[$userId] ?? 0;
            $rows[] = [
                'userId' => $userId,
                'name' => $names[$userId] ?? '—',
                'avatarUrl' => $avatars[$userId] ?? null,
                'games' => $games,
                'wins' => $w,
                'losses' => $games - $w,
                'points' => $w,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['points'], $b['wins'], $a['name']] <=> [$a['points'], $a['wins'], $b['name']];
        });

        return $rows;
    }
}
