<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationLog;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * История рассылок (только просмотр).
 */
final class NotificationLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Рассылка')
            ->setEntityLabelInPlural('Рассылки')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Рассылки создаются системой, руками не редактируем — только смотрим.
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $a) => $a->setLabel('Открыть'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Когда');
        yield TextField::new('subject', 'Тема');
        yield IntegerField::new('recipientCount', 'Получателей');
        yield AssociationField::new('tournament', 'Турнир');
        yield TextField::new('type', 'Тип')->hideOnIndex();
    }
}
