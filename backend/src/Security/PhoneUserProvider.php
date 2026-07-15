<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PhoneNormalizer;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Провайдер пользователей по телефону с нормализацией.
 * Благодаря этому вход работает независимо от формата ввода (+7…, 8…, 7…).
 *
 * @implements UserProviderInterface<User>
 */
final class PhoneUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Приводим введённый телефон к единому виду, потом ищем.
        $phone = $this->phoneNormalizer->normalize($identifier) ?? $identifier;
        $user = $this->users->findOneByPhone($phone);
        if ($user === null) {
            throw new UserNotFoundException(\sprintf('Пользователь с телефоном "%s" не найден.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // Перечитываем по id — стабильнее, чем по идентификатору-телефону.
        $refreshed = $user->getId() !== null ? $this->users->find($user->getId()) : null;
        if ($refreshed === null) {
            throw new UserNotFoundException('Пользователь больше не существует.');
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->users->upgradePassword($user, $newHashedPassword);
    }
}
