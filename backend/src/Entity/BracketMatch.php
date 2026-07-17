<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchStatus;
use App\Repository\BracketMatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Матч в турнирной сетке (назван BracketMatch, т.к. `Match` — зарезервированное
 * слово в PHP 8). Один матч = одна пара в одном туре на одном столе.
 *
 * Связь туров: матч (round r, slot s) отдаёт победителя в матч
 * (round r+1, slot s>>1): в player1 если s чётный, иначе в player2.
 */
#[ORM\Entity(repositoryClass: BracketMatchRepository::class)]
class BracketMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Tournament $tournament;

    /** Стол (1 или 2). Каждый стол — своя независимая сетка. */
    #[ORM\Column]
    private int $tableNumber;

    /** Номер тура на этом столе, 1 = первый сыгранный тур. */
    #[ORM\Column]
    private int $round;

    /** Позиция матча внутри тура (0-based). */
    #[ORM\Column]
    private int $slot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $player1 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $player2 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $winner = null;

    #[ORM\Column(length: 10, enumType: MatchStatus::class)]
    private MatchStatus $status = MatchStatus::Pending;

    /** Техническая победа (соперник не явился): в статистику не идёт. */
    #[ORM\Column]
    private bool $walkover = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $playedAt = null;

    public function __construct(Tournament $tournament, int $tableNumber, int $round, int $slot)
    {
        $this->tournament = $tournament;
        $this->tableNumber = $tableNumber;
        $this->round = $round;
        $this->slot = $slot;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getTableNumber(): int
    {
        return $this->tableNumber;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getSlot(): int
    {
        return $this->slot;
    }

    public function getPlayer1(): ?User
    {
        return $this->player1;
    }

    public function setPlayer1(?User $player1): static
    {
        $this->player1 = $player1;

        return $this;
    }

    public function getPlayer2(): ?User
    {
        return $this->player2;
    }

    public function setPlayer2(?User $player2): static
    {
        $this->player2 = $player2;

        return $this;
    }

    public function getWinner(): ?User
    {
        return $this->winner;
    }

    public function getStatus(): MatchStatus
    {
        return $this->status;
    }

    /**
     * Записать победителя и пометить матч сыгранным.
     * $walkover = техпобеда (соперник не явился) — в статистику не попадёт.
     */
    public function setWinner(?User $winner, bool $walkover = false): static
    {
        $this->winner = $winner;
        $this->walkover = $winner !== null && $walkover;
        if ($winner !== null) {
            $this->status = MatchStatus::Done;
            $this->playedAt = new \DateTimeImmutable();
        } else {
            $this->status = MatchStatus::Pending;
            $this->playedAt = null;
        }

        return $this;
    }

    public function isWalkover(): bool
    {
        return $this->walkover;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    /**
     * Оба соперника известны — матч можно играть.
     */
    public function isReady(): bool
    {
        return $this->player1 !== null && $this->player2 !== null;
    }
}
