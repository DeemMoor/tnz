<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\StatsService;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичная карточка игрока: аватар, имя и рейтинг по внутренним турнирам.
 * Контакты (телефон, email) наружу не отдаём.
 */
final class PlayerController extends AbstractController
{
    #[Route('/api/players/{id}', name: 'api_player_card', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function card(User $user, StatsService $stats, UserPresenter $presenter): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getDisplayName(),
            'avatarUrl' => $presenter->avatarUrl($user),
            'isChampion' => $user->isChampion(),
            'stats' => $stats->forUser($user),
        ]);
    }
}
