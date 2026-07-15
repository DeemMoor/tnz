<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tournament;
use App\Enum\TournamentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Управление турнирами в админке: создать, задать дату (воскресенье), статус.
 */
final class TournamentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tournament::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Турнир')
            ->setEntityLabelInPlural('Турниры')
            ->setDefaultSort(['date' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
        yield DateField::new('date', 'Дата (воскресенье)');
        yield ChoiceField::new('status', 'Статус')
            ->setChoices(array_combine(
                array_map(static fn (TournamentStatus $s) => $s->name, TournamentStatus::cases()),
                TournamentStatus::cases(),
            ))
            ->renderAsBadges();
        yield IntegerField::new('id', 'Записей')
            ->formatValue(fn ($v, Tournament $t) => $t->getEntries()->count())
            ->onlyOnIndex();
    }
}
