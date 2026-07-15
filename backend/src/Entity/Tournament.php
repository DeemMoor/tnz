<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Турнир. Проходит еженедельно по воскресеньям. Админ задаёт только `date`
 * (дату воскресенья) — окна регистрации/чекина считаются от неё в TournamentSchedule.
 */
#[ORM\Entity(repositoryClass: TournamentRepository::class)]
class Tournament
{
    /** Вместимость основы: 2 стола × 16 игроков. */
    public const int CAPACITY = 32;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    /** Дата турнира (воскресенье). Время не важно — берётся 00:00. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 20, enumType: TournamentStatus::class)]
    private TournamentStatus $status = TournamentStatus::Draft;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, TournamentEntry> */
    #[ORM\OneToMany(targetEntity: TournamentEntry::class, mappedBy: 'tournament', orphanRemoval: true)]
    private Collection $entries;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable('next sunday');
        $this->createdAt = new \DateTimeImmutable();
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): TournamentStatus
    {
        return $this->status;
    }

    public function setStatus(TournamentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, TournamentEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Турнир #' . $this->id;
    }
}
