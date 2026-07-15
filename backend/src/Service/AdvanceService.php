<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Exception\RegistrationException;
use App\Repository\BracketMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ход турнира: запись победителя матча, продвижение по сетке, чемпион стола
 * и завершение турнира.
 */
final class AdvanceService
{
    public function __construct(
        private readonly BracketMatchRepository $matches,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Записать победителя матча и продвинуть его в следующий тур.
     *
     * @throws RegistrationException
     */
    public function recordWinner(BracketMatch $match, User $winner, bool $byAdmin): void
    {
        if ($winner !== $match->getPlayer1() && $winner !== $match->getPlayer2()) {
            throw new RegistrationException('Победитель должен быть участником матча', 422);
        }

        if ($match->getStatus() === MatchStatus::Done) {
            if ($match->getWinner() === $winner) {
                return; // тот же результат — идемпотентно
            }
            if (!$byAdmin) {
                throw new RegistrationException('Матч уже сыгран', 409);
            }
            // Админ переотмечает: откатываем прошлого победителя.
            $this->rollbackWinner($match);
        }

        $match->setWinner($winner);

        $tournament = $match->getTournament();
        if ($tournament->getStatus() === TournamentStatus::Drawn) {
            $tournament->setStatus(TournamentStatus::InProgress);
        }

        $next = $this->nextMatch($match);
        if ($next === null) {
            // Это финал стола — победитель становится чемпионом.
            $winner->setIsChampion(true);
        } else {
            $this->placeInto($next, $match->getSlot(), $winner);
        }

        $this->maybeFinish($tournament);

        $this->em->flush();
    }

    /**
     * Откат прошлого результата (для переотметки админом).
     */
    private function rollbackWinner(BracketMatch $match): void
    {
        $old = $match->getWinner();
        if ($old === null) {
            return;
        }

        $next = $this->nextMatch($match);
        if ($next === null) {
            // Был финал — снимаем чемпионство.
            $old->setIsChampion(false);

            return;
        }

        // Убираем старого победителя из слота следующего матча, если он ещё там.
        if ($match->getSlot() % 2 === 0 && $next->getPlayer1() === $old) {
            $next->setPlayer1(null);
        } elseif ($match->getSlot() % 2 === 1 && $next->getPlayer2() === $old) {
            $next->setPlayer2(null);
        }
    }

    /**
     * Матч следующего тура, куда идёт победитель данного (или null, если это финал).
     */
    private function nextMatch(BracketMatch $match): ?BracketMatch
    {
        return $this->matches->findOneBySlot(
            $match->getTournament(),
            $match->getTableNumber(),
            $match->getRound() + 1,
            intdiv($match->getSlot(), 2),
        );
    }

    /**
     * Поставить игрока в слот следующего матча: player1 если исходный slot чётный,
     * иначе player2.
     */
    private function placeInto(BracketMatch $next, int $fromSlot, User $player): void
    {
        if ($fromSlot % 2 === 0) {
            $next->setPlayer1($player);
        } else {
            $next->setPlayer2($player);
        }
    }

    /**
     * Турнир завершён, когда финалы всех столов сыграны.
     */
    private function maybeFinish(Tournament $tournament): void
    {
        $all = $this->matches->findByTournamentOrdered($tournament);

        // Максимальный тур (финал) для каждого стола.
        $maxRoundByTable = [];
        foreach ($all as $m) {
            $table = $m->getTableNumber();
            $maxRoundByTable[$table] = max($maxRoundByTable[$table] ?? 0, $m->getRound());
        }

        foreach ($maxRoundByTable as $table => $finalRound) {
            $final = $this->matches->findOneBySlot($tournament, $table, $finalRound, 0);
            if ($final === null || $final->getStatus() !== MatchStatus::Done) {
                return; // ещё не всё сыграно
            }
        }

        $tournament->setStatus(TournamentStatus::Finished);
    }
}
