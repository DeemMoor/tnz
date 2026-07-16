<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\RegistrationException;
use App\Repository\UserRepository;
use App\Service\AvatarUploader;
use App\Service\EmailVerifier;
use App\Service\PlayerHistoryService;
use App\Service\UserPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        UserPresenter $presenter,
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

        if (\array_key_exists('nickname', $data)) {
            $nick = \is_string($data['nickname']) ? trim($data['nickname']) : '';
            if (mb_strlen($nick) > 50) {
                $errors['nickname'] = 'Ник слишком длинный (до 50 символов)';
            } else {
                $user->setNickname($nick !== '' ? $nick : null);
            }
        }

        if (\array_key_exists('telegram', $data)) {
            $tg = \is_string($data['telegram']) ? trim($data['telegram']) : '';
            // Нормализуем: убираем ведущий @ и пробелы, храним без @.
            $tg = ltrim($tg, '@');
            if (mb_strlen($tg) > 100) {
                $errors['telegram'] = 'Слишком длинное значение';
            } else {
                $user->setTelegram($tg !== '' ? $tg : null);
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

        return $this->json($presenter->view($user));
    }

    /**
     * POST /api/me/avatar — загрузить аватарку (multipart, поле "avatar").
     */
    #[Route('/api/me/avatar', name: 'api_me_avatar', methods: ['POST'])]
    public function uploadAvatar(
        Request $request,
        #[CurrentUser] User $user,
        AvatarUploader $uploader,
        UserPresenter $presenter,
        EntityManagerInterface $em,
    ): JsonResponse {
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Файл не передан'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $filename = $uploader->upload($user, $file);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        $user->setAvatarPath($filename);
        $em->flush();

        return $this->json($presenter->view($user));
    }

    /**
     * DELETE /api/me/avatar — удалить аватарку (вернуться к букве).
     */
    #[Route('/api/me/avatar', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(
        #[CurrentUser] User $user,
        AvatarUploader $uploader,
        UserPresenter $presenter,
        EntityManagerInterface $em,
    ): JsonResponse {
        $uploader->deleteCurrent($user);
        $user->setAvatarPath(null);
        $em->flush();

        return $this->json($presenter->view($user));
    }

    /**
     * POST /api/me/password — сменить пароль.
     * Тело: { currentPassword, newPassword }. Нужен текущий пароль (подтверждение).
     */
    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] User $user,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $current = \is_string($data['currentPassword'] ?? null) ? $data['currentPassword'] : '';
        $new = \is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';

        if (!$hasher->isPasswordValid($user, $current)) {
            return $this->json(['error' => 'Текущий пароль неверный'], Response::HTTP_FORBIDDEN);
        }
        if (mb_strlen($new) < 6) {
            return $this->json(['error' => 'Новый пароль минимум 6 символов'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        return $this->json(['status' => 'ok']);
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
}
