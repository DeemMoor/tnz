<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Просмотр/правка игроков в админке. Пароль тут не трогаем (меняется игроком).
 */
final class UserCrudController extends AbstractCrudController
{
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
        // Создавать игроков руками через админку не даём (только регистрация/walk-in).
        return $actions->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Фамилия и Имя');
        yield TextField::new('nickname', 'Ник')->hideOnIndex();
        yield TextField::new('phone', 'Телефон');
        yield TextField::new('telegram', 'Telegram')->hideOnIndex();
        yield EmailField::new('email', 'Email');
        yield BooleanField::new('emailVerified', 'Email подтверждён')->renderAsSwitch(false);
        yield IntegerField::new('rttfRating', 'RTTF')->hideOnIndex();
        yield BooleanField::new('isChampion', 'Чемпион')->renderAsSwitch(false);
        // Переключатель роли админа прямо в списке/карточке (можно тапнуть в таблице).
        yield BooleanField::new('isAdmin', 'Админ');
    }
}
