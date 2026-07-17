<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Стек проекта с РЕАЛЬНЫМИ версиями (нигде не захардкожено):
 * PHP и Symfony отдают их сами, npm-версии читаем из frontend/package-lock.json,
 * composer-пакеты — из composer.lock, MySQL спрашиваем напрямую у базы.
 */
final class StackController extends AbstractController
{
    #[Route('/api/stack', name: 'api_stack', methods: ['GET'])]
    public function index(Connection $connection): JsonResponse
    {
        $npm = $this->npmVersions();
        $composer = $this->composerVersions();

        return $this->json([
            'groups' => [
                [
                    'title' => 'Frontend',
                    'items' => [
                        $this->item('React', 'react', 'https://react.dev', $npm['react'] ?? null),
                        $this->item('TypeScript', 'typescript', 'https://www.typescriptlang.org', $npm['typescript'] ?? null),
                        $this->item('Vite', 'vite', 'https://vite.dev', $npm['vite'] ?? null),
                        $this->item('React Router', 'react', 'https://reactrouter.com', $npm['react-router-dom'] ?? null),
                    ],
                ],
                [
                    'title' => 'Backend',
                    'items' => [
                        $this->item('PHP', 'php', 'https://www.php.net', PHP_VERSION),
                        $this->item('Symfony', 'symfony', 'https://symfony.com', Kernel::VERSION),
                        $this->item('Doctrine ORM', 'doctrine', 'https://www.doctrine-project.org', $composer['doctrine/orm'] ?? null),
                        $this->item('EasyAdmin', 'easyadmin', 'https://github.com/EasyCorp/EasyAdminBundle', $composer['easycorp/easyadmin-bundle'] ?? null),
                        $this->item('MySQL', 'mysql', 'https://www.mysql.com', $this->mysqlVersion($connection)),
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{name: string, logo: string, link: string, version: string}
     */
    private function item(string $name, string $logo, string $link, ?string $version): array
    {
        return [
            'name' => $name,
            'logo' => $logo,
            'link' => $link,
            'version' => $version ?? '—',
        ];
    }

    /**
     * Версии npm-пакетов из frontend/package-lock.json (что реально установлено).
     *
     * @return array<string, string>
     */
    private function npmVersions(): array
    {
        $path = \dirname((string) $this->getParameter('kernel.project_dir')) . '/frontend/package-lock.json';
        if (!is_file($path)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($path), true);
        $result = [];
        foreach (($lock['packages'] ?? []) as $key => $pkg) {
            if (str_starts_with((string) $key, 'node_modules/') && isset($pkg['version'])) {
                $result[substr((string) $key, \strlen('node_modules/'))] = (string) $pkg['version'];
            }
        }

        return $result;
    }

    /**
     * Версии composer-пакетов из composer.lock.
     *
     * @return array<string, string>
     */
    private function composerVersions(): array
    {
        $path = (string) $this->getParameter('kernel.project_dir') . '/composer.lock';
        if (!is_file($path)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($path), true);
        $result = [];
        foreach (($lock['packages'] ?? []) as $pkg) {
            if (isset($pkg['name'], $pkg['version'])) {
                // "v7.4.14" → "7.4.14"
                $result[(string) $pkg['name']] = ltrim((string) $pkg['version'], 'v');
            }
        }

        return $result;
    }

    private function mysqlVersion(Connection $connection): string
    {
        try {
            $version = (string) $connection->executeQuery('SELECT VERSION()')->fetchOne();

            // "8.0.34-26-beget..." → "8.0.34"
            return explode('-', $version)[0];
        } catch (\Throwable) {
            return '—';
        }
    }
}
