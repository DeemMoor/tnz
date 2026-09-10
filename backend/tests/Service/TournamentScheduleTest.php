<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tournament;
use App\Service\TournamentSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Нумерация турниров от первого (#1 = 15.03.2026, еженедельно).
 */
final class TournamentScheduleTest extends TestCase
{
    private function schedule(): TournamentSchedule
    {
        return new TournamentSchedule(new MockClock('2026-07-19 12:00:00'));
    }

    private function tournamentOn(string $date): Tournament
    {
        $t = new Tournament();
        $t->setDate(new \DateTimeImmutable($date));

        return $t;
    }

    public function testFirstTournamentIsNumberOne(): void
    {
        self::assertSame(1, $this->schedule()->number($this->tournamentOn('2026-03-15')));
    }

    public function testJuly19IsNumber19(): void
    {
        self::assertSame(19, $this->schedule()->number($this->tournamentOn('2026-07-19')));
    }

    public function testNextWeekIncrements(): void
    {
        self::assertSame(2, $this->schedule()->number($this->tournamentOn('2026-03-22')));
        self::assertSame(3, $this->schedule()->number($this->tournamentOn('2026-03-29')));
    }

    public function testRegistrationOpensThursdayAtFourByDefault(): void
    {
        $opens = $this->schedule()->registrationOpensAt($this->tournamentOn('2026-09-13'));

        self::assertSame('2026-09-10 16:00', $opens->format('Y-m-d H:i'));
    }

    public function testManualTimeOverridesDefault(): void
    {
        $tournament = $this->tournamentOn('2026-09-13');
        $tournament->setRegistrationOpensAt(new \DateTimeImmutable('2026-09-10 10:00'));

        $opens = $this->schedule()->registrationOpensAt($tournament);

        self::assertSame('2026-09-10 10:00', $opens->format('Y-m-d H:i'));
    }

    public function testRegistrationIsClosedBeforeDefaultTimeButOpenAfterManualOpening(): void
    {
        $tournament = $this->tournamentOn('2026-09-13');
        $now = new \DateTimeImmutable('2026-09-10 15:10');

        // По умолчанию в 15:10 четверга запись ещё закрыта — откроется в 16:00.
        self::assertFalse($this->schedule()->isRegistrationOpen($tournament, $now));

        // Админ нажал «Открыть запись сейчас» — время открытия стало текущим.
        $tournament->setRegistrationOpensAt($now);

        self::assertTrue($this->schedule()->isRegistrationOpen($tournament, $now));
    }
}
