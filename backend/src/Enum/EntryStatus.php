<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Статус записи игрока на турнир.
 */
enum EntryStatus: string
{
    case Registered = 'registered';  // в основе (одно из 32 мест)
    case Waitlisted = 'waitlisted';  // в очереди ожидания
    case Cancelled = 'cancelled';    // сам снялся с регистрации
    case Dropped = 'dropped';        // сброшен как не пришедший (но-шоу) в 14:15
}
