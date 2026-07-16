<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Отдаёт собранный React-фронт (index.html) на все «страничные» адреса,
 * которые не перехватили API/админка/статика. Дальше роут разруливает React.
 *
 * priority: -100 — срабатывает последним, когда ничего другое не подошло.
 * Регексп исключает api/admin/bundles/assets, чтобы они 404-или штатно,
 * а не подменялись SPA.
 */
final class SpaController extends AbstractController
{
    #[Route('/', name: 'spa_home', methods: ['GET'], priority: -100)]
    #[Route(
        '/{path}',
        name: 'spa_fallback',
        requirements: ['path' => '^(?!api|admin|bundles|assets|uploads|_(profiler|wdt)).+'],
        methods: ['GET'],
        priority: -100,
    )]
    public function index(): Response
    {
        $index = $this->getParameter('kernel.project_dir') . '/public/index.html';

        if (!is_file($index)) {
            return new Response(
                'React ещё не собран. Выполните: cd frontend && npm run build',
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain; charset=utf-8'],
            );
        }

        return new Response(
            (string) file_get_contents($index),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html'],
        );
    }
}
