<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Создать администратора или выдать ROLE_ADMIN существующему игроку.
 *
 * Запуск: php bin/console app:create-admin "<телефон>" "<пароль>" "<Фамилия Имя>"
 * Если игрок с таким телефоном уже есть — ему добавляется роль ROLE_ADMIN
 * (пароль/имя не меняются).
 */
#[AsCommand(name: 'app:create-admin', description: 'Создать админа или выдать ROLE_ADMIN')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('phone', InputArgument::REQUIRED, 'Телефон')
            ->addArgument('password', InputArgument::OPTIONAL, 'Пароль (нужен только для нового игрока)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Фамилия Имя (для нового игрока)', 'Администратор');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $phone = $this->phoneNormalizer->normalize((string) $input->getArgument('phone'));
        if ($phone === null) {
            $io->error('Некорректный номер телефона.');

            return Command::FAILURE;
        }

        $user = $this->users->findOneByPhone($phone);

        if ($user === null) {
            $password = (string) $input->getArgument('password');
            if (mb_strlen($password) < 6) {
                $io->error('Для нового админа укажите пароль (минимум 6 символов) вторым аргументом.');

                return Command::FAILURE;
            }
            $user = new User();
            $user->setPhone($phone);
            $user->setName((string) $input->getArgument('name'));
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $io->text("Создан новый пользователь {$phone}.");
        } else {
            $io->text("Пользователь {$phone} уже существует — выдаю права админа.");
        }

        $roles = $user->getRoles();
        if (!\in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles(array_values(array_unique(array_filter($roles, static fn (string $r) => $r !== 'ROLE_USER'))));
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Готово. {$phone} теперь администратор.");

        return Command::SUCCESS;
    }
}
