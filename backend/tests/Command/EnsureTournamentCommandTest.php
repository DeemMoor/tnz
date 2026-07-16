<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Автосоздание турнира на ближайшее воскресенье.
 * Часы в тестах зафиксированы на 2026-07-19 (см. services.yaml when@test) —
 * это воскресенье, значит ближайшее вс = сам этот день.
 */
final class EnsureTournamentCommandTest extends KernelTestCase
{
    public function testCreatesTournamentForUpcomingSunday(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:ensure-tournament'));

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $repo = static::getContainer()->get(TournamentRepository::class);
        $created = $repo->findOneBy(['date' => new \DateTimeImmutable('2026-07-19')]);
        self::assertNotNull($created, 'Турнир на ближайшее воскресенье должен быть создан');
        self::assertSame('registration', $created->getStatus()->value);
    }

    public function testIsIdempotent(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:ensure-tournament'));

        $tester->execute([]);
        $tester->execute([]); // второй раз — не должно создать дубль

        $repo = static::getContainer()->get(TournamentRepository::class);
        $count = \count($repo->findBy(['date' => new \DateTimeImmutable('2026-07-19')]));
        self::assertSame(1, $count, 'Дубль турнира на ту же дату не создаётся');
    }
}
