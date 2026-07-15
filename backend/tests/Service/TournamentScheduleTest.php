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
}
