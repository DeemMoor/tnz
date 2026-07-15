<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Отказ в регистрации/снятии с понятной причиной и HTTP-кодом.
 * Контроллер превращает его в JSON-ответ.
 */
final class RegistrationException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }
}
