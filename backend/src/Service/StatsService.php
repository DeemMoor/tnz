<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\BracketMatchRepository;
use App\Repository\ReplacedGameRepository;
use App\Repository\UserRepository;

/**
 * Сводная статистика игроков из сыгранных матчей.
 * Очки = число побед (+1 за победу). Байи не учитываются (не сыгранная игра).
 */
final class StatsService
{
    public function __construct(
        private readonly BracketMatchRepository $matches,
        private readonly ReplacedGameRepository $replacedGames,
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

        // Игры, вытесненные из сетки заменой игрока (подошёл опоздавший, и
        // победитель играет заново уже с ним). Засчитываем только победителю —
        // заменённому по договорённости ничего не пишем.
        foreach ($this->replacedGames->fetchWinCounts() as $userId => $count) {
            $played[$userId] = ($played[$userId] ?? 0) + $count;
            $wins[$userId] = ($wins[$userId] ?? 0) + $count;
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

    /**
     * Статистика одного игрока плюс его место в общей таблице (rank).
     * Если игрок ещё не сыграл ни одного матча — нули и rank = null.
     *
     * @return array{games: int, wins: int, losses: int, points: int, rank: int|null}
     */
    public function forUser(User $user): array
    {
        $userId = $user->getId();

        foreach ($this->leaderboard() as $i => $row) {
            if ($row['userId'] === $userId) {
                return [
                    'games' => $row['games'],
                    'wins' => $row['wins'],
                    'losses' => $row['losses'],
                    'points' => $row['points'],
                    'rank' => $i + 1,
                ];
            }
        }

        return ['games' => 0, 'wins' => 0, 'losses' => 0, 'points' => 0, 'rank' => null];
    }
}
