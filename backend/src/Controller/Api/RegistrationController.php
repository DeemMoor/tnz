<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Регистрация игрока по телефону + паролю.
 */
final class RegistrationController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        PhoneNormalizer $phoneNormalizer,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $rawPhone = \is_string($data['phone'] ?? null) ? $data['phone'] : '';
        $password = \is_string($data['password'] ?? null) ? $data['password'] : '';
        $name = \is_string($data['name'] ?? null) ? trim($data['name']) : '';

        $errors = [];

        $phone = $phoneNormalizer->normalize($rawPhone);
        if ($phone === null) {
            $errors['phone'] = 'Укажите мобильный в формате +7 9XX XXX-XX-XX';
        }
        if (mb_strlen($password) < 6) {
            $errors['password'] = 'Пароль минимум 6 символов';
        }
        if ($name === '') {
            $errors['name'] = 'Укажите имя';
        }

        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($users->findOneByPhone($phone) !== null) {
            return $this->json(
                ['errors' => ['phone' => 'Этот телефон уже зарегистрирован']],
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User();
        $user->setPhone($phone);
        $user->setName($name);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'id' => $user->getId(),
            'phone' => $user->getPhone(),
            'name' => $user->getName(),
        ], Response::HTTP_CREATED);
    }
}
