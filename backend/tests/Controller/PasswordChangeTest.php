<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Смена пароля в личном кабинете.
 */
final class PasswordChangeTest extends WebTestCase
{
    private function registerAndLogin(KernelBrowser $client, string $phone, string $password): void
    {
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone, 'password' => $password, 'name' => 'Пароль Тест',
        ]));
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone, 'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }

    public function testChangePasswordThenLoginWithNew(): void
    {
        $client = static::createClient();
        $this->registerAndLogin($client, '79015550001', 'oldpass1');

        $client->request('POST', '/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'currentPassword' => 'oldpass1',
            'newPassword' => 'newpass2',
        ]));
        self::assertResponseIsSuccessful();

        // Старый пароль больше не подходит, новый — работает.
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79015550001', 'password' => 'oldpass1',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79015550001', 'password' => 'newpass2',
        ]));
        self::assertResponseIsSuccessful();
    }

    public function testWrongCurrentPasswordRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogin($client, '79015550002', 'oldpass1');

        $client->request('POST', '/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'currentPassword' => 'wrongone',
            'newPassword' => 'newpass2',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testShortNewPasswordRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogin($client, '79015550003', 'oldpass1');

        $client->request('POST', '/api/me/password', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'currentPassword' => 'oldpass1',
            'newPassword' => '123',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
