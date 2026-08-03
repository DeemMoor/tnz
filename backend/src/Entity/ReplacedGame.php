<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReplacedGameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Реально сыгранная игра, вытесненная из сетки заменой игрока.
 *
 * Бывает так: в первом туре двое отыграли, а потом подошёл опоздавший, и
 * победителю предлагают сыграть ещё раз — уже с ним. Слот матча один, поэтому
 * прежний результат в `BracketMatch` не сохранить: сюда и уезжает пара
 * «победитель — заменённый», чтобы сыгранная игра не пропала из статистики.
 *
 * В таблице лидеров такая игра засчитывается только победителю (+1 игра,
 * +1 победа); заменённому ничего не пишем — так решено.
 */
#[ORM\Entity(repositoryClass: ReplacedGameRepository::class)]
class ReplacedGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    /** Матч, в котором игра была сыграна (может быть удалён — тогда null). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BracketMatch $bracketMatch = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $winner;

    /** Кого заменили — тот, кто эту игру проиграл. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $loser;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $playedAt;

    public function __construct(
        Tournament $tournament,
        ?BracketMatch $bracketMatch,
        User $winner,
        User $loser,
        ?\DateTimeImmutable $playedAt = null,
    ) {
        $this->tournament = $tournament;
        $this->bracketMatch = $bracketMatch;
        $this->winner = $winner;
        $this->loser = $loser;
        $this->playedAt = $playedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getBracketMatch(): ?BracketMatch
    {
        return $this->bracketMatch;
    }

    public function getWinner(): User
    {
        return $this->winner;
    }

    public function getLoser(): User
    {
        return $this->loser;
    }

    public function getPlayedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }
}
