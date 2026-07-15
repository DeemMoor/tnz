<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Статус матча в сетке.
 */
enum MatchStatus: string
{
    case Pending = 'pending'; // ещё не сыгран (или ждёт соперников)
    case Done = 'done';       // сыгран, есть победитель
}
