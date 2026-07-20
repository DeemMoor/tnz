<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BracketMatch;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Enum\MatchStatus;
use App\Enum\TournamentStatus;
use App\Exception\RegistrationException;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ход турнира: запись победителя матча, продвижение по сетке, чемпион стола
 * и завершение турнира.
 */
final class AdvanceService
{
    public function __construct(
        private readonly BracketMatchRepository $matches,
        private readonly TournamentEntryRepository $entries,
        private readonly UserRepository $users,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Записать победителя матча и продвинуть его в следующий тур.
     *
     * @throws RegistrationException
     */
    public function recordWinner(BracketMatch $match, User $winner, bool $byAdmin, bool $walkover = false): void
    {
        if ($winner !== $match->getPlayer1() && $winner !== $match->getPlayer2()) {
            throw new RegistrationException('Победитель должен быть участником матча', 422);
        }

        if ($match->getStatus() === MatchStatus::Done) {
            if ($match->getWinner() === $winner && $match->isWalkover() === $walkover) {
                return; // тот же результат — идемпотентно
            }
            if (!$byAdmin) {
                throw new RegistrationException('Матч уже сыгран', 409);
            }
            // Админ переотмечает: откатываем прошлого победителя.
            $this->rollbackWinner($match);
        }

        $match->setWinner($winner, $walkover);

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
     * Админ подсаживает проигравшего со стола 1 в пустой bye-слот 1-го тура
     * стола 2: отменяем прежний автопроход, ставим игрока вторым, матч
     * возвращается в статус "не сыгран" — дальше его отмечают как обычный.
     *
     * @throws RegistrationException
     */
    public function fillBye(BracketMatch $match, User $player): void
    {
        $tournament = $match->getTournament();
        if ($tournament->getStatus() === TournamentStatus::Finished) {
            throw new RegistrationException('Турнир уже завершён', 422);
        }
        if ($match->getTableNumber() !== 2 || $match->getRound() !== 1) {
            throw new RegistrationException('Подсадка доступна только в 1-м туре стола 2', 422);
        }
        if ($match->getPlayer1() === null || $match->getPlayer2() !== null || !$match->isWalkover()) {
            throw new RegistrationException('Это не пустой bye-слот', 422);
        }

        $isTable1Loser = \in_array($player, $this->matches->findEligibleTable1Losers($tournament), true);
        $isFreshPlayer = !$this->matches->hasAppearance($tournament, $player);
        if (!$isTable1Loser && !$isFreshPlayer) {
            throw new RegistrationException('Этот игрок недоступен для подсадки', 422);
        }

        $this->rollbackWinner($match);
        $match->setPlayer2($player);
        $match->setWinner(null);

        $entry = $this->entries->findOneByTournamentAndUser($tournament, $player);
        $entry?->setTableNumber(2);

        $this->em->flush();
    }

    /**
     * Как fillBye(), но сначала заводит нового игрока по телефону+имени (или
     * находит существующего пользователя по телефону), если он ещё не участвует
     * в этом турнире. В отличие от обычного walk-in (CheckinService::walkIn),
     * это можно делать и после жеребьёвки — игрок сразу садится в bye-слот,
     * минуя очередь регистрации.
     *
     * @throws RegistrationException
     */
    public function fillByeWithNewPlayer(BracketMatch $match, string $rawPhone, string $name): void
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        if ($phone === null) {
            throw new RegistrationException('Некорректный номер телефона', 422);
        }
        $name = trim($name);

        $tournament = $match->getTournament();
        $user = $this->users->findOneByPhone($phone);
        if ($user === null) {
            if ($name === '') {
                throw new RegistrationException('Укажите имя нового игрока', 422);
            }
            $user = new User();
            $user->setPhone($phone);
            $user->setName($name);
            // Временный случайный пароль: аккаунт существует для сетки/статистики.
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(8))));
            $this->em->persist($user);
        }

        // Заводим запись на турнир, только если у игрока её ещё нет вовсе —
        // а годится ли он для подсадки (новый или проигравший стола 1),
        // решает единая проверка внутри fillBye().
        if ($this->entries->findOneByTournamentAndUser($tournament, $user) === null) {
            $entry = new TournamentEntry($tournament, $user);
            $entry->setStatus(EntryStatus::Registered);
            $entry->setCheckedIn(true);
            $this->em->persist($entry);
            $this->em->flush();
        }

        $this->fillBye($match, $user);
    }

    /**
     * Откат прошлого результата (для переотметки админом).
     *
     * @throws RegistrationException если следующий матч уже сыгран
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

        if ($next->getStatus() === MatchStatus::Done) {
            throw new RegistrationException(
                'Нельзя изменить: следующий матч уже сыгран — сначала отмените его результат',
                409,
            );
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
