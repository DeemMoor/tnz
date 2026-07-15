<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Tournament;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Гейты регистрации на турнир через HTTP. Часы в тестах зафиксированы
 * на 2026-07-17 12:00 (см. services.yaml when@test); окно подбираем датой турнира.
 */
final class TournamentApiTest extends WebTestCase
{
    private function makeTournament(EntityManagerInterface $em, string $date): int
    {
        $t = new Tournament();
        $t->setName('Турнир ' . $date);
        $t->setDate(new \DateTimeImmutable($date));
        $em->persist($t);
        $em->flush();

        return $t->getId();
    }

    private function registerAndLogin(KernelBrowser $client, string $phone): void
    {
        $client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone, 'password' => 'secret1', 'name' => 'Т',
        ]));
        $client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'phone' => $phone, 'password' => 'secret1',
        ]));
    }

    public function testRegisterWhenOpen(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Дата 2026-07-19: регистрация открыта в фикс-«сейчас» 2026-07-17.
        $id = $this->makeTournament($em, '2026-07-19');
        $this->registerAndLogin($client, '79020000001');

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('registered', $data['me']['status']);
        self::assertSame(1, $data['registeredCount']);
    }

    public function testRegisterWhenClosed(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Дата в будущем: регистрация ещё не открылась.
        $id = $this->makeTournament($em, '2026-08-16');
        $this->registerAndLogin($client, '79020000002');

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDuplicateRegister(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $id = $this->makeTournament($em, '2026-07-19');
        $this->registerAndLogin($client, '79020000003');

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testChampionCannotRegister(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $users = static::getContainer()->get(UserRepository::class);
        $id = $this->makeTournament($em, '2026-07-19');
        $this->registerAndLogin($client, '79020000004');

        $user = $users->findOneByPhone('79020000004');
        $user->setIsChampion(true);
        $em->flush();

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testHighRttfCannotRegister(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $id = $this->makeTournament($em, '2026-07-19');
        $this->registerAndLogin($client, '79020000005');

        $client->request('PATCH', '/api/me', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'rttfRating' => 300,
        ]));
        self::assertResponseIsSuccessful();

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnregister(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $id = $this->makeTournament($em, '2026-07-19');
        $this->registerAndLogin($client, '79020000006');

        $client->request('POST', "/api/tournaments/{$id}/register");
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('DELETE', "/api/tournaments/{$id}/registration");
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertNull($data['me']); // запись снята — статуса нет
        self::assertSame(0, $data['registeredCount']);
    }
}
