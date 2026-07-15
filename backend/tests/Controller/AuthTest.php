<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверяет полный цикл аутентификации: регистрация, дубль, валидация,
 * вход/выход и доступ к /api/me. Каждый тест изолирован транзакцией
 * (dama/doctrine-test-bundle), так что данные не протекают между тестами.
 */
final class AuthTest extends WebTestCase
{
    private function register(KernelBrowser $client, string $phone, string $password, string $name): void
    {
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone,
            'password' => $password,
            'name' => $name,
        ]));
    }

    public function testRegisterNormalizesPhoneAndCreatesUser(): void
    {
        $client = static::createClient();
        // Ввод «человеческим» форматом — должен нормализоваться в 7XXXXXXXXXX.
        $this->register($client, '+7 (900) 111-22-33', 'secret1', 'Игрок');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('79001112233', $data['phone']);
        self::assertSame('Игрок', $data['name']);
    }

    public function testRegisterDuplicatePhoneConflicts(): void
    {
        $client = static::createClient();
        $this->register($client, '79002223344', 'secret1', 'Первый');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Тот же номер в другом формате (8...) — считается тем же телефоном.
        $this->register($client, '89002223344', 'secret1', 'Второй');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testRegisterValidationErrors(): void
    {
        $client = static::createClient();
        $this->register($client, '123', 'x', '');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('phone', $data['errors']);
        self::assertArrayHasKey('password', $data['errors']);
        self::assertArrayHasKey('name', $data['errors']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginThenMeReturnsUser(): void
    {
        $client = static::createClient();
        $this->register($client, '79003334455', 'secret1', 'Логинер');

        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79003334455',
            'password' => 'secret1',
        ]));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('79003334455', $data['phone']);
        self::assertContains('ROLE_USER', $data['roles']);
    }

    public function testLoginWorksWithAnyPhoneFormat(): void
    {
        $client = static::createClient();
        // Зарегистрировались в «человеческом» формате.
        $this->register($client, '+7 999 111 22 33', 'secret1', 'Формат');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Входим тем же номером, но в формате 8XXXXXXXXXX — должно сработать.
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '89991112233',
            'password' => 'secret1',
        ]));
        self::assertResponseIsSuccessful();
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $client = static::createClient();
        $this->register($client, '79004445566', 'secret1', 'Ошибка');

        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79004445566',
            'password' => 'wrongpass',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
