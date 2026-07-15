<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Enum\TournamentStatus;
use App\Enum\MatchStatus;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Service\AdvanceService;
use App\Service\DrawService;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Демо-данные: два прошедших турнира (5 и 12 июля 2026) со сыгранными сетками.
 * Идемпотентна: турнир на уже существующую дату не создаётся повторно.
 *
 * Запуск: php bin/console app:seed-demo
 */
#[AsCommand(name: 'app:seed-demo', description: 'Накатить демо-турниры (5 и 12 июля)')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly TournamentRepository $tournaments,
        private readonly BracketMatchRepository $matches,
        private readonly RegistrationService $registration,
        private readonly DrawService $draw,
        private readonly AdvanceService $advance,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Игроки: короткий ключ => [телефон, «Фамилия Имя»].
        // Дима — существующий тестовый пользователь (имя не меняем).
        $people = [
            'Дима' => ['79991234567', 'Дима'],
            'Герман' => ['79000000002', 'Соколов Герман'],
            'Пётр' => ['79000000003', 'Морозов Пётр'],
            'Алексей' => ['79000000004', 'Волков Алексей'],
            'Сергей' => ['79000000005', 'Новиков Сергей'],
            'Иван' => ['79000000006', 'Фёдоров Иван'],
            'Николай' => ['79000000007', 'Козлов Николай'],
            'Андрей' => ['79000000008', 'Лебедев Андрей'],
            'Максим' => ['79000000009', 'Егоров Максим'],
        ];
        /** @var array<string, User> $u ключ => User */
        $u = [];
        foreach ($people as $key => [$phone, $fullName]) {
            $u[$key] = $this->getOrCreateUser($phone, $fullName);
        }

        // Турнир 12 июля: Дима доходит до 1/2 и проигрывает Герману; чемпион — Герман.
        $this->seedTournament(
            $io,
            '2026-07-12',
            ['Дима', 'Герман', 'Пётр', 'Алексей', 'Сергей', 'Иван', 'Николай', 'Андрей'],
            [
                [1, 0, 'Дима', 'Пётр', 'Дима'],
                [1, 1, 'Герман', 'Алексей', 'Герман'],
                [1, 2, 'Сергей', 'Иван', 'Сергей'],
                [1, 3, 'Николай', 'Андрей', 'Николай'],
                [2, 0, 'Дима', 'Герман', 'Герман'],
                [2, 1, 'Сергей', 'Николай', 'Сергей'],
                [3, 0, 'Герман', 'Сергей', 'Герман'],
            ],
            'Герман',
            $u,
        );

        // Турнир 5 июля: Дима вылетает в 1/4; чемпион — Максим.
        $this->seedTournament(
            $io,
            '2026-07-05',
            ['Дима', 'Максим', 'Пётр', 'Алексей', 'Сергей', 'Иван', 'Николай', 'Андрей'],
            [
                [1, 0, 'Дима', 'Максим', 'Максим'],
                [1, 1, 'Пётр', 'Алексей', 'Пётр'],
                [1, 2, 'Сергей', 'Иван', 'Сергей'],
                [1, 3, 'Николай', 'Андрей', 'Николай'],
                [2, 0, 'Максим', 'Пётр', 'Максим'],
                [2, 1, 'Сергей', 'Николай', 'Сергей'],
                [3, 0, 'Максим', 'Сергей', 'Максим'],
            ],
            'Максим',
            $u,
        );

        // Полный турнир на 32 игрока (два стола по 16), сыгранный до конца —
        // чтобы оценить вёрстку сетки. Дата 28 июня 2026 (воскресенье, #16).
        $this->seedFullTournament($io, '2026-06-28');

        $this->em->flush();
        $io->success('Демо-данные готовы.');

        return Command::SUCCESS;
    }

    /**
     * 32 игрока → жеребьёвка (2 стола по 16) → доигрываем все матчи случайно.
     */
    private function seedFullTournament(SymfonyStyle $io, string $date): void
    {
        if ($this->tournaments->findOneBy(['date' => new \DateTimeImmutable($date)]) !== null) {
            $io->note("Турнир на {$date} уже есть — пропускаю.");

            return;
        }

        $surnames = ['Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Петров', 'Соколов', 'Михайлов', 'Новиков', 'Фёдоров', 'Морозов', 'Волков', 'Алексеев', 'Лебедев', 'Семёнов', 'Егоров', 'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов', 'Андреев', 'Макаров', 'Никитин', 'Захаров', 'Зайцев', 'Соловьёв', 'Борисов', 'Яковлев', 'Григорьев', 'Романов', 'Воробьёв', 'Сергеев'];
        $firstNames = ['Александр', 'Дмитрий', 'Максим', 'Сергей', 'Андрей', 'Алексей', 'Артём', 'Илья', 'Кирилл', 'Михаил', 'Никита', 'Матвей', 'Роман', 'Егор', 'Арсений', 'Иван', 'Денис', 'Евгений', 'Даниил', 'Тимофей', 'Владислав', 'Игорь', 'Владимир', 'Павел', 'Руслан', 'Марк', 'Тимур', 'Олег', 'Ярослав', 'Антон', 'Виктор', 'Глеб'];

        $t = new Tournament();
        $t->setName('Турнир ' . $date);
        $t->setDate(new \DateTimeImmutable($date));
        $t->setStatus(TournamentStatus::Checkin);
        $this->em->persist($t);
        $this->em->flush();

        for ($i = 0; $i < 32; $i++) {
            $phone = '7910' . str_pad((string) ($i + 1), 7, '0', \STR_PAD_LEFT);
            $fullName = $surnames[$i] . ' ' . $firstNames[$i];
            $user = $this->getOrCreateUser($phone, $fullName);
            $this->em->flush();
            $this->registration->register($t, $user, ignoreSchedule: true);
        }

        $this->draw->draw($t);

        // Доигрываем все готовые матчи, победитель — случайный из пары.
        do {
            $progressed = false;
            foreach ($this->matches->findByTournamentOrdered($t) as $m) {
                if ($m->getStatus() === MatchStatus::Pending && $m->isReady()) {
                    $winner = random_int(0, 1) === 0 ? $m->getPlayer1() : $m->getPlayer2();
                    $this->advance->recordWinner($m, $winner, byAdmin: true);
                    $progressed = true;
                }
            }
        } while ($progressed);

        $io->writeln("Создан полный турнир {$date} (32 игрока, сыгран).");
    }

    private function getOrCreateUser(string $phone, string $name): User
    {
        $user = $this->users->findOneByPhone($phone);
        if ($user === null) {
            $user = new User();
            $user->setPhone($phone);
            $user->setName($name);
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(6))));
            $this->em->persist($user);
        }

        return $user;
    }

    /**
     * @param list<string>                                       $roster имена игроков стола 1
     * @param list<array{int, int, string, string, string}>     $games  [round, slot, p1, p2, winner]
     * @param array<string, User>                                $u
     */
    private function seedTournament(
        SymfonyStyle $io,
        string $date,
        array $roster,
        array $games,
        string $championName,
        array $u,
    ): void {
        if ($this->tournaments->findOneBy(['date' => new \DateTimeImmutable($date)]) !== null) {
            $io->note("Турнир на {$date} уже есть — пропускаю.");

            return;
        }

        $t = new Tournament();
        $t->setName('Турнир ' . $date);
        $t->setDate(new \DateTimeImmutable($date));
        $t->setStatus(TournamentStatus::Finished);
        $this->em->persist($t);

        foreach ($roster as $name) {
            $entry = new TournamentEntry($t, $u[$name]);
            $entry->setStatus(EntryStatus::Registered);
            $entry->setCheckedIn(true);
            $entry->setTableNumber(1);
            $this->em->persist($entry);
        }

        foreach ($games as [$round, $slot, $p1, $p2, $winner]) {
            $m = new BracketMatch($t, 1, $round, $slot);
            $m->setPlayer1($u[$p1]);
            $m->setPlayer2($u[$p2]);
            $m->setWinner($u[$winner]);
            $this->em->persist($m);
        }

        $u[$championName]->setIsChampion(true);
        $io->writeln("Создан турнир {$date}, чемпион — {$championName}.");
    }
}
