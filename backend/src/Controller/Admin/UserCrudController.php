<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\EntryStatus;
use App\Exception\RegistrationException;
use App\Repository\TournamentRepository;
use App\Service\PhoneNormalizer;
use App\Service\RegistrationService;
use App\Service\TournamentSchedule;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        // Быстрая запись игрока на ближайший турнир прямо из списка.
        $toTournament = Action::new('toTournament', 'На турнир', 'fa fa-plus')
            ->linkToCrudAction('registerToTournament');

        return $actions
            ->add(Crud::PAGE_INDEX, $toTournament)
            ->add(Crud::PAGE_DETAIL, $toTournament)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $a) => $a->setLabel('Добавить игрока'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setLabel('Изменить'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->setLabel('Удалить'));
    }

    /**
     * Записать игрока на ближайший (незавершённый) турнир.
     */
    #[AdminRoute(path: '{entityId}/to-tournament', name: 'register_to_tournament')]
    public function registerToTournament(
        AdminContext $context,
        TournamentRepository $tournaments,
        RegistrationService $registration,
        TournamentSchedule $schedule,
        AdminUrlGenerator $urlGenerator,
    ): RedirectResponse {
        $user = $context->getEntity()->getInstance();
        $tournament = $tournaments->findNearestUpcoming();

        if (!$user instanceof User) {
            $this->addFlash('danger', 'Игрок не найден.');
        } elseif ($tournament === null) {
            $this->addFlash('warning', 'Нет ближайшего турнира — сначала создайте турнир.');
        } else {
            try {
                $entry = $registration->register($tournament, $user, ignoreSchedule: true);
                $number = $schedule->number($tournament);
                $where = $entry->getStatus() === EntryStatus::Waitlisted ? 'в очередь ожидания' : 'в участники';
                $this->addFlash('success', "{$user->getName()} записан на турнир #{$number} ({$where}).");
            } catch (RegistrationException $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        return $this->redirect(
            $urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
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

    public function createNewFormBuilder($entityDto, $formOptions, $context): FormBuilderInterface
    {
        return $this->withPhoneNormalization(parent::createNewFormBuilder($entityDto, $formOptions, $context));
    }

    public function createEditFormBuilder($entityDto, $formOptions, $context): FormBuilderInterface
    {
        return $this->withPhoneNormalization(parent::createEditFormBuilder($entityDto, $formOptions, $context));
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
     * Нормализуем телефон ДО валидации (иначе UniqueEntity сравнит сырой ввод
     * с нормализованными номерами в БД и пропустит дубль → 500 при сохранении).
     */
    private function withPhoneNormalization(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (\is_array($data) && \is_string($data['phone'] ?? null)) {
                $normalized = $this->phoneNormalizer->normalize($data['phone']);
                if ($normalized !== null) {
                    $data['phone'] = $normalized;
                    $event->setData($data);
                }
            }
        });

        return $builder;
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
