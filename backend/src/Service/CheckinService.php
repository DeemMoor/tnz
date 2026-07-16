<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Enum\TournamentStatus;
use App\Exception\RegistrationException;
use App\Repository\BracketMatchRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Чекин игроков в день турнира и обработка очереди при закрытии чекина.
 */
final class CheckinService
{
    public function __construct(
        private readonly TournamentEntryRepository $entries,
        private readonly UserRepository $users,
        private readonly BracketMatchRepository $matches,
        private readonly RegistrationService $registration,
        private readonly TournamentSchedule $schedule,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * После жеребьёвки состав менять нельзя.
     *
     * @throws RegistrationException
     */
    private function assertNotDrawn(Tournament $tournament): void
    {
        if ($this->matches->countByTournament($tournament) > 0) {
            throw new RegistrationException('Жеребьёвка уже проведена — состав зафиксирован', 409);
        }
    }

    /**
     * Отметить игрока пришедшим. Сам игрок — только в окне чекина;
     * админ — в любой момент ($byAdmin = true).
     *
     * @throws RegistrationException
     */
    public function checkIn(Tournament $tournament, User $user, bool $byAdmin = false, ?\DateTimeImmutable $now = null): TournamentEntry
    {
        $this->assertNotDrawn($tournament);

        if (!$byAdmin && !$this->schedule->isCheckinOpen($tournament, $now)) {
            throw new RegistrationException('Чекин сейчас закрыт (окно — воскресенье 14:00–14:15)', 403);
        }

        $entry = $this->entries->findOneByTournamentAndUser($tournament, $user);
        if ($entry === null || $entry->getStatus() !== EntryStatus::Registered) {
            throw new RegistrationException('Отметиться может только записанный участник', 409);
        }

        if (!$entry->isCheckedIn()) {
            $entry->setCheckedIn(true);
            $this->em->flush();
        }

        return $entry;
    }

    /**
     * Walk-in: админ заводит игрока «с колёс» (телефон + имя) и сразу чекинит.
     * Если игрок с таким телефоном уже есть — используем его аккаунт.
     *
     * @throws RegistrationException
     */
    public function walkIn(Tournament $tournament, string $rawPhone, string $name): TournamentEntry
    {
        $this->assertNotDrawn($tournament);

        $phone = $this->phoneNormalizer->normalize($rawPhone);
        if ($phone === null) {
            throw new RegistrationException('Некорректный номер телефона', 422);
        }
        $name = trim($name);
        if ($name === '') {
            throw new RegistrationException('Укажите имя', 422);
        }

        $user = $this->users->findOneByPhone($phone);
        if ($user === null) {
            $user = new User();
            $user->setPhone($phone);
            $user->setName($name);
            // Временный случайный пароль: аккаунт существует для сетки/статистики.
            // Полноценный вход игрок получит после сброса пароля (отдельная задача).
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(8))));
            $this->em->persist($user);
            $this->em->flush();
        }

        // Ставим на турнир в обход окна регистрации (админ на месте), затем чекиним.
        $entry = $this->registration->register($tournament, $user, ignoreSchedule: true);
        if ($entry->getStatus() === EntryStatus::Registered) {
            $entry->setCheckedIn(true);
            $this->em->flush();
        }

        return $entry;
    }

    /**
     * Закрыть чекин (кнопка админа ~14:15):
     *  - все из основы, кто не отметился, → dropped;
     *  - освободившиеся места добираются из очереди по порядку (первому — независимо
     *    от того, отмечался ли он);
     *  - статус турнира → checkin (готов к жеребьёвке).
     *
     * @return array{dropped: int, promoted: int}
     */
    public function closeCheckin(Tournament $tournament): array
    {
        $this->assertNotDrawn($tournament);

        $dropped = 0;
        foreach ($this->entries->findByStatusOrdered($tournament, EntryStatus::Registered) as $entry) {
            if (!$entry->isCheckedIn()) {
                $entry->setStatus(EntryStatus::Dropped);
                $dropped++;
            }
        }
        $this->em->flush();

        $promoted = 0;
        while ($this->registration->promoteFirstWaitlisted($tournament) !== null) {
            $this->em->flush();
            $promoted++;
        }

        $tournament->setStatus(TournamentStatus::Checkin);
        $this->em->flush();

        return ['dropped' => $dropped, 'promoted' => $promoted];
    }
}
