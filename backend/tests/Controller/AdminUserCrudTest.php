<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\UserCrudController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Создание игрока админом: телефон нормализуется, пароль хешируется,
 * созданный игрок проходит проверку пароля.
 */
final class AdminUserCrudTest extends KernelTestCase
{
    public function testPersistNormalizesPhoneAndHashesPassword(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $controller = new UserCrudController($hasher, $container->get(PhoneNormalizer::class));

        // Эмулируем то, что делает форма EasyAdmin: заполнили поля и plainPassword.
        $user = new User();
        $user->setName('Новичок Игрок');
        $user->setPhone('+7 900 777 88 99'); // человеческий формат
        $user->setPlainPassword('adminset1');

        $controller->persistEntity($em, $user);

        // Телефон нормализован.
        $users = $container->get(UserRepository::class);
        $created = $users->findOneByPhone('79007778899');
        self::assertNotNull($created);
        self::assertSame('Новичок Игрок', $created->getName());

        // Пароль захеширован (не открытый) и рабочий; plainPassword очищен.
        self::assertNotSame('', $created->getPassword());
        self::assertNotSame('adminset1', $created->getPassword());
        self::assertTrue($hasher->isPasswordValid($created, 'adminset1'));
        self::assertNull($created->getPlainPassword());
    }
}
