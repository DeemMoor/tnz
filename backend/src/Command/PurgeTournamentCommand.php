<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Repository\TournamentRepository;
use App\Service\TournamentSchedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Полностью удалить турнир вместе со всеми его матчами и записями участников.
 *
 * Нужна, когда турнир «сломался» (например, недожеребёвка) и его не даёт удалить
 * ни админка, ни просто DELETE — мешают внешние ключи bracket_match и
 * tournament_entry, у которых нет каскада на турнир.
 *
 * notification_log очистится сам: там FK с onDelete SET NULL.
 *
 * Запуск: php bin/console app:tournament:purge 19
 */
#[AsCommand(name: 'app:tournament:purge', description: 'Удалить турнир вместе с матчами и записями участников')]
final class PurgeTournamentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TournamentRepository $tournaments,
        private readonly TournamentSchedule $schedule,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID турнира (столбец id в таблице tournament)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (int) $input->getArgument('id');

        $tournament = $this->tournaments->find($id);
        if ($tournament === null) {
            $io->error("Турнир с id={$id} не найден.");

            return Command::FAILURE;
        }

        $number = $this->schedule->number($tournament);
        $date = $tournament->getDate()->format('Y-m-d');

        if (!$io->confirm("Удалить турнир #{$number} ({$date}, id={$id}) со всеми матчами и участниками? Отмена невозможна.", false)) {
            $io->comment('Отменено.');

            return Command::SUCCESS;
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $matches = $this->em->createQuery(
                'DELETE FROM ' . BracketMatch::class . ' m WHERE m.tournament = :t'
            )->setParameter('t', $tournament)->execute();

            $entries = $this->em->createQuery(
                'DELETE FROM ' . TournamentEntry::class . ' e WHERE e.tournament = :t'
            )->setParameter('t', $tournament)->execute();

            $this->em->createQuery(
                'DELETE FROM ' . Tournament::class . ' t WHERE t.id = :id'
            )->setParameter('id', $id)->execute();

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            $io->error('Не удалось удалить: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success("Турнир #{$number} удалён. Матчей: {$matches}, записей участников: {$entries}.");

        return Command::SUCCESS;
    }
}
