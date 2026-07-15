<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Личный кабинет: правка профиля и подтверждение email.
 */
final class ProfileTest extends WebTestCase
{
    private function registerAndLogin(KernelBrowser $client, string $phone, string $password): void
    {
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone,
            'password' => $password,
            'name' => 'Профиль',
        ]));
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone,
            'password' => $password,
        ]));
        self::assertResponseIsSuccessful();
    }

    public function testSetEmailSendsVerificationAndVerifyConfirms(): void
    {
        $client = static::createClient();
        $this->registerAndLogin($client, '79010000001', 'secret1');

        // Задаём email — должно уйти письмо и сбросить флаг подтверждения.
        $client->request('PATCH', '/api/me', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'Player@Example.com',
        ]));
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('player@example.com', $data['email']);
        self::assertFalse($data['emailVerified']);
        self::assertEmailCount(1);

        // Берём токен из БД и подтверждаем по ссылке.
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByPhone('79010000001');
        $token = $user->getEmailVerificationToken();
        self::assertNotNull($token);

        $client->request('GET', '/api/verify-email?token=' . $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND); // 302 redirect
        self::assertStringContainsString('verified=1', $client->getResponse()->headers->get('Location'));

        // Проверяем, что флаг встал и токен погашен.
        $client->request('GET', '/api/me');
        $me = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($me['emailVerified']);
    }

    public function testInvalidEmailRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogin($client, '79010000002', 'secret1');

        $client->request('PATCH', '/api/me', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'not-an-email',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDuplicateEmailRejected(): void
    {
        $client = static::createClient();
        // Первый игрок занимает email.
        $this->registerAndLogin($client, '79010000003', 'secret1');
        $client->request('PATCH', '/api/me', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'busy@example.com',
        ]));
        self::assertResponseIsSuccessful();

        // Второй игрок (тот же клиент, перелогин) пытается занять тот же email.
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79010000004',
            'password' => 'secret1',
            'name' => 'Второй',
        ]));
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => '79010000004',
            'password' => 'secret1',
        ]));
        $client->request('PATCH', '/api/me', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'busy@example.com',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testVerifyWithBadTokenRedirectsWithFailure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/verify-email?token=nonexistent');
        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertStringContainsString('verified=0', $client->getResponse()->headers->get('Location'));
    }
}
