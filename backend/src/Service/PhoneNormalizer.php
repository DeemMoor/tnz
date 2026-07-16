<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Приводит телефон к единому виду 79XXXXXXXXX (11 цифр), чтобы уникальность
 * работала независимо от формата ввода (+7, 8, скобки...).
 *
 * Российские мобильные — всегда +7 9XX XXX XX XX (после кода страны идёт 9),
 * поэтому валидными считаем только номера, начинающиеся на «79».
 */
final class PhoneNormalizer
{
    /**
     * @return string|null нормализованный номер или null, если это не похоже на РФ-мобильный
     */
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (\strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }

        // 9XXXXXXXXX (10 цифр без кода страны) -> 79XXXXXXXXX
        if (\strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        // Валиден только российский мобильный: 11 цифр, начинается на 79.
        if (\strlen($digits) === 11 && str_starts_with($digits, '79')) {
            return $digits;
        }

        return null;
    }
}
