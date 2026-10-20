<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Handler nativo de Symfony Security para autenticar peticiones con cabecera:
 * Authorization: Bearer <JWT>
 */
class JwtAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $payload = $this->jwtService->validateAndDecode($accessToken);

        if ($payload === null || empty($payload['sub'])) {
            throw new BadCredentialsException('Token JWT inválido, manipulado o expirado.');
        }

        return new UserBadge((string) $payload['sub']);
    }
}
