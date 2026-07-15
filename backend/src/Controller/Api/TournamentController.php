<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Exception\RegistrationException;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentRepository;
use App\Service\CheckinService;
use App\Service\RegistrationService;
use App\Service\TournamentSchedule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Публичный список турниров и запись/снятие игрока.
 */
final class TournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournaments,
        private readonly TournamentEntryRepository $entries,
        private readonly TournamentSchedule $schedule,
    ) {
    }

    #[Route('/api/tournaments', name: 'api_tournaments_list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        $view = array_map(
            fn (Tournament $t) => $this->view($t, $user),
            $this->tournaments->findAllOrderedByDateDesc(),
        );

        return $this->json($view);
    }

    /**
     * Ближайший незавершённый турнир — для главной. null, если такого нет.
     */
    #[Route('/api/tournaments/nearest', name: 'api_tournaments_nearest', methods: ['GET'])]
    public function nearest(#[CurrentUser] ?User $user): JsonResponse
    {
        $tournament = $this->tournaments->findNearestUpcoming();

        return $this->json($tournament !== null ? $this->view($tournament, $user) : null);
    }

    #[Route('/api/tournaments/{id}', name: 'api_tournaments_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Tournament $tournament, #[CurrentUser] ?User $user): JsonResponse
    {
        return $this->json($this->view($tournament, $user));
    }

    #[Route('/api/tournaments/{id}/register', name: 'api_tournaments_register', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function register(
        Tournament $tournament,
        #[CurrentUser] User $user,
        RegistrationService $registration,
    ): JsonResponse {
        try {
            $registration->register($tournament, $user);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->json($this->view($tournament, $user), JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/tournaments/{id}/registration', name: 'api_tournaments_unregister', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function unregister(
        Tournament $tournament,
        #[CurrentUser] User $user,
        RegistrationService $registration,
    ): JsonResponse {
        try {
            $registration->unregister($tournament, $user);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->json($this->view($tournament, $user));
    }

    #[Route('/api/tournaments/{id}/checkin', name: 'api_tournaments_checkin', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function checkin(
        Tournament $tournament,
        #[CurrentUser] User $user,
        CheckinService $checkin,
    ): JsonResponse {
        try {
            $checkin->checkIn($tournament, $user);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->json($this->view($tournament, $user));
    }

    /**
     * Сборка представления турнира: расписание, счётчики и статус текущего игрока.
     *
     * @return array<string, mixed>
     */
    private function view(Tournament $tournament, ?User $user): array
    {
        $registeredCount = $this->entries->countByStatus($tournament, EntryStatus::Registered);
        $waitlistCount = $this->entries->countByStatus($tournament, EntryStatus::Waitlisted);

        $me = null;
        if ($user !== null) {
            $entry = $this->entries->findOneByTournamentAndUser($tournament, $user);
            // Показываем «me» только для активной записи (в основе или очереди).
            // Снятые/сброшенные — как будто не записан.
            $activeStatuses = [EntryStatus::Registered, EntryStatus::Waitlisted];
            if ($entry !== null && \in_array($entry->getStatus(), $activeStatuses, true)) {
                $position = null;
                if ($entry->getStatus() === EntryStatus::Waitlisted) {
                    // Позиция в очереди = сколько записавшихся в очередь раньше + 1.
                    $position = 1;
                    foreach ($this->entries->findByStatusOrdered($tournament, EntryStatus::Waitlisted) as $i => $w) {
                        if ($w->getId() === $entry->getId()) {
                            $position = $i + 1;
                            break;
                        }
                    }
                }
                $me = [
                    'status' => $entry->getStatus()->value,
                    'checkedIn' => $entry->isCheckedIn(),
                    'waitlistPosition' => $position,
                ];
            }
        }

        return [
            'id' => $tournament->getId(),
            'number' => $this->schedule->number($tournament),
            'name' => (string) $tournament,
            'date' => $tournament->getDate()->format('Y-m-d'),
            'status' => $tournament->getStatus()->value,
            'capacity' => Tournament::CAPACITY,
            'registeredCount' => $registeredCount,
            'waitlistCount' => $waitlistCount,
            'registrationOpensAt' => $this->schedule->registrationOpensAt($tournament)->format(\DATE_ATOM),
            'checkinStartsAt' => $this->schedule->checkinStartsAt($tournament)->format(\DATE_ATOM),
            'checkinEndsAt' => $this->schedule->checkinEndsAt($tournament)->format(\DATE_ATOM),
            'registrationOpen' => $this->schedule->isRegistrationOpen($tournament),
            'checkinOpen' => $this->schedule->isCheckinOpen($tournament),
            'me' => $me,
        ];
    }
}
