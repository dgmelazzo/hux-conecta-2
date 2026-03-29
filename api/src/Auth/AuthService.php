<?php

declare(strict_types=1);

namespace CRM\Auth;

use CRM\Shared\Exceptions\ApiException;

class AuthService
{
    public function __construct(
        private readonly \PDO        $pdo,
        private readonly JwtService  $jwt,
    ) {}

    // ── Login ─────────────────────────────────────────────

    public function login(string $email, string $senha, ?string $tenantSlug = null): array
    {
        $usuario = $this->findUsuario($email, $tenantSlug);

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            throw new ApiException('Credenciais inválidas.', 401);
        }

        if (!$usuario['ativo']) {
            throw new ApiException('Usuário inativo. Contate o administrador.', 403);
        }

        // Superadmin não precisa de tenant
        $tenantId = $usuario['tenant_id'] ?? null;

        if ($usuario['role'] !== 'superadmin' && !$tenantId) {
            throw new ApiException('Tenant não encontrado.', 404);
        }

        $this->updateLastLogin($usuario['id']);

        $accessToken  = $this->jwt->generateAccess([
            'user_id'   => $usuario['id'],
            'tenant_id' => $tenantId,
            'role'      => $usuario['role'],
        ]);
        $refreshToken = $this->jwt->generateRefresh();

        $this->saveSession($usuario['id'], (int)($tenantId ?? 0), $refreshToken);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => 900,
            'usuario' => [
                'id'        => $usuario['id'],
                'nome'      => $usuario['nome'],
                'email'     => $usuario['email'],
                'role'      => $usuario['role'],
                'tenant_id' => $tenantId,
            ],
        ];
    }

    // ── Refresh ───────────────────────────────────────────

    public function refresh(string $refreshToken): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, u.role, u.ativo, u.nome, u.email
               FROM sessoes s
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.refresh_token = :token
                AND s.revogado      = 0
                AND s.expira_em    > NOW()
              LIMIT 1"
        );
        $stmt->execute([':token' => $refreshToken]);
        $sessao = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sessao || !$sessao['ativo']) {
            throw new ApiException('Refresh token inválido ou expirado.', 401);
        }

        // Rotacionar refresh token (one-time use)
        $novoRefresh = $this->jwt->generateRefresh();
        $this->pdo->prepare(
            "UPDATE sessoes SET revogado = 1 WHERE refresh_token = :token"
        )->execute([':token' => $refreshToken]);

        $this->saveSession($sessao['usuario_id'], $sessao['tenant_id'], $novoRefresh);

        $accessToken = $this->jwt->generateAccess([
            'user_id'   => $sessao['usuario_id'],
            'tenant_id' => $sessao['tenant_id'],
            'role'      => $sessao['role'],
        ]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $novoRefresh,
            'expires_in'    => 900,
        ];
    }

    // ── Logout ────────────────────────────────────────────

    public function logout(string $refreshToken): void
    {
        $this->pdo->prepare(
            "UPDATE sessoes SET revogado = 1 WHERE refresh_token = :token"
        )->execute([':token' => $refreshToken]);
    }

    // ── Helpers ───────────────────────────────────────────

    private function findUsuario(string $email, ?string $tenantSlug): ?array
    {
        if ($tenantSlug) {
            $stmt = $this->pdo->prepare(
                "SELECT u.* FROM usuarios u
                   JOIN tenants t ON t.id = u.tenant_id
                  WHERE u.email = :email AND t.slug = :slug
                  LIMIT 1"
            );
            $stmt->execute([':email' => $email, ':slug' => $tenantSlug]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM usuarios WHERE email = :email AND role = 'superadmin' LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function saveSession(int $userId, int $tenantId, string $refreshToken): void
    {
        $expira = date('Y-m-d H:i:s', time() + $this->jwt->refreshTtl());
        $this->pdo->prepare(
            "INSERT INTO sessoes (usuario_id, tenant_id, refresh_token, ip, user_agent, expira_em)
             VALUES (:uid, :tid, :token, :ip, :ua, :exp)"
        )->execute([
            ':uid'   => $userId,
            ':tid'   => $tenantId,
            ':token' => $refreshToken,
            ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':exp'   => $expira,
        ]);
    }

    private function updateLastLogin(int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id"
        )->execute([':id' => $userId]);
    }
}
