<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    /**
     * Lista todos los usuarios registrados (Solo ROLE_ADMIN).
     * Utiliza $user->toArray() para no exponer hashes de contraseñas.
     */
    #[Route('', name: 'api_users_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findBy([], ['id' => 'ASC']);

        return $this->json([
            'count' => count($users),
            'users' => array_map(static fn (User $u) => $u->toArray(), $users),
        ]);
    }
}
