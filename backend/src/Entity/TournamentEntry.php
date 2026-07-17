<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EntryStatus;
use App\Repository\TournamentEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Запись игрока на турнир: его место (основа/очередь), чекин, стол в сетке.
 * На одного игрока в одном турнире — максимум одна запись.
 */
#[ORM\Entity(repositoryClass: TournamentEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_entry_tournament_user', columns: ['tournament_id', 'user_id'])]
class TournamentEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    private Tournament $tournament;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, enumType: EntryStatus::class)]
    private EntryStatus $status = EntryStatus::Registered;

    /** Момент регистрации — задаёт порядок в основе и в очереди ожидания. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column]
    private bool $checkedIn = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    /** Стол в сетке (1 или 2), проставляется при жеребьёвке. */
    #[ORM\Column(nullable: true)]
    private ?int $tableNumber = null;

    /** Выбыл из сетки (проиграл матч). */
    #[ORM\Column]
    private bool $eliminated = false;

    public function __construct(Tournament $tournament, User $user)
    {
        $this->tournament = $tournament;
        $this->user = $user;
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): EntryStatus
    {
        return $this->status;
    }

    public function setStatus(EntryStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function isCheckedIn(): bool
    {
        return $this->checkedIn;
    }

    public function setCheckedIn(bool $checkedIn): static
    {
        $this->checkedIn = $checkedIn;
        $this->checkedInAt = $checkedIn ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function getTableNumber(): ?int
    {
        return $this->tableNumber;
    }

    public function setTableNumber(?int $tableNumber): static
    {
        $this->tableNumber = $tableNumber;

        return $this;
    }

    public function isEliminated(): bool
    {
        return $this->eliminated;
    }

    public function setEliminated(bool $eliminated): static
    {
        $this->eliminated = $eliminated;

        return $this;
    }
}
