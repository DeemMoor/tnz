<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Exception\RegistrationException;
use App\Repository\TournamentEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Бизнес-логика записи на турнир: регистрация с гейтами, очередь ожидания,
 * снятие с регистрации и автоподъём первого из очереди.
 * Флаши делает сам сервис (операции атомарны на уровне одного вызова).
 */
final class RegistrationService
{
    /** RTTF-рейтинг строго выше этого — на турнир нельзя. */
    public const int RTTF_LIMIT = 250;

    public function __construct(
        private readonly TournamentEntryRepository $entries,
        private readonly TournamentSchedule $schedule,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Записать игрока на турнир. Возвращает созданную/обновлённую запись.
     *
     * @throws RegistrationException при закрытой регистрации, гейтах или дубле
     */
    public function register(
        Tournament $tournament,
        User $user,
        ?\DateTimeImmutable $now = null,
        bool $ignoreSchedule = false,
    ): TournamentEntry {
        // $ignoreSchedule = true для walk-in: админ добавляет игрока на месте,
        // когда окно регистрации по времени уже могло закрыться.
        if (!$ignoreSchedule && !$this->schedule->isRegistrationOpen($tournament, $now)) {
            throw new RegistrationException('Регистрация на этот турнир сейчас закрыта', 403);
        }
        if ($user->isChampion()) {
            throw new RegistrationException('Чемпионы не участвуют в обычных турнирах', 403);
        }
        $rating = $user->getRttfRating();
        if ($rating !== null && $rating > self::RTTF_LIMIT) {
            throw new RegistrationException(
                \sprintf('Турнир для игроков с рейтингом до %d. Ваш рейтинг: %d', self::RTTF_LIMIT, $rating),
                403,
            );
        }

        $entry = $this->entries->findOneByTournamentAndUser($tournament, $user);
        if ($entry !== null && \in_array($entry->getStatus(), [EntryStatus::Registered, EntryStatus::Waitlisted], true)) {
            throw new RegistrationException('Вы уже записаны на этот турнир', 409);
        }

        // Новая запись или повторная после снятия/сброса (тогда — в конец очереди).
        if ($entry === null) {
            $entry = new TournamentEntry($tournament, $user);
            $this->em->persist($entry);
        } else {
            // Реактивация: заново встаём в порядок по текущему времени.
            $entry->setStatus(EntryStatus::Registered);
        }

        $registeredCount = $this->entries->countByStatus($tournament, EntryStatus::Registered);
        $entry->setStatus(
            $registeredCount < Tournament::CAPACITY ? EntryStatus::Registered : EntryStatus::Waitlisted,
        );

        $this->em->flush();

        return $entry;
    }

    /**
     * Снять игрока с регистрации. Если освободилось место в основе —
     * поднять первого из очереди.
     *
     * @throws RegistrationException если игрок не записан
     */
    public function unregister(Tournament $tournament, User $user): void
    {
        $entry = $this->entries->findOneByTournamentAndUser($tournament, $user);
        if ($entry === null || !\in_array($entry->getStatus(), [EntryStatus::Registered, EntryStatus::Waitlisted], true)) {
            throw new RegistrationException('Вы не записаны на этот турнир', 404);
        }

        $freedMainSlot = $entry->getStatus() === EntryStatus::Registered;
        $entry->setStatus(EntryStatus::Cancelled);
        // Фиксируем отмену до подсчёта мест — иначе запрос к БД ещё видит
        // игрока в основе и очередь не подтянется.
        $this->em->flush();

        if ($freedMainSlot) {
            $this->promoteFirstWaitlisted($tournament);
            $this->em->flush();
        }
    }

    /**
     * Поднять первого из очереди ожидания в основу (если место есть и очередь не пуста).
     */
    public function promoteFirstWaitlisted(Tournament $tournament): ?TournamentEntry
    {
        $registeredCount = $this->entries->countByStatus($tournament, EntryStatus::Registered);
        if ($registeredCount >= Tournament::CAPACITY) {
            return null;
        }

        $next = $this->entries->findFirstWaitlisted($tournament);
        if ($next === null) {
            return null;
        }

        $next->setStatus(EntryStatus::Registered);

        return $next;
    }
}
