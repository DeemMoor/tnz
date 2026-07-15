<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Exception\RegistrationException;
use App\Repository\BracketMatchRepository;
use App\Service\DrawService;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Жеребьёвка: сплит по столам, размер сетки, автопроходы (bye) и гварды.
 */
final class DrawServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DrawService $draw;
    private RegistrationService $registration;
    private BracketMatchRepository $matches;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->draw = $c->get(DrawService::class);
        $this->registration = $c->get(RegistrationService::class);
        $this->matches = $c->get(BracketMatchRepository::class);
    }

    private function tournamentWithPlayers(int $n, TournamentStatus $status = TournamentStatus::Checkin): Tournament
    {
        $t = new Tournament();
        $t->setName('Draw');
        $t->setDate(new \DateTimeImmutable('2026-07-19'));
        $t->setStatus($status);
        $this->em->persist($t);
        $this->em->flush();

        for ($i = 1; $i <= $n; $i++) {
            $u = new User();
            $u->setPhone('7922' . str_pad((string) $i, 7, '0', \STR_PAD_LEFT));
            $u->setName('P' . $i);
            $u->setPassword('hash');
            $this->em->persist($u);
            $this->em->flush();
            $this->registration->register($t, $u, ignoreSchedule: true);
        }

        return $t;
    }

    /**
     * @return list<BracketMatch>
     */
    private function tableRound(Tournament $t, int $table, int $round): array
    {
        return array_values(array_filter(
            $this->matches->findByTournamentOrdered($t),
            static fn (BracketMatch $m) => $m->getTableNumber() === $table && $m->getRound() === $round,
        ));
    }

    public function testFull32SplitsEvenlyIntoTwoFullBrackets(): void
    {
        $t = $this->tournamentWithPlayers(32);
        $res = $this->draw->draw($t);

        self::assertSame(['table1' => 16, 'table2' => 16], $res);
        self::assertSame(TournamentStatus::Drawn, $t->getStatus());
        // Полная сетка на 16 = 15 матчей на стол, 30 всего.
        self::assertCount(30, $this->matches->findByTournamentOrdered($t));
        // Первый тур каждого стола — 8 матчей, все с двумя игроками (байев нет).
        foreach ([1, 2] as $table) {
            $r1 = $this->tableRound($t, $table, 1);
            self::assertCount(8, $r1);
            foreach ($r1 as $m) {
                self::assertTrue($m->isReady(), 'В полной сетке байев быть не должно');
            }
        }
    }

    public function testTwentyFillsTable1To16AndTable2With4(): void
    {
        $t = $this->tournamentWithPlayers(20);
        $res = $this->draw->draw($t);

        self::assertSame(['table1' => 16, 'table2' => 4], $res);
        // Стол 1: 15 матчей; стол 2 (4 игрока): 3 матча.
        self::assertCount(8, $this->tableRound($t, 1, 1));
        self::assertCount(2, $this->tableRound($t, 2, 1)); // 4 игрока → 2 полуфинала
        self::assertCount(1, $this->tableRound($t, 2, 2)); // финал стола 2
    }

    public function testTwelveUsesByesResolvedIntoNextRound(): void
    {
        $t = $this->tournamentWithPlayers(12);
        $res = $this->draw->draw($t);

        self::assertSame(['table1' => 12, 'table2' => 0], $res);

        // Сетка 16: тур1 = 8 матчей, из них 4 реальных + 4 автопрохода.
        $r1 = $this->tableRound($t, 1, 1);
        self::assertCount(8, $r1);
        $done = array_filter($r1, static fn (BracketMatch $m) => $m->getStatus() === MatchStatus::Done);
        self::assertCount(4, $done, '12 игроков → 4 автопрохода');

        // Ни один матч тура 1 не пустой (у каждого хотя бы player1).
        foreach ($r1 as $m) {
            self::assertNotNull($m->getPlayer1());
        }

        // Победители автопроходов уже стоят во втором туре.
        $r2 = $this->tableRound($t, 1, 2);
        $filledSlots = array_filter($r2, static fn (BracketMatch $m) => $m->getPlayer1() !== null || $m->getPlayer2() !== null);
        self::assertNotEmpty($filledSlots);
    }

    public function testDrawRequiresCheckinStatus(): void
    {
        $t = $this->tournamentWithPlayers(4, TournamentStatus::Registration);
        $this->expectException(RegistrationException::class);
        $this->draw->draw($t);
    }

    public function testCannotDrawTwice(): void
    {
        $t = $this->tournamentWithPlayers(8);
        $this->draw->draw($t);

        $this->expectExceptionMessage('уже проведена');
        $this->draw->draw($t);
    }
}
