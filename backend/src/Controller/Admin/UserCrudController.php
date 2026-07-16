<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Игроки в админке: просмотр, правка, создание (когда игрок не может
 * зарегистрироваться сам — админ заводит его по телефону+ФИО+паролю).
 */
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Игрок')
            ->setEntityLabelInPlural('Игроки')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Добавить игрока'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setLabel('Изменить'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->setLabel('Удалить'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Фамилия и Имя');
        yield TextField::new('nickname', 'Ник');
        yield TextField::new('phone', 'Телефон');
        // Пароль: обязателен при создании, при правке — пусто = не менять.
        yield TextField::new('plainPassword', 'Пароль')
            ->setFormType(PasswordType::class)
            ->onlyOnForms()
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->setHelp('При создании — задайте пароль (мин. 6). При правке пусто = не менять.');
        yield TextField::new('telegram', 'Telegram');
        yield EmailField::new('email', 'Email');
        yield BooleanField::new('emailVerified', 'Email подтверждён')->renderAsSwitch(false);
        yield IntegerField::new('rttfRating', 'RTTF')->hideOnIndex();
        yield BooleanField::new('isChampion', 'Чемпион')->renderAsSwitch(false);
        yield BooleanField::new('isAdmin', 'Админ');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPhoneAndPassword($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPhoneAndPassword($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Нормализовать телефон и захешировать пароль, если он задан в форме.
     */
    private function applyPhoneAndPassword(User $user): void
    {
        $normalized = $this->phoneNormalizer->normalize($user->getPhone());
        if ($normalized !== null) {
            $user->setPhone($normalized);
        }

        $plain = $user->getPlainPassword();
        if ($plain !== null && $plain !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $plain));
            $user->setPlainPassword(null);
        }
    }
}
