<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Стадия турнира. Двигается вперёд по мере проведения.
 */
enum TournamentStatus: string
{
    case Draft = 'draft';           // черновик, ещё не анонсирован
    case Registration = 'registration'; // идёт запись игроков
    case Checkin = 'checkin';       // день турнира, окно чекина / после закрытия чекина
    case Drawn = 'drawn';           // жеребьёвка проведена, сетка построена
    case InProgress = 'in_progress'; // идут игры
    case Finished = 'finished';     // завершён
}
