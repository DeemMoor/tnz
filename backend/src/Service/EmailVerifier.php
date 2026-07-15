<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Генерация токена подтверждения email и отправка письма со ссылкой.
 * Сам flush делает вызывающий код — сервис только меняет состояние сущности.
 */
final class EmailVerifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
        private readonly string $publicUrl,
    ) {
    }

    /**
     * Выдать пользователю новый токен, пометить email неподтверждённым
     * и отправить письмо со ссылкой подтверждения.
     */
    public function sendVerification(User $user): void
    {
        $email = $user->getEmail();
        if ($email === null) {
            throw new \LogicException('Нельзя отправить подтверждение: email не задан.');
        }

        $token = bin2hex(random_bytes(32));
        $user->setEmailVerificationToken($token);
        $user->setEmailVerified(false);

        // Ссылка на эндпоинт подтверждения от заданной базы (APP_PUBLIC_URL),
        // а не от хоста запроса — иначе за прокси получится неверный адрес.
        $path = $this->urlGenerator->generate('api_verify_email', ['token' => $token]);
        $link = rtrim($this->publicUrl, '/') . $path;

        // Локально MAILER_DSN=null — письмо никуда не уходит, поэтому дублируем
        // ссылку в лог, чтобы можно было проверить флоу в деве.
        $this->logger->info('Ссылка подтверждения email', ['email' => $email, 'link' => $link]);

        $message = (new Email())
            ->from(new Address($this->mailerFrom, 'Теннис на Новой Земле'))
            ->to($email)
            ->subject('Подтверждение email')
            ->text("Здравствуйте!\n\nПодтвердите ваш email, перейдя по ссылке:\n{$link}\n\nЕсли вы не регистрировались — просто проигнорируйте это письмо.")
            ->html("<p>Здравствуйте!</p><p>Подтвердите ваш email, перейдя по ссылке:</p><p><a href=\"{$link}\">Подтвердить email</a></p><p>Если вы не регистрировались — просто проигнорируйте это письмо.</p>");

        $this->mailer->send($message);
    }
}
