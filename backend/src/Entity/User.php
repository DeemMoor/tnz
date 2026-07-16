<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Игрок / пользователь системы.
 * Логин-идентификатор — телефон (уникальный). Пароль хранится только хешем.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_phone', columns: ['phone'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Нормализованный телефон (только цифры в формате 7XXXXXXXXXX). Уникален. */
    #[ORM\Column(length: 20)]
    private string $phone;

    /** Хеш пароля (argon2id). Никогда не хранить/сравнивать открытый пароль. */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    private string $name;

    /** Ник (прозвище). Если задан — показывается вместо ФИО в сетке/статистике. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nickname = null;

    /** Telegram-контакт (@username или ссылка), просто для связи. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $telegram = null;

    /** Имя файла аватарки в public/uploads/avatars/ (null = буква-аватар). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column]
    private bool $emailVerified = false;

    /** Токен для подтверждения email по ссылке из письма (T2). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Рейтинг игрока на rttf.ru (самозапись). null = рейтинга нет.
     * Гейт: строго выше 250 → регистрация на турнир запрещена.
     */
    #[ORM\Column(nullable: true)]
    private ?int $rttfRating = null;

    /** Победитель турнира: в обычных турнирах больше не участвует. */
    #[ORM\Column]
    private bool $isChampion = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Пароль в открытом виде — только для формы админки (не хранится в БД).
     * При сохранении хешируется в UserCrudController и сюда не попадает.
     */
    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Идентификатор для Symfony Security — телефон.
     */
    public function getUserIdentifier(): string
    {
        return $this->phone;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
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

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    /**
     * Отображаемое имя: ник, если задан, иначе ФИО.
     */
    public function getDisplayName(): string
    {
        return ($this->nickname !== null && $this->nickname !== '') ? $this->nickname : $this->name;
    }

    public function getTelegram(): ?string
    {
        return $this->telegram;
    }

    public function setTelegram(?string $telegram): static
    {
        $this->telegram = $telegram;

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Гарантируем, что каждый залогиненный пользователь имеет базовую роль.
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Удобный флаг для админки: есть ли у игрока роль администратора.
     */
    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    /**
     * Назначить/снять роль администратора (переключатель в админке).
     */
    public function setIsAdmin(bool $isAdmin): static
    {
        // В raw-roles храним только доп-роли (ROLE_USER добавляется в getRoles()).
        $roles = array_values(array_filter($this->roles, static fn (string $r) => $r !== 'ROLE_ADMIN'));
        if ($isAdmin) {
            $roles[] = 'ROLE_ADMIN';
        }
        $this->roles = $roles;

        return $this;
    }

    public function getRttfRating(): ?int
    {
        return $this->rttfRating;
    }

    public function setRttfRating(?int $rttfRating): static
    {
        $this->rttfRating = $rttfRating;

        return $this;
    }

    public function isChampion(): bool
    {
        return $this->isChampion;
    }

    public function setIsChampion(bool $isChampion): static
    {
        $this->isChampion = $isChampion;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * Чистка временных чувствительных данных. Пароль храним хешем — стирать нечего.
     */
    public function eraseCredentials(): void
    {
    }
}
