<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BracketMatch;
use App\Entity\ReplacedGame;
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
     * Отменить результат сыгранного матча: убрать победителя, вернуть матч в
     * «не сыгран». Нельзя, если победитель уже сыграл следующий матч дальше по
     * сетке (rollbackWinner кинет ошибку — сначала отмени тот).
     *
     * @throws RegistrationException
     */
    public function clearWinner(BracketMatch $match): void
    {
        if ($match->getStatus() !== MatchStatus::Done) {
            return; // уже не сыгран — отменять нечего
        }

        $this->rollbackWinner($match);
        $match->setWinner(null);

        // Раз матч снова не сыгран — турнир точно не завершён.
        $tournament = $match->getTournament();
        if ($tournament->getStatus() === TournamentStatus::Finished) {
            $tournament->setStatus(TournamentStatus::InProgress);
        }

        $this->em->flush();
    }

    /**
     * Админ сажает игрока в свободный слот матча 1-го тура: либо это пустой
     * bye-слот (кто-то прошёл автопроходом — тогда автопроход отменяем), либо
     * место освободилось после «Очистить». Матч встаёт в статус «не сыгран» —
     * дальше его отмечают как обычный.
     *
     * @throws RegistrationException
     */
    public function fillBye(BracketMatch $match, User $player): void
    {
        $this->assertFirstRoundEditable($match);

        if ($match->getPlayer1() !== null && $match->getPlayer2() !== null) {
            throw new RegistrationException('В этом матче нет свободного места', 422);
        }
        $this->assertAvailableForSeat($match->getTournament(), $player);

        // Автопроход: матч помечен сыгранным, хотя соперника не было.
        if ($match->getStatus() === MatchStatus::Done) {
            $this->rollbackWinner($match);
            $match->setWinner(null);
        }

        if ($match->getPlayer1() === null) {
            $match->setPlayer1($player);
        } else {
            $match->setPlayer2($player);
        }

        $this->seatEntry($match, $player);

        $this->em->flush();
    }

    /**
     * Как fillBye(), но сначала заводит нового игрока по телефону+имени (или
     * находит существующего пользователя по телефону), если он ещё не участвует
     * в этом турнире. В отличие от обычного walk-in (CheckinService::walkIn),
     * это можно делать и после жеребьёвки — игрок сразу садится в слот,
     * минуя очередь регистрации.
     *
     * @throws RegistrationException
     */
    public function fillByeWithNewPlayer(BracketMatch $match, string $rawPhone, string $name): void
    {
        $this->fillBye($match, $this->resolvePlayer($match->getTournament(), $rawPhone, $name));
    }

    /**
     * Замена игрока в матче 1-го тура — тот самый случай «подошёл опоздавший».
     *
     * Если матч уже сыгран, заменить можно только проигравшего: сыгранная игра
     * уезжает в лог `ReplacedGame` (победа засчитается победителю в статистике),
     * матч возвращается в «не сыгран» уже с новым соперником. Если матч ещё не
     * сыгран — это просто подмена игрока в слоте, без всякой статистики.
     *
     * @throws RegistrationException
     */
    public function replacePlayer(BracketMatch $match, User $outgoing, User $player): void
    {
        $this->assertFirstRoundEditable($match);

        $isP1 = $match->getPlayer1() === $outgoing;
        $isP2 = $match->getPlayer2() === $outgoing;
        if (!$isP1 && !$isP2) {
            throw new RegistrationException('Этот игрок не участвует в матче', 422);
        }
        if ($outgoing === $player) {
            throw new RegistrationException('Это тот же самый игрок', 422);
        }
        $this->assertAvailableForSeat($match->getTournament(), $player);

        if ($match->getStatus() === MatchStatus::Done) {
            $winner = $match->getWinner();
            if ($winner === $outgoing) {
                throw new RegistrationException(
                    'Заменить можно только проигравшего — победитель проходит дальше',
                    422,
                );
            }

            // Игра реально состоялась (оба были, не техпобеда) — сохраняем её,
            // чтобы победа не пропала вместе с заменённым соперником.
            if ($winner !== null && $match->getPlayer1() !== null && $match->getPlayer2() !== null && !$match->isWalkover()) {
                $this->em->persist(new ReplacedGame(
                    $match->getTournament(),
                    $match,
                    $winner,
                    $outgoing,
                    $match->getPlayedAt(),
                ));
            }

            $this->rollbackWinner($match);
            $match->setWinner(null);
        }

        if ($isP1) {
            $match->setPlayer1($player);
        } else {
            $match->setPlayer2($player);
        }

        // Заменённый выбывает, но стол ему оставляем — он тут играл.
        $this->entries->findOneByTournamentAndUser($match->getTournament(), $outgoing)?->setEliminated(true);
        $this->seatEntry($match, $player);

        $this->em->flush();
    }

    /**
     * Как replacePlayer(), но новый игрок заводится по телефону+имени.
     *
     * @throws RegistrationException
     */
    public function replacePlayerWithNewPlayer(
        BracketMatch $match,
        User $outgoing,
        string $rawPhone,
        string $name,
    ): void {
        $this->replacePlayer($match, $outgoing, $this->resolvePlayer($match->getTournament(), $rawPhone, $name));
    }

    /**
     * Убрать игрока из сетки: слот пустеет, результат матча (если был)
     * отменяется — как будто этого игрока в матче и не было. Нужно, когда
     * жеребьёвка посадила того, кто на самом деле не пришёл.
     *
     * @throws RegistrationException
     */
    public function removePlayer(BracketMatch $match, User $player): void
    {
        $this->assertFirstRoundEditable($match);

        $isP1 = $match->getPlayer1() === $player;
        $isP2 = $match->getPlayer2() === $player;
        if (!$isP1 && !$isP2) {
            throw new RegistrationException('Этот игрок не участвует в матче', 422);
        }

        if ($match->getStatus() === MatchStatus::Done) {
            $this->rollbackWinner($match);
            $match->setWinner(null);
        }

        if ($isP1) {
            $match->setPlayer1(null);
        } else {
            $match->setPlayer2(null);
        }

        // Из сетки убран совсем — стол сбрасываем, чтобы не числился участником.
        $entry = $this->entries->findOneByTournamentAndUser($match->getTournament(), $player);
        $entry?->setTableNumber(null);
        $entry?->setEliminated(false);

        $this->em->flush();
    }

    /**
     * Правка состава разрешена только в 1-м туре незавершённого турнира:
     * дальше по сетке игроки попадают продвижением, а не руками.
     *
     * @throws RegistrationException
     */
    private function assertFirstRoundEditable(BracketMatch $match): void
    {
        if ($match->getTournament()->getStatus() === TournamentStatus::Finished) {
            throw new RegistrationException('Турнир уже завершён', 422);
        }
        if ($match->getRound() !== 1) {
            throw new RegistrationException('Менять состав можно только в первом туре', 422);
        }
    }

    /**
     * Игрока можно сажать в слот, если он либо ещё не в сетке (пришёл только
     * что), либо уже выбыл (проиграл и нигде не ждёт своего матча).
     *
     * @throws RegistrationException
     */
    private function assertAvailableForSeat(Tournament $tournament, User $player): void
    {
        $isEliminated = \in_array($player, $this->matches->findEliminatedPlayers($tournament), true);
        $isFreshPlayer = !$this->matches->hasAppearance($tournament, $player);
        if (!$isEliminated && !$isFreshPlayer) {
            throw new RegistrationException('Этот игрок сейчас в игре — его нельзя посадить в другой матч', 422);
        }
    }

    /**
     * Запись игрока на турнир получает стол этого матча (и снова «в игре»).
     */
    private function seatEntry(BracketMatch $match, User $player): void
    {
        $entry = $this->entries->findOneByTournamentAndUser($match->getTournament(), $player);
        $entry?->setTableNumber($match->getTableNumber());
        $entry?->setEliminated(false);
    }

    /**
     * Найти пользователя по телефону или завести нового (walk-in) и записать
     * его на турнир, если записи ещё нет. Годится ли он для посадки — решает
     * общая проверка в fillBye()/replacePlayer().
     *
     * @throws RegistrationException
     */
    private function resolvePlayer(Tournament $tournament, string $rawPhone, string $name): User
    {
        $phone = $this->phoneNormalizer->normalize($rawPhone);
        if ($phone === null) {
            throw new RegistrationException('Некорректный номер телефона', 422);
        }
        $name = trim($name);

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

        if ($this->entries->findOneByTournamentAndUser($tournament, $user) === null) {
            $entry = new TournamentEntry($tournament, $user);
            $entry->setStatus(EntryStatus::Registered);
            $entry->setCheckedIn(true);
            $this->em->persist($entry);
            $this->em->flush();
        }

        return $user;
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
