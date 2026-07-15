<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use App\Service\PlayerHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Личный кабинет: правка профиля (имя, email) и повторная отправка письма.
 */
final class ProfileController extends AbstractController
{
    /**
     * PATCH /api/me  { "name"?: string, "email"?: string|null }
     * Меняет имя и/или email. При новом email — шлёт письмо с подтверждением
     * и сбрасывает флаг подтверждения.
     */
    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        #[CurrentUser] User $user,
        UserRepository $users,
        EmailVerifier $emailVerifier,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $errors = [];
        $emailChanged = false;

        if (\array_key_exists('name', $data)) {
            $name = \is_string($data['name']) ? trim($data['name']) : '';
            if ($name === '') {
                $errors['name'] = 'Имя не может быть пустым';
            } else {
                $user->setName($name);
            }
        }

        if (\array_key_exists('rttfRating', $data)) {
            $rating = $data['rttfRating'];
            if ($rating === null || $rating === '') {
                $user->setRttfRating(null); // «нет рейтинга»
            } elseif ((\is_int($rating) || (\is_string($rating) && ctype_digit($rating))) && (int) $rating >= 0) {
                $user->setRttfRating((int) $rating);
            } else {
                $errors['rttfRating'] = 'Рейтинг должен быть неотрицательным числом';
            }
        }

        if (\array_key_exists('email', $data)) {
            $rawEmail = $data['email'];
            if ($rawEmail === null || $rawEmail === '') {
                // Удаление email из профиля.
                $user->setEmail(null);
                $user->setEmailVerified(false);
                $user->setEmailVerificationToken(null);
            } else {
                $email = \is_string($rawEmail) ? mb_strtolower(trim($rawEmail)) : '';
                if (filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
                    $errors['email'] = 'Некорректный email';
                } elseif ($email !== $user->getEmail()) {
                    $owner = $users->findOneByEmail($email);
                    if ($owner !== null && $owner->getId() !== $user->getId()) {
                        $errors['email'] = 'Этот email уже используется';
                    } else {
                        $user->setEmail($email);
                        $emailChanged = true;
                    }
                }
            }
        }

        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Письмо шлём до flush, но сам flush фиксирует и токен, и email разом.
        if ($emailChanged) {
            $emailVerifier->sendVerification($user);
        }

        $em->flush();

        return $this->json($this->userView($user));
    }

    /**
     * POST /api/me/resend-verification — отправить письмо подтверждения повторно.
     */
    #[Route('/api/me/resend-verification', name: 'api_me_resend_verification', methods: ['POST'])]
    public function resend(
        #[CurrentUser] User $user,
        EmailVerifier $emailVerifier,
        EntityManagerInterface $em,
    ): JsonResponse {
        if ($user->getEmail() === null) {
            return $this->json(['error' => 'Email не указан'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($user->isEmailVerified()) {
            return $this->json(['error' => 'Email уже подтверждён'], Response::HTTP_CONFLICT);
        }

        $emailVerifier->sendVerification($user);
        $em->flush();

        return $this->json(['status' => 'sent']);
    }

    /**
     * GET /api/me/tournaments — история выступлений игрока по турнирам.
     */
    #[Route('/api/me/tournaments', name: 'api_me_tournaments', methods: ['GET'])]
    public function myTournaments(
        #[CurrentUser] User $user,
        PlayerHistoryService $history,
    ): JsonResponse {
        return $this->json(['tournaments' => $history->forUser($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userView(User $user): array
    {
        return [
            'id' => $user->getId(),
            'phone' => $user->getPhone(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'emailVerified' => $user->isEmailVerified(),
            'rttfRating' => $user->getRttfRating(),
            'roles' => $user->getRoles(),
            'isChampion' => $user->isChampion(),
        ];
    }
}
