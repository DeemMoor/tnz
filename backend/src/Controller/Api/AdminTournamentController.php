<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\EntryStatus;
use App\Exception\RegistrationException;
use App\Repository\TournamentEntryRepository;
use App\Repository\UserRepository;
use App\Service\CheckinService;
use App\Service\DrawService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Админские действия по чекину и очереди в день турнира.
 * Все эндпоинты под ROLE_ADMIN (см. также access_control ^/api/admin).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/api/admin/tournaments/{id}', requirements: ['id' => '\d+'])]
final class AdminTournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentEntryRepository $entries,
        private readonly CheckinService $checkin,
    ) {
    }

    /**
     * Ростер для экрана чекина: основа (с отметками), очередь, счётчики.
     */
    #[Route('/roster', name: 'api_admin_roster', methods: ['GET'])]
    public function roster(Tournament $tournament): JsonResponse
    {
        $main = $this->entries->findByStatusOrdered($tournament, EntryStatus::Registered);
        $waitlist = $this->entries->findByStatusOrdered($tournament, EntryStatus::Waitlisted);

        $checkedIn = array_filter($main, static fn (TournamentEntry $e) => $e->isCheckedIn());

        return $this->json([
            'tournamentId' => $tournament->getId(),
            'status' => $tournament->getStatus()->value,
            'capacity' => Tournament::CAPACITY,
            'registeredCount' => \count($main),
            'checkedInCount' => \count($checkedIn),
            'waitlistCount' => \count($waitlist),
            'main' => array_map($this->entryView(...), $main),
            'waitlist' => array_map($this->entryView(...), $waitlist),
        ]);
    }

    #[Route('/checkin/{userId}', name: 'api_admin_checkin', methods: ['POST'], requirements: ['userId' => '\d+'])]
    public function checkinUser(Tournament $tournament, int $userId, UserRepository $users): JsonResponse
    {
        $user = $users->find($userId);
        if ($user === null) {
            return $this->json(['error' => 'Игрок не найден'], 404);
        }

        try {
            $this->checkin->checkIn($tournament, $user, byAdmin: true);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->roster($tournament);
    }

    #[Route('/walk-in', name: 'api_admin_walk_in', methods: ['POST'])]
    public function walkIn(Tournament $tournament, Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $phone = \is_string($data['phone'] ?? null) ? $data['phone'] : '';
        $name = \is_string($data['name'] ?? null) ? $data['name'] : '';

        try {
            $this->checkin->walkIn($tournament, $phone, $name);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->roster($tournament);
    }

    #[Route('/close-checkin', name: 'api_admin_close_checkin', methods: ['POST'])]
    public function closeCheckin(Tournament $tournament): JsonResponse
    {
        try {
            $result = $this->checkin->closeCheckin($tournament);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }
        $roster = json_decode($this->roster($tournament)->getContent(), true);
        $roster['closed'] = $result; // {dropped, promoted}

        return $this->json($roster);
    }

    #[Route('/draw', name: 'api_admin_draw', methods: ['POST'])]
    public function draw(Tournament $tournament, DrawService $drawService): JsonResponse
    {
        try {
            $result = $drawService->draw($tournament);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->json([
            'status' => $tournament->getStatus()->value,
            'tables' => $result,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryView(TournamentEntry $entry): array
    {
        $user = $entry->getUser();

        return [
            'userId' => $user->getId(),
            'name' => $user->getName(),
            'phone' => $user->getPhone(),
            'checkedIn' => $entry->isCheckedIn(),
        ];
    }
}
