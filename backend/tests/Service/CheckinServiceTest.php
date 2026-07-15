<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Repository\TournamentEntryRepository;
use App\Repository\UserRepository;
use App\Service\CheckinService;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Чекин, walk-in и закрытие чекина (сброс но-шоу + добор из очереди).
 * Часы в тестах: 2026-07-19 14:05 (внутри окна чекина турнира на 2026-07-19).
 */
final class CheckinServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CheckinService $checkin;
    private RegistrationService $registration;
    private TournamentEntryRepository $entries;
    private UserRepository $users;
    private \DateTimeImmutable $regNow;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->checkin = $c->get(CheckinService::class);
        $this->registration = $c->get(RegistrationService::class);
        $this->entries = $c->get(TournamentEntryRepository::class);
        $this->users = $c->get(UserRepository::class);
        $this->regNow = new \DateTimeImmutable('2026-07-19 10:00:00'); // окно регистрации ещё открыто
    }

    private function makeTournament(string $date = '2026-07-19'): Tournament
    {
        $t = new Tournament();
        $t->setName('Тест');
        $t->setDate(new \DateTimeImmutable($date));
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    private function makeUser(int $n): User
    {
        $u = new User();
        $u->setPhone('7911000' . str_pad((string) $n, 4, '0', \STR_PAD_LEFT));
        $u->setName('Игрок ' . $n);
        $u->setPassword('hash');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    public function testPlayerCheckinInWindow(): void
    {
        $t = $this->makeTournament();
        $u = $this->makeUser(1);
        $this->registration->register($t, $u, $this->regNow);

        // Часы (MockClock) внутри окна чекина — self-checkin проходит.
        $entry = $this->checkin->checkIn($t, $u);
        self::assertTrue($entry->isCheckedIn());
    }

    public function testPlayerCheckinOutsideWindowFails(): void
    {
        // Турнир в будущем — окно чекина ещё не наступило.
        $t = $this->makeTournament('2026-08-16');
        $u = $this->makeUser(2);
        // Регистрация тоже закрыта по времени, поэтому ставим напрямую (ignoreSchedule).
        $this->registration->register($t, $u, ignoreSchedule: true);

        $this->expectExceptionMessage('Чекин сейчас закрыт');
        $this->checkin->checkIn($t, $u);
    }

    public function testWalkInCreatesUserAndChecksIn(): void
    {
        $t = $this->makeTournament();

        $entry = $this->checkin->walkIn($t, '+7 (912) 000-11-22', 'Пришёл сам');

        self::assertSame(EntryStatus::Registered, $entry->getStatus());
        self::assertTrue($entry->isCheckedIn());
        self::assertNotNull($this->users->findOneByPhone('79120001122'));
    }

    public function testCloseCheckinDropsNoShowsAndPromotesQueue(): void
    {
        $t = $this->makeTournament();

        // 32 в основе + 2 в очереди.
        $main = [];
        for ($i = 1; $i <= Tournament::CAPACITY + 2; $i++) {
            $u = $this->makeUser($i);
            $this->registration->register($t, $u, $this->regNow);
            if ($i <= Tournament::CAPACITY) {
                $main[$i] = $u;
            }
        }

        // Отмечаем всех из основы, кроме двоих (номера 5 и 10 — но-шоу).
        foreach ($main as $i => $u) {
            if ($i !== 5 && $i !== 10) {
                $this->checkin->checkIn($t, $u, byAdmin: true);
            }
        }

        $result = $this->checkin->closeCheckin($t);

        self::assertSame(2, $result['dropped']);
        self::assertSame(2, $result['promoted']);
        // После обработки основа снова заполнена до 32.
        self::assertSame(Tournament::CAPACITY, $this->entries->countByStatus($t, EntryStatus::Registered));
        self::assertSame(0, $this->entries->countByStatus($t, EntryStatus::Waitlisted));
        // Но-шоу выбыли.
        $entry5 = $this->entries->findOneByTournamentAndUser($t, $main[5]);
        self::assertSame(EntryStatus::Dropped, $entry5->getStatus());
    }
}
