<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Приводит телефон к единому виду 7XXXXXXXXXX (11 цифр), чтобы уникальность
 * работала независимо от того, как пользователь ввёл номер (+7, 8, скобки...).
 */
final class PhoneNormalizer
{
    /**
     * @return string|null нормализованный номер или null, если это не похоже на РФ-номер
     */
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (\strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        // XXXXXXXXXX (10 цифр без кода страны) -> 7XXXXXXXXXX
        if (\strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (\strlen($digits) === 11 && str_starts_with($digits, '7')) {
            return $digits;
        }

        return null;
    }
}
