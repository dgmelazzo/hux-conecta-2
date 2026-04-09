<?php declare(strict_types=1);

// ============================================================
// AuthController
// POST /api/auth/login
// POST /api/auth/refresh
// POST /api/auth/logout
// GET  /api/auth/me
// ============================================================

namespace CRM\Auth;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class AuthController
{
    public function __construct(
        private readonly AuthService $service,
    ) {}

    public function login(Request $request): void
    {
        $data = $request->json();
        $this->validate($data, ['email', 'senha']);

        $result = $this->service->login(
            email:       $data['email'],
            senha:       $data['senha'],
            tenantSlug:  $data['tenant'] ?? null,
        );

        Response::json($result);
    }

    public function refresh(Request $request): void
    {
        $data = $request->json();
        $this->validate($data, ['refresh_token']);

        Response::json($this->service->refresh($data['refresh_token']));
    }

    public function logout(Request $request): void
    {
        $data = $request->json();
        if (!empty($data['refresh_token'])) {
            $this->service->logout($data['refresh_token']);
        }
        Response::json(['ok' => true]);
    }

    public function me(Request $request): void
    {
        $user = $request->user();
        Response::json(['usuario' => $user]);
    }

    private function validate(array $data, array $fields): void
    {
        foreach ($fields as $f) {
            if (empty($data[$f])) {
                \CRM\Shared\Http\Response::json(['error' => "Campo '{$f}' obrigatório."], 422);
                exit;
            }
        }
    }
}


// ============================================================
// AuthMiddleware — valida JWT em todas as rotas protegidas
// ============================================================

namespace CRM\Auth;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class AuthMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, callable $next): void
    {
        $header = $request->header('Authorization') ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            Response::json(['error' => 'Token não fornecido.'], 401);
            return;
        }

        $token = substr($header, 7);

        try {
            $payload = $this->jwt->decode($token);
            $request->setUser([
                'id'        => $payload['sub'],
                'tenant_id' => $payload['tenant_id'],
                'role'      => $payload['role'],
            ]);
            $next($request);
        } catch (\Exception) {
            Response::json(['error' => 'Token inválido ou expirado.'], 401);
        }
    }
}


// ============================================================
// TenantMiddleware — resolve e injeta tenant_id em toda request
// Suporta: header X-Tenant (slug) ou subdomínio
// ============================================================

namespace CRM\Tenant;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class TenantMiddleware
{
    public function __construct(private readonly \PDO $pdo) {}

    public function handle(Request $request, callable $next): void
    {
        // Superadmin pode operar sem tenant
        $user = $request->user();
        if (($user['role'] ?? '') === 'superadmin') {
            $next($request);
            return;
        }

        $tenantId = $user['tenant_id'] ?? null;

        if (!$tenantId) {
            Response::json(['error' => 'Tenant não identificado.'], 403);
            return;
        }

        // Verificar se tenant existe e está ativo
        $stmt = $this->pdo->prepare(
            "SELECT id, slug, nome, ativo FROM tenants WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tenant || !$tenant['ativo']) {
            Response::json(['error' => 'Tenant inativo ou não encontrado.'], 403);
            return;
        }

        $request->setTenant($tenant);
        $next($request);
    }
}


// ============================================================
// RbacMiddleware — controle de acesso por role
// Uso: new RbacMiddleware(['gestor', 'superadmin'])
// ============================================================

namespace CRM\Auth;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class RbacMiddleware
{
    /** Hierarquia: superadmin > gestor > atendente */
    private const HIERARCHY = ['atendente' => 1, 'gestor' => 2, 'superadmin' => 3];

    /** @param string[] $rolesPermitidas */
    public function __construct(private readonly array $rolesPermitidas) {}

    public function handle(Request $request, callable $next): void
    {
        $role      = $request->user()['role'] ?? '';
        $nivel     = self::HIERARCHY[$role]   ?? 0;
        $minNivel  = min(array_map(fn($r) => self::HIERARCHY[$r] ?? 99, $this->rolesPermitidas));

        if ($nivel < $minNivel) {
            Response::json(['error' => 'Acesso negado. Permissão insuficiente.'], 403);
            return;
        }

        $next($request);
    }
}
