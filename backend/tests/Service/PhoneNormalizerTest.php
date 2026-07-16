<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Нормализация телефона: разные форматы → единый 79XXXXXXXXX, мусор → null.
 */
final class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PhoneNormalizer();
    }

    #[DataProvider('validCases')]
    public function testValid(string $input, string $expected): void
    {
        self::assertSame($expected, $this->normalizer->normalize($input));
    }

    public static function validCases(): iterable
    {
        yield 'плюс семь с пробелами' => ['+7 999 123-45-67', '79991234567'];
        yield 'восьмёрка' => ['89991234567', '79991234567'];
        yield 'семёрка' => ['79991234567', '79991234567'];
        yield 'десять цифр с девятки' => ['9991234567', '79991234567'];
        yield 'скобки' => ['+7 (912) 000-11-22', '79120001122'];
    }

    #[DataProvider('invalidCases')]
    public function testInvalidReturnsNull(string $input): void
    {
        self::assertNull($this->normalizer->normalize($input));
    }

    public static function invalidCases(): iterable
    {
        yield 'не мобильный (77...)' => ['77900000000'];
        yield 'городской (после 7 не 9)' => ['74951234567'];
        yield 'слишком коротко' => ['12345'];
        yield 'пусто' => [''];
        yield 'буквы' => ['телефон'];
    }
}
