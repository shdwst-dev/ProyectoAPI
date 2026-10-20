<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Servicio de generación y verificación de JSON Web Tokens (JWT - RFC 7519).
 * Utiliza firma HMAC-SHA256 (HS256) y comparación en tiempo constante (hash_equals)
 * para mitigar ataques de temporización (Timing Attacks).
 */
class JwtService
{
    public function __construct(
        #[Autowire('%env(JWT_SECRET)%')]
        private readonly string $jwtSecret,
        #[Autowire('%env(int:JWT_TTL)%')]
        private readonly int $jwtTtl = 86400,
    ) {
    }

    /**
     * Genera un token JWT firmado para un usuario autenticado.
     */
    public function createToken(User $user): string
    {
        $now = time();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $user->getUserIdentifier(),
            'uid' => $user->getId(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'iat' => $now,
            'exp' => $now + $this->jwtTtl,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->jwtSecret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return sprintf('%s.%s.%s', $encodedHeader, $encodedPayload, $encodedSignature);
    }

    /**
     * Valida la firma, algoritmo y expiración del JWT.
     *
     * @return array<string, mixed>|null Retorna el payload si es válido, o null si es inválido/expirado
     */
    public function validateAndDecode(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);

        if ($headerJson === false || $payloadJson === false) {
            return null;
        }

        try {
            $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        // Prevenir ataque "alg: none" verificando explícitamente HS256
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        // Recalcular firma HMAC-SHA256 y comparar en tiempo constante
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        // Validar tiempo de expiración (exp)
        if (!is_array($payload) || !isset($payload['exp'], $payload['sub']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
