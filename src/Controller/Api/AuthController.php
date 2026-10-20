<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/status', name: 'api_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'project' => 'Tienda en Línea - API Segura en Symfony',
            'status' => 'online',
            'securityControls' => [
                'authentication' => 'JWT Bearer Token (HMAC-SHA256 / RFC 7519)',
                'passwordHashing' => 'Symfony PasswordHasher (Bcrypt / Argon2id)',
                'authorization' => 'RBAC (ROLE_USER, ROLE_ADMIN) + Protección BOLA/IDOR en pedidos',
                'dataProtection' => 'Prevención de Mass Assignment y Exposición Excesiva de Datos',
                'sqlInjection' => 'Consultas parametrizadas con Doctrine ORM',
            ],
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Inicio de sesión y emisión de Token JWT.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JwtService $jwtService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'El cuerpo de la petición debe ser un JSON válido.'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['error' => 'Debes proporcionar email y password.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        // SEGURIDAD: Mensaje genérico para evitar enumeración de usuarios (User Enumeration)
        if ($user === null || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'error' => 'Credenciales inválidas. Verifica tu correo y contraseña.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $jwtService->createToken($user);

        return $this->json([
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $user->toArray(),
        ]);
    }

    /**
     * Registro de nuevos clientes.
     * Previene Mass Assignment (OWASP API3): aunque el cliente envíe "roles": ["ROLE_ADMIN"]
     * en el JSON, el controlador ignora ese campo y asigna únicamente ROLE_USER.
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        JwtService $jwtService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'El cuerpo de la petición debe ser un JSON válido.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $plainPassword = (string) ($data['password'] ?? '');

        if (strlen($plainPassword) < 6) {
            return $this->json([
                'error' => 'La contraseña debe tener al menos 6 caracteres.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json([
                'error' => 'Este correo electrónico ya está registrado.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        // Forzar siempre rol de cliente estándar (Mitigación de Mass Assignment / Escalada de privilegios)
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Datos de registro inválidos.',
                'details' => $messages,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->persist($user);
        $em->flush();

        $token = $jwtService->createToken($user);

        return $this->json([
            'message' => 'Usuario registrado exitosamente.',
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => $user->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Devuelve el perfil del usuario autenticado mediante el token JWT.
     */
    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user' => $user->toArray(),
        ]);
    }
}
