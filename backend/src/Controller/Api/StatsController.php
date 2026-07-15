<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичная статистика игроков (таблица лидеров).
 */
final class StatsController extends AbstractController
{
    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function stats(StatsService $stats): JsonResponse
    {
        return $this->json(['players' => $stats->leaderboard()]);
    }
}
