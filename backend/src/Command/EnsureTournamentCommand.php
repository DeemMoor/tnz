<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tournament;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use App\Service\NotificationService;
use App\Service\TournamentSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Автосоздание турнира на ближайшее воскресенье. Идемпотентна: если турнир на
 * эту дату уже есть — ничего не делает.
 *
 * Ставится в cron (BeGet), по договорённости — в четверг к 16:00, к открытию
 * регистрации. Запуск: php bin/console app:ensure-tournament
 */
#[AsCommand(name: 'app:ensure-tournament', description: 'Создать турнир на ближайшее воскресенье, если его ещё нет')]
final class EnsureTournamentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TournamentRepository $tournaments,
        private readonly TournamentSchedule $schedule,
        private readonly NotificationService $notifications,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Ближайшее воскресенье: сегодня, если сегодня вс, иначе следующее.
        $today = $this->clock->now()->setTime(0, 0);
        $daysUntilSunday = (7 - (int) $today->format('N')) % 7;
        $sunday = $today->modify("+{$daysUntilSunday} days");

        if ($this->tournaments->findOneBy(['date' => $sunday]) !== null) {
            $io->info("Турнир на {$sunday->format('Y-m-d')} уже существует — пропускаю.");

            return Command::SUCCESS;
        }

        $tournament = new Tournament();
        $tournament->setDate($sunday);
        $tournament->setStatus(TournamentStatus::Registration);
        $tournament->setName('Турнир #' . $this->schedule->number($tournament));

        $this->em->persist($tournament);
        $this->em->flush();

        // Авто-анонс: письма всем игрокам с email.
        $sent = $this->notifications->announceRegistrationOpen($tournament);

        $io->success("Создан турнир #{$this->schedule->number($tournament)} на {$sunday->format('Y-m-d')}. Разослано писем: {$sent}.");

        return Command::SUCCESS;
    }
}
