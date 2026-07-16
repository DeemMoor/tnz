<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Точка входа в админку (EasyAdmin) на /admin.
 * Доступ ограничен ROLE_ADMIN через security.yaml (access_control ^/admin).
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        // Вместо дефолтной заглушки EasyAdmin — сразу список турниров.
        return $this->redirect(
            $this->adminUrlGenerator->setController(TournamentCrudController::class)->generateUrl(),
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Теннис на Новой Земле — админка');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Дашборд', 'fa fa-home');
        yield MenuItem::linkTo(TournamentCrudController::class, 'Турниры', 'fa fa-trophy');
        yield MenuItem::linkTo(UserCrudController::class, 'Игроки', 'fa fa-users');
    }
}
