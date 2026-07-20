<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\RegistrationException;
use App\Service\PasswordResetService;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Восстановление пароля по ссылке из письма. Публичные эндпоинты —
 * пользователь не залогинен (пароль как раз забыл).
 */
final class PasswordResetController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgot(
        Request $request,
        PhoneNormalizer $phoneNormalizer,
        PasswordResetService $reset,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $rawPhone = \is_string($data['phone'] ?? null) ? $data['phone'] : '';
        $phone = $phoneNormalizer->normalize($rawPhone);

        // Ответ всегда одинаковый, независимо от результата — не палим,
        // есть ли такой аккаунт и привязан ли к нему подтверждённый email.
        if ($phone !== null) {
            $reset->requestReset($phone);
            $em->flush();
        }

        return $this->json(['status' => 'ok']);
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function reset(Request $request, PasswordResetService $reset, EntityManagerInterface $em): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $token = \is_string($data['token'] ?? null) ? $data['token'] : '';
        $newPassword = \is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';

        try {
            $reset->resetPassword($token, $newPassword);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }
        $em->flush();

        return $this->json(['status' => 'ok']);
    }
}
