<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Простой health-check: подтверждает, что API живой и БД доступна.
 * Пригодится для проверки деплоя и как первый рабочий эндпоинт каркаса.
 */
final class PingController extends AbstractController
{
    #[Route('/api/ping', name: 'api_ping', methods: ['GET'])]
    public function ping(Connection $connection): JsonResponse
    {
        // Лёгкий запрос — убеждаемся, что коннект к БД поднимается.
        $dbOk = false;
        try {
            $connection->executeQuery('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        return $this->json([
            'status' => 'ok',
            'db' => $dbOk,
        ]);
    }
}
