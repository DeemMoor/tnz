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
use App\Service\AdvanceService;
use App\Service\DrawService;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Продвижение по сетке, чемпион стола и завершение турнира.
 */
final class AdvanceServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AdvanceService $advance;
    private DrawService $draw;
    private RegistrationService $registration;
    private BracketMatchRepository $matches;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->advance = $c->get(AdvanceService::class);
        $this->draw = $c->get(DrawService::class);
        $this->registration = $c->get(RegistrationService::class);
        $this->matches = $c->get(BracketMatchRepository::class);
    }

    private function drawnTournament(int $n): Tournament
    {
        $t = new Tournament();
        $t->setName('Adv');
        $t->setDate(new \DateTimeImmutable('2026-07-19'));
        $t->setStatus(TournamentStatus::Checkin);
        $this->em->persist($t);
        $this->em->flush();

        for ($i = 1; $i <= $n; $i++) {
            $u = new User();
            $u->setPhone('7944' . str_pad((string) $i, 7, '0', \STR_PAD_LEFT));
            $u->setName('A' . $i);
            $u->setPassword('hash');
            $this->em->persist($u);
            $this->em->flush();
            $this->registration->register($t, $u, ignoreSchedule: true);
        }
        $this->draw->draw($t);

        return $t;
    }

    /** Доиграть все готовые матчи указанного стола (победитель — player1). */
    private function playTable(Tournament $t, int $table): void
    {
        do {
            $progressed = false;
            foreach ($this->matches->findByTournamentOrdered($t) as $m) {
                if ($m->getTableNumber() === $table
                    && $m->getStatus() === MatchStatus::Pending
                    && $m->isReady()
                ) {
                    $this->advance->recordWinner($m, $m->getPlayer1(), byAdmin: true);
                    $progressed = true;
                }
            }
        } while ($progressed);
    }

    private function firstReady(Tournament $t): BracketMatch
    {
        foreach ($this->matches->findByTournamentOrdered($t) as $m) {
            if ($m->getStatus() === MatchStatus::Pending && $m->isReady()) {
                return $m;
            }
        }
        self::fail('Нет готовых матчей');
    }

    public function testSingleTableProducesChampionAndFinishes(): void
    {
        $t = $this->drawnTournament(4); // один стол, 4 игрока
        $this->playTable($t, 1);

        self::assertSame(TournamentStatus::Finished, $t->getStatus());

        // Ровно один чемпион = победитель финала стола.
        $final = $this->matches->findOneBySlot($t, 1, 2, 0);
        self::assertSame(MatchStatus::Done, $final->getStatus());
        self::assertTrue($final->getWinner()->isChampion());
    }

    public function testTwoTablesFinishOnlyWhenBothDone(): void
    {
        $t = $this->drawnTournament(18); // стол1=16, стол2=2

        // Доигрываем только стол 2 — турнир ещё не завершён.
        $this->playTable($t, 2);
        self::assertNotSame(TournamentStatus::Finished, $t->getStatus());

        // Доигрываем стол 1 — теперь завершён, и чемпионов двое.
        $this->playTable($t, 1);
        self::assertSame(TournamentStatus::Finished, $t->getStatus());

        $champions = array_filter(
            $this->em->getRepository(User::class)->findAll(),
            static fn (User $u) => $u->isChampion(),
        );
        self::assertCount(2, $champions);
    }

    public function testWinnerMustBeParticipant(): void
    {
        $t = $this->drawnTournament(4);
        $match = $this->firstReady($t);

        $stranger = new User();
        $stranger->setPhone('79449999999');
        $stranger->setName('Чужой');
        $stranger->setPassword('hash');
        $this->em->persist($stranger);
        $this->em->flush();

        $this->expectException(RegistrationException::class);
        $this->advance->recordWinner($match, $stranger, byAdmin: true);
    }

    public function testPlayerCannotOverwriteButAdminCan(): void
    {
        $t = $this->drawnTournament(4);
        $match = $this->firstReady($t);
        $p1 = $match->getPlayer1();
        $p2 = $match->getPlayer2();

        $this->advance->recordWinner($match, $p1, byAdmin: false);

        // Игрок не может переписать результат.
        try {
            $this->advance->recordWinner($match, $p2, byAdmin: false);
            self::fail('Ожидалось исключение');
        } catch (RegistrationException $e) {
            self::assertSame(409, $e->statusCode);
        }

        // Тот же победитель повторно — идемпотентно (без ошибки).
        $this->advance->recordWinner($match, $p1, byAdmin: false);

        // Админ может переотметить — победитель меняется и едет в следующий тур.
        $this->advance->recordWinner($match, $p2, byAdmin: true);
        self::assertSame($p2, $match->getWinner());

        $next = $this->matches->findOneBySlot($t, 1, 2, 0);
        $inNext = $next->getPlayer1() === $p2 || $next->getPlayer2() === $p2;
        self::assertTrue($inNext, 'Новый победитель должен стоять в финале');
        $oldStillThere = $next->getPlayer1() === $p1 || $next->getPlayer2() === $p1;
        self::assertFalse($oldStillThere, 'Старый победитель убран из финала');
    }
}
