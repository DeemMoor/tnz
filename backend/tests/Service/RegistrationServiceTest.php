<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Repository\TournamentEntryRepository;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Логика основы/очереди ожидания и автоподъёма — без HTTP.
 */
final class RegistrationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RegistrationService $service;
    private TournamentEntryRepository $entries;
    private \DateTimeImmutable $openNow;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->service = $c->get(RegistrationService::class);
        $this->entries = $c->get(TournamentEntryRepository::class);
        // Внутри окна регистрации турнира с датой 2026-07-19.
        $this->openNow = new \DateTimeImmutable('2026-07-17 12:00:00');
    }

    private function makeTournament(): Tournament
    {
        $t = new Tournament();
        $t->setName('Тест');
        $t->setDate(new \DateTimeImmutable('2026-07-19'));
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    private function makeUser(int $n): User
    {
        $u = new User();
        $u->setPhone('7900000' . str_pad((string) $n, 4, '0', \STR_PAD_LEFT));
        $u->setName('Игрок ' . $n);
        $u->setPassword('hash');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    public function testFirst32RegisteredRestWaitlisted(): void
    {
        $t = $this->makeTournament();

        // Регистрируем на 2 больше вместимости.
        $users = [];
        for ($i = 1; $i <= Tournament::CAPACITY + 2; $i++) {
            $u = $this->makeUser($i);
            $users[$i] = $u;
            $this->service->register($t, $u, $this->openNow);
        }

        self::assertSame(Tournament::CAPACITY, $this->entries->countByStatus($t, EntryStatus::Registered));
        self::assertSame(2, $this->entries->countByStatus($t, EntryStatus::Waitlisted));

        // 33-й и 34-й — в очереди.
        $entry33 = $this->entries->findOneByTournamentAndUser($t, $users[33]);
        self::assertSame(EntryStatus::Waitlisted, $entry33->getStatus());
    }

    public function testUnregisterPromotesFirstWaitlisted(): void
    {
        $t = $this->makeTournament();

        $users = [];
        for ($i = 1; $i <= Tournament::CAPACITY + 1; $i++) {
            $u = $this->makeUser($i);
            $users[$i] = $u;
            $this->service->register($t, $u, $this->openNow);
        }

        // 33-й в очереди.
        $waitlisted = $this->entries->findOneByTournamentAndUser($t, $users[33]);
        self::assertSame(EntryStatus::Waitlisted, $waitlisted->getStatus());

        // Снимаем первого зарегистрированного — очередь должна подтянуться.
        $this->service->unregister($t, $users[1]);

        $this->em->refresh($waitlisted);
        self::assertSame(EntryStatus::Registered, $waitlisted->getStatus());
        self::assertSame(Tournament::CAPACITY, $this->entries->countByStatus($t, EntryStatus::Registered));
    }
}
