<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичная карточка игрока: видна без входа, контактов не отдаёт.
 */
final class PlayerCardTest extends WebTestCase
{
    public function testCardIsPublicAndHidesContacts(): void
    {
        // Клиент никуда не логинится — значит запрос идёт от гостя.
        $client = static::createClient();

        $user = new User();
        $user->setPhone('79015550001');
        $user->setName('Карточкин Игрок');
        $user->setPassword('hash');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/players/' . $user->getId());
        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Карточкин Игрок', $data['name']);
        self::assertSame(0, $data['stats']['games']);
        self::assertNull($data['stats']['rank'], 'Без сыгранных матчей места в таблице нет');
        self::assertArrayNotHasKey('phone', $data);
        self::assertArrayNotHasKey('email', $data);
    }

    public function testUnknownPlayerGives404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/players/999999');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
