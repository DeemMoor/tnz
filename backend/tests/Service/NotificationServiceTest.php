<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use App\Repository\NotificationLogRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * Рассылка анонса регистрации: письма всем с email + запись в лог.
 */
final class NotificationServiceTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    public function testAnnounceSendsToPlayersWithEmailAndLogs(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $service = $c->get(NotificationService::class);

        // Двое с email, один без.
        foreach ([['79030000001', 'a@example.com'], ['79030000002', 'b@example.com'], ['79030000003', null]] as $i => [$phone, $email]) {
            $u = new User();
            $u->setPhone($phone);
            $u->setName('Игрок ' . $i);
            $u->setPassword('hash');
            $u->setEmail($email);
            $em->persist($u);
        }

        $t = new Tournament();
        $t->setName('Тест');
        $t->setDate(new \DateTimeImmutable('2026-07-19'));
        $t->setStatus(TournamentStatus::Registration);
        $em->persist($t);
        $em->flush();

        $sent = $service->announceRegistrationOpen($t);

        self::assertSame(2, $sent, 'Письма уходят только тем, у кого есть email');
        self::assertEmailCount(2);

        // В логе появилась запись о рассылке.
        $logs = $c->get(NotificationLogRepository::class)->findAll();
        self::assertNotEmpty($logs);
        self::assertSame(2, $logs[0]->getRecipientCount());
    }
}
