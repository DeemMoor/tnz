<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationLog;
use App\Entity\Tournament;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Email-рассылки игрокам по событиям. Шлёт всем, у кого указан email,
 * устойчиво к сбоям SMTP (одна ошибка не рушит всю рассылку), и пишет лог.
 */
final class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $users,
        private readonly TournamentSchedule $schedule,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
        private readonly string $publicUrl,
    ) {
    }

    /**
     * Анонс открытия регистрации на турнир — всем игрокам с email.
     * Возвращает число успешно отправленных писем.
     */
    public function announceRegistrationOpen(Tournament $tournament): int
    {
        $number = $this->schedule->number($tournament);
        $date = $tournament->getDate()->format('d.m.Y');
        $subject = "Открыта регистрация на турнир #{$number}";

        $link = rtrim($this->publicUrl, '/') . '/';
        $text = "Привет!\n\n"
            . "Открыта регистрация на турнир #{$number} ({$date}).\n"
            . "Успей записаться — первые 32 попадают в сетку, остальные в очередь.\n\n"
            . "Записаться: {$link}\n\n"
            . 'Теннис на Новой Земле';

        $sent = $this->broadcast($subject, $text);

        $log = new NotificationLog('registration_open', $subject);
        $log->setTournament($tournament);
        $log->setRecipientCount($sent);
        $this->em->persist($log);
        $this->em->flush();

        return $sent;
    }

    /**
     * Разослать письмо всем игрокам с непустым email. Ошибки на отдельных
     * адресах логируются, но не прерывают рассылку.
     */
    private function broadcast(string $subject, string $text): int
    {
        $sent = 0;
        foreach ($this->users->findAllWithEmail() as $user) {
            $email = $user->getEmail();
            if ($email === null || $email === '') {
                continue;
            }
            try {
                $message = (new Email())
                    ->from(new Address($this->mailerFrom, 'Теннис на Новой Земле'))
                    ->to($email)
                    ->subject($subject)
                    ->text($text);
                $this->mailer->send($message);
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->warning('Не удалось отправить письмо', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
