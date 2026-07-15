<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use Psr\Clock\ClockInterface;

/**
 * Считает временные окна турнира от его даты (воскресенья).
 * Времена зашиты в код (по договорённости):
 *  - регистрация открывается в четверг перед турниром в 16:00;
 *  - окно чекина — в воскресенье 14:00–14:15.
 */
final class TournamentSchedule
{
    /** Дата первого турнира (#1). Нумерация считается от неё, шаг — неделя. */
    public const string FIRST_TOURNAMENT_DATE = '2026-03-15';

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Порядковый номер турнира от первого (#1 = FIRST_TOURNAMENT_DATE).
     * Турниры еженедельные, поэтому номер = число недель от якоря + 1.
     */
    public function number(Tournament $tournament): int
    {
        // Полдень, чтобы переход на летнее/зимнее время не сдвигал счёт дней.
        $anchor = new \DateTimeImmutable(self::FIRST_TOURNAMENT_DATE . ' 12:00');
        $date = $tournament->getDate()->setTime(12, 0);

        $days = (int) $anchor->diff($date)->days * ($date >= $anchor ? 1 : -1);

        return (int) round($days / 7) + 1;
    }

    /**
     * Регистрация открывается в четверг 16:00 (за 3 дня до воскресенья).
     */
    public function registrationOpensAt(Tournament $tournament): \DateTimeImmutable
    {
        return $tournament->getDate()->modify('-3 days')->setTime(16, 0);
    }

    public function checkinStartsAt(Tournament $tournament): \DateTimeImmutable
    {
        return $tournament->getDate()->setTime(14, 0);
    }

    public function checkinEndsAt(Tournament $tournament): \DateTimeImmutable
    {
        return $tournament->getDate()->setTime(14, 15);
    }

    /**
     * Регистрация открыта: от чт 16:00 и пока не закрылось окно чекина (14:15).
     */
    public function isRegistrationOpen(Tournament $tournament, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= $this->clock->now();

        return $now >= $this->registrationOpensAt($tournament)
            && $now < $this->checkinEndsAt($tournament);
    }

    /**
     * Идёт окно чекина: вс 14:00–14:15.
     */
    public function isCheckinOpen(Tournament $tournament, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= $this->clock->now();

        return $now >= $this->checkinStartsAt($tournament)
            && $now < $this->checkinEndsAt($tournament);
    }
}
