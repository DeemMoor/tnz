<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tournament;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Точка входа в админку (EasyAdmin) на /admin.
 * Доступ ограничен ROLE_ADMIN через security.yaml (access_control ^/admin).
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Теннис на Новой Земле — админка');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Дашборд', 'fa fa-home');
        yield MenuItem::linkToCrud('Турниры', 'fa fa-trophy', Tournament::class);
        yield MenuItem::linkToCrud('Игроки', 'fa fa-users', User::class);
    }
}
