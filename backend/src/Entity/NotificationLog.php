<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Запись о проведённой рассылке (для истории в админке).
 */
#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Тип события (напр. registration_open). */
    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(length: 200)]
    private string $subject;

    /** Сколько писем реально ушло. */
    #[ORM\Column]
    private int $recipientCount = 0;

    /** Связанный турнир (если рассылка про турнир). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tournament $tournament = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, string $subject)
    {
        $this->type = $type;
        $this->subject = $subject;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getRecipientCount(): int
    {
        return $this->recipientCount;
    }

    public function setRecipientCount(int $recipientCount): static
    {
        $this->recipientCount = $recipientCount;

        return $this;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): static
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
