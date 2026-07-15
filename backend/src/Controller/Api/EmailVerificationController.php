<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Подтверждение email по ссылке из письма.
 * Публичный эндпоинт (открывается из почты, пользователь может быть не залогинен).
 */
final class EmailVerificationController extends AbstractController
{
    #[Route('/api/verify-email', name: 'api_verify_email', methods: ['GET'])]
    public function verify(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $token = $request->query->get('token', '');

        $user = $token !== '' ? $users->findOneByVerificationToken($token) : null;

        if ($user === null) {
            // Неверный/просроченный токен — ведём на профиль с признаком ошибки.
            return new RedirectResponse('/profile?verified=0');
        }

        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $em->flush();

        return new RedirectResponse('/profile?verified=1');
    }
}
