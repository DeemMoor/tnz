<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Единый JSON-вид пользователя для API (/api/me, login, обновление профиля).
 */
final class UserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function view(User $user): array
    {
        return [
            'id' => $user->getId(),
            'phone' => $user->getPhone(),
            'name' => $user->getName(),
            'nickname' => $user->getNickname(),
            'displayName' => $user->getDisplayName(),
            'telegram' => $user->getTelegram(),
            'avatarUrl' => $this->avatarUrl($user),
            'email' => $user->getEmail(),
            'emailVerified' => $user->isEmailVerified(),
            'rttfRating' => $user->getRttfRating(),
            'roles' => $user->getRoles(),
            'isChampion' => $user->isChampion(),
        ];
    }

    /**
     * Относительный URL аватарки (тот же origin) или null.
     */
    public function avatarUrl(User $user): ?string
    {
        $path = $user->getAvatarPath();

        return ($path !== null && $path !== '') ? '/uploads/avatars/' . $path : null;
    }
}
