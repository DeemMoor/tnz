<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Вход, выход и «кто я». Сами login/logout обрабатывает firewall Symfony —
 * методы-заглушки нужны только чтобы у check_path/logout был зарегистрирован роут.
 */
final class SecurityController extends AbstractController
{
    /**
     * POST /api/login  { "phone": "...", "password": "..." }
     * json_login аутентифицирует и (без success-handler'а) передаёт управление
     * сюда — на успехе возвращаем текущего пользователя. При неверных данных
     * сюда не доходит: firewall сам отдаёт 401.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user, UserPresenter $presenter): JsonResponse
    {
        return $this->me($user, $presenter);
    }

    /**
     * POST /api/logout — обрабатывает firewall (logout).
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('Этот метод перехватывается logout-механизмом firewall.');
    }

    /**
     * GET /api/me — текущий залогиненный пользователь.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user, UserPresenter $presenter): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'Не авторизован'], 401);
        }

        return $this->json($presenter->view($user));
    }
}
