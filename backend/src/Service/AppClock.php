<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Clock\ClockInterface;

/**
 * Часы приложения. В обычном режиме — реальное время; но если задан
 * APP_FAKE_NOW (только для локальной отладки), возвращает фиксированный момент.
 * Удобно проверять сценарии, завязанные на день/время (окна регистрации, чекина).
 */
final class AppClock implements ClockInterface
{
    public function __construct(
        private readonly string $fakeNow = '',
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        $fake = trim($this->fakeNow);

        return $fake !== '' ? new \DateTimeImmutable($fake) : new \DateTimeImmutable();
    }
}
