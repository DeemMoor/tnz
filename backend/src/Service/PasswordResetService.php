<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\RegistrationException;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Восстановление пароля по ссылке из письма. Работает только для игроков
 * с подтверждённым email — логин у нас по телефону, а SMS-подтверждений нет.
 * Сам flush делает вызывающий код — сервис только меняет состояние сущности.
 */
final class PasswordResetService
{
    private const int TOKEN_TTL_SECONDS = 3600; // 1 час

    public function __construct(
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
        private readonly string $publicUrl,
    ) {
    }

    /**
     * Запросить сброс пароля по телефону. Если аккаунта нет, или у него нет
     * подтверждённого email — молча ничего не делаем: контроллер всё равно
     * отвечает одинаково, чтобы не палить наличие/статус чужого аккаунта.
     */
    public function requestReset(string $phone): void
    {
        $user = $this->users->findOneByPhone($phone);
        if ($user === null || $user->getEmail() === null || !$user->isEmailVerified()) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetPasswordToken($token);
        $user->setResetPasswordTokenExpiresAt(
            $this->clock->now()->modify('+' . self::TOKEN_TTL_SECONDS . ' seconds'),
        );

        $link = rtrim($this->publicUrl, '/') . '/reset-password?token=' . $token;

        // Локально MAILER_DSN=null — письмо никуда не уходит, поэтому дублируем
        // ссылку в лог, чтобы можно было проверить флоу в деве.
        $this->logger->info('Ссылка сброса пароля', ['email' => $user->getEmail(), 'link' => $link]);

        $message = (new Email())
            ->from(new Address($this->mailerFrom, 'Теннис на Новой Земле'))
            ->to($user->getEmail())
            ->subject('Восстановление пароля')
            ->text(
                "Здравствуйте!\n\n"
                . "Запрошен сброс пароля. Перейдите по ссылке, чтобы задать новый (ссылка действует 1 час):\n{$link}\n\n"
                . 'Если это были не вы — просто проигнорируйте письмо, пароль не изменится.',
            )
            ->html(
                '<p>Здравствуйте!</p>'
                . '<p>Запрошен сброс пароля. Перейдите по ссылке, чтобы задать новый (ссылка действует 1 час):</p>'
                . "<p><a href=\"{$link}\">Сбросить пароль</a></p>"
                . '<p>Если это были не вы — просто проигнорируйте письмо, пароль не изменится.</p>',
            );

        $this->mailer->send($message);
    }

    /**
     * Проверить токен и задать новый пароль.
     *
     * @throws RegistrationException
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        $user = $token !== '' ? $this->users->findOneByResetPasswordToken($token) : null;
        if ($user === null) {
            throw new RegistrationException('Ссылка недействительна или уже использована', 404);
        }

        $expiresAt = $user->getResetPasswordTokenExpiresAt();
        if ($expiresAt === null || $expiresAt < $this->clock->now()) {
            throw new RegistrationException('Ссылка устарела — запросите сброс пароля ещё раз', 410);
        }

        if (mb_strlen($newPassword) < 6) {
            throw new RegistrationException('Пароль минимум 6 символов', 422);
        }

        $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);
    }
}
