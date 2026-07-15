<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Repository\BracketMatchRepository;
use App\Service\AdvanceService;
use App\Service\DrawService;
use App\Service\RegistrationService;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Таблица лидеров: очки = победы, байи не учитываются.
 */
final class StatsServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StatsService $stats;
    private DrawService $draw;
    private AdvanceService $advance;
    private RegistrationService $registration;
    private BracketMatchRepository $matches;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->stats = $c->get(StatsService::class);
        $this->draw = $c->get(DrawService::class);
        $this->advance = $c->get(AdvanceService::class);
        $this->registration = $c->get(RegistrationService::class);
        $this->matches = $c->get(BracketMatchRepository::class);
    }

    private function playedTournament(int $n): Tournament
    {
        $t = new Tournament();
        $t->setName('Stats');
        $t->setDate(new \DateTimeImmutable('2026-07-19'));
        $t->setStatus(TournamentStatus::Checkin);
        $this->em->persist($t);
        $this->em->flush();

        for ($i = 1; $i <= $n; $i++) {
            $u = new User();
            $u->setPhone('7988' . str_pad((string) $i, 7, '0', \STR_PAD_LEFT));
            $u->setName('S' . $i);
            $u->setPassword('hash');
            $this->em->persist($u);
            $this->em->flush();
            $this->registration->register($t, $u, ignoreSchedule: true);
        }
        $this->draw->draw($t);

        // Доигрываем все готовые матчи (победитель — player1).
        do {
            $progressed = false;
            foreach ($this->matches->findByTournamentOrdered($t) as $m) {
                if ($m->getStatus() === MatchStatus::Pending && $m->isReady()) {
                    $this->advance->recordWinner($m, $m->getPlayer1(), byAdmin: true);
                    $progressed = true;
                }
            }
        } while ($progressed);

        return $t;
    }

    public function testLeaderboardCountsWinsAsPoints(): void
    {
        $this->playedTournament(4); // 3 реальных матча (2 полуфинала + финал)

        $board = $this->stats->leaderboard();
        self::assertNotEmpty($board);

        $totalWins = array_sum(array_column($board, 'wins'));
        $totalGames = array_sum(array_column($board, 'games'));
        self::assertSame(3, $totalWins, 'Всего побед = числу сыгранных матчей');
        self::assertSame(6, $totalGames, 'Каждый матч — 2 участника');

        // Чемпион (лидер) выиграл полуфинал и финал → 2 очка, и стоит первым.
        self::assertSame(2, $board[0]['points']);
        self::assertSame($board[0]['wins'], $board[0]['points']); // очки = победы
        foreach ($board as $row) {
            self::assertSame($row['games'] - $row['wins'], $row['losses']);
        }
    }

    public function testByesAreNotCountedAsGames(): void
    {
        // 6 игроков в сетке на 8 → 2 автопрохода; реально играется 5 матчей.
        $this->playedTournament(6);

        $board = $this->stats->leaderboard();
        $totalWins = array_sum(array_column($board, 'wins'));
        self::assertSame(5, $totalWins, 'Байи (автопроходы) не считаются победами');
    }
}
