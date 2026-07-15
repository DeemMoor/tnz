<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\BracketMatch;
use App\Entity\User;
use App\Exception\RegistrationException;
use App\Service\AdvanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Отметка результата матча. Нажать может админ ИЛИ один из двух игроков матча.
 */
final class MatchController extends AbstractController
{
    #[Route('/api/matches/{id}/winner', name: 'api_match_winner', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function winner(
        BracketMatch $match,
        #[CurrentUser] User $user,
        Request $request,
        AdvanceService $advance,
    ): JsonResponse {
        $isAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);
        $player1 = $match->getPlayer1();
        $player2 = $match->getPlayer2();

        // Право отметить: админ или участник этого матча.
        $isParticipant = ($player1 !== null && $player1->getId() === $user->getId())
            || ($player2 !== null && $player2->getId() === $user->getId());
        if (!$isAdmin && !$isParticipant) {
            return $this->json(['error' => 'Отметить результат может только участник матча'], 403);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $winnerId = \is_int($data['winnerId'] ?? null) ? $data['winnerId'] : null;

        // Победитель — один из двух игроков матча.
        $winner = null;
        if ($winnerId !== null && $player1 !== null && $player1->getId() === $winnerId) {
            $winner = $player1;
        } elseif ($winnerId !== null && $player2 !== null && $player2->getId() === $winnerId) {
            $winner = $player2;
        }
        if ($winner === null) {
            return $this->json(['error' => 'Укажите победителя из участников матча'], 422);
        }

        try {
            $advance->recordWinner($match, $winner, byAdmin: $isAdmin);
        } catch (RegistrationException $e) {
            return $this->json(['error' => $e->getMessage()], $e->statusCode);
        }

        return $this->json([
            'match' => [
                'id' => $match->getId(),
                'winnerId' => $match->getWinner()?->getId(),
                'status' => $match->getStatus()->value,
            ],
            'tournamentStatus' => $match->getTournament()->getStatus()->value,
        ]);
    }
}
