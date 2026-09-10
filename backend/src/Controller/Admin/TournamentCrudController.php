<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tournament;
use App\Enum\TournamentStatus;
use App\Service\NotificationService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

    public function configureActions(Actions $actions): Actions
    {
        // Кнопка ручной рассылки анонса регистрации.
        $announce = Action::new('announce', 'Разослать анонс', 'fa fa-envelope')
            ->linkToCrudAction('announce');

        // Открыть запись досрочно, не дожидаясь четверга 16:00.
        $openNow = Action::new('openRegistrationNow', 'Открыть запись сейчас', 'fa fa-unlock')
            ->linkToCrudAction('openRegistrationNow');

        return $actions
            ->add(Crud::PAGE_INDEX, $announce)
            ->add(Crud::PAGE_INDEX, $openNow)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Создать турнир'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setLabel('Изменить'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->setLabel('Удалить'));
    }

    /**
     * Ручная рассылка анонса регистрации выбранного турнира.
     */
    #[AdminRoute(path: '{entityId}/announce', name: 'announce')]
    public function announce(
        AdminContext $context,
        NotificationService $notifications,
        AdminUrlGenerator $urlGenerator,
    ): RedirectResponse {
        $tournament = $context->getEntity()->getInstance();
        if ($tournament instanceof Tournament) {
            $sent = $notifications->announceRegistrationOpen($tournament);
            $this->addFlash('success', "Анонс разослан. Писем отправлено: {$sent}.");
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    /**
     * Открыть запись на турнир прямо сейчас: проставляем ручное время открытия
     * текущим моментом, и TournamentSchedule сразу считает регистрацию открытой.
     */
    #[AdminRoute(path: '{entityId}/open-registration-now', name: 'openRegistrationNow')]
    public function openRegistrationNow(
        AdminContext $context,
        EntityManagerInterface $em,
        ClockInterface $clock,
        AdminUrlGenerator $urlGenerator,
    ): RedirectResponse {
        $tournament = $context->getEntity()->getInstance();
        if ($tournament instanceof Tournament) {
            $tournament->setRegistrationOpensAt($clock->now());
            if ($tournament->getStatus() === TournamentStatus::Draft) {
                $tournament->setStatus(TournamentStatus::Registration);
            }
            $em->flush();
            $this->addFlash('success', 'Запись открыта — прямо сейчас.');
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Название');
        yield DateField::new('date', 'Дата (воскресенье)');
        yield DateTimeField::new('registrationOpensAt', 'Запись открывается')
            ->setHelp('Пусто — по умолчанию: четверг перед турниром, 16:00. Заполнить, чтобы открыть запись раньше или позже.');
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
