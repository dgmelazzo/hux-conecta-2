<?php

declare(strict_types=1);

namespace CRM\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private int    $accessTtl;
    private int    $refreshTtl;
    private const  ALG = 'HS256';

    public function __construct()
    {
        $this->secret     = $_ENV['JWT_SECRET']      ?? throw new \RuntimeException('JWT_SECRET não definido.');
        $this->accessTtl  = (int)($_ENV['JWT_ACCESS_TTL']  ?? 900);
        $this->refreshTtl = (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000);
    }

    public function generateAccess(array $payload): string
    {
        $now = time();
        return JWT::encode([
            'iss'       => 'conecta-crm',
            'iat'       => $now,
            'exp'       => $now + $this->accessTtl,
            'sub'       => $payload['user_id'],
            'tenant_id' => $payload['tenant_id'],
            'role'      => $payload['role'],
        ], $this->secret, self::ALG);
    }

    public function generateRefresh(): string
    {
        return bin2hex(random_bytes(64));
    }

    /** @return array<string, mixed> @throws \Exception */
    public function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret, self::ALG));
    }

    public function refreshTtl(): int { return $this->refreshTtl; }
}
