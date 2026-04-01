<?php
declare(strict_types=1);

// ============================================================
// Conecta CRM API — v2.3
// VPS: /var/www/hux-crm-association/api/public/index.php
// Schema alinhado com banco real (conecta_crm)
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

set_exception_handler(function(\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['ok' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
    exit;
});
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Tenant');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ============================================================
// .ENV LOADER
// ============================================================
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

date_default_timezone_set('America/Sao_Paulo');

// ============================================================
// HELPERS — todos declarados ANTES das rotas
// ============================================================

function pdo(): PDO {
    static $conn = null;
    if ($conn) return $conn;
    $conn = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_NAME'] ?? 'conecta_crm'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $conn;
}

function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    if ($code >= 400) {
        // Erros: { ok: false, error: "..." }
        $out = ['ok' => false, 'error' => $data['error'] ?? 'Erro desconhecido'];
    } else {
        // Sucesso: { ok: true, data: ... }
        $out = ['ok' => true, 'data' => $data];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array {
    static $parsed = null;
    if ($parsed !== null) return $parsed;
    $parsed = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    return $parsed;
}

function required_fields(array $fields): void {
    $b = body();
    $missing = array_filter($fields, fn($f) => !isset($b[$f]) || $b[$f] === '');
    if ($missing) json_out(['error' => 'Campos obrigatórios: ' . implode(', ', array_values($missing))], 422);
}

function tenant_id(): int {
    // Para MVP ACIC-DF: tenant fixo = 1
    // Futuramente: resolver pelo subdomínio ou header X-Tenant
    return (int)($_ENV['DEFAULT_TENANT_ID'] ?? 1);
}

// ── JWT ──────────────────────────────────────────────────────────────────────

function jwt_encode(array $payload): string {
    $secret  = $_ENV['JWT_SECRET'] ?? 'conecta_crm_secret_change_me_32c';
    $header  = base64url_enc(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 86400 * 7;
    $body    = base64url_enc(json_encode($payload));
    $sig     = base64url_enc(hash_hmac('sha256', "$header.$body", $secret, true));
    return "$header.$body.$sig";
}

function jwt_decode(string $token): ?array {
    $secret = $_ENV['JWT_SECRET'] ?? 'conecta_crm_secret_change_me_32c';
    $parts  = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $b, $sig] = $parts;
    $expected = base64url_enc(hash_hmac('sha256', "$h.$b", $secret, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_dec($b), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function base64url_enc(string $d): string {
    return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
}

function base64url_dec(string $d): string {
    return base64_decode(strtr($d, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($d)) % 4));
}

function auth_required(): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $h, $m)) json_out(['error' => 'Token não fornecido'], 401);
    $p = jwt_decode($m[1]);
    if (!$p) json_out(['error' => 'Token inválido ou expirado'], 401);
    return $p;
}

function require_role(array $payload, array $roles): void {
    if (!in_array($payload['role'] ?? '', $roles)) {
        json_out(['error' => 'Permissão insuficiente'], 403);
    }
}

// ── CONECTA 2.0 HELPERS ──────────────────────────────────────────────────────

function conectaApi(string $action, array $data): array {
    $url = 'https://acicdf.org.br/conecta/auth.php?action=' . $action;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    error_log("[CRM-CONECTA] action=$action code=$code err=$err body=" . substr($resp ?: '', 0, 500));
    $decoded = json_decode($resp ?: '{}', true) ?? [];
    $decoded['_http'] = $code;
    $decoded['_ok']   = $code < 400 && (($decoded['success'] ?? false) || ($decoded['ok'] ?? false) || !empty($decoded['id']) || !empty($decoded['token']));
    return $decoded;
}

function conectaSyncUser(string $doc, string $nome): void {
    $secret = $_ENV['CRM_SECRET'] ?? '';
    if (!$doc || !$secret) return;
    // Check if user exists in Conecta 2.0
    $check = conectaApi('check', ['cpf_cnpj' => $doc, 'secret' => $secret]);
    if (!($check['exists'] ?? false) && !($check['_ok'] ?? false)) {
        // Register in Conecta 2.0
        conectaApi('register_from_crm', [
            'cpf_cnpj' => $doc,
            'nome'     => $nome,
            'secret'   => $secret,
        ]);
    }
}

// ── ASAAS ────────────────────────────────────────────────────────────────────

function asaasReq(string $method, string $path, array $data = []): array {
    $apiKey  = $_ENV['ASAAS_API_KEY'] ?? '';
    $baseUrl = ($_ENV['ASAAS_ENV'] ?? 'sandbox') === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://sandbox.asaas.com/api/v3';

    $ch = curl_init($baseUrl . $path);
    $headers = [
        'Content-Type: application/json',
        'access_token: ' . $apiKey,
        'User-Agent: ConectaCRM/2.2',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => $err, '_http' => 0];
    $decoded = json_decode($response ?: '{}', true) ?? [];
    $decoded['_http'] = $code;
    return $decoded;
}

// ── Busca customer_id do Asaas para o associado ───────────────────────────────

function asaasEnsureCustomer(array $assoc): string {
    // Verifica se já tem customer_id salvo em campos_extras
    $extras = json_decode($assoc['campos_extras'] ?? '{}', true) ?? [];
    if (!empty($extras['asaas_customer_id'])) return $extras['asaas_customer_id'];

    $doc = preg_replace('/\D/', '', $assoc['cnpj'] ?? $assoc['cpf'] ?? '');
    $res = asaasReq('POST', '/customers', [
        'name'    => $assoc['nome_fantasia'] ?? $assoc['razao_social'] ?? 'Associado',
        'cpfCnpj' => $doc,
        'email'   => $assoc['email'] ?? null,
        'phone'   => $assoc['whatsapp'] ?? $assoc['telefone'] ?? null,
    ]);

    if (empty($res['id'])) json_out(['error' => 'Erro ao criar customer no Asaas', 'detail' => $res], 502);

    // Salva customer_id nos campos_extras
    $extras['asaas_customer_id'] = $res['id'];
    pdo()->prepare('UPDATE associados SET campos_extras = ? WHERE id = ?')
         ->execute([json_encode($extras), $assoc['id']]);

    return $res['id'];
}

// ============================================================
// ROTEADOR
// ============================================================

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';

// ── Health check ─────────────────────────────────────────────────────────────
if ($uri === '/' || $uri === '/api') {
    // Testa conexão com banco
    $dbOk = false;
    try { pdo()->query('SELECT 1'); $dbOk = true; } catch (\Throwable) {}
    json_out([
        'status'  => 'ok',
        'app'     => 'Conecta CRM API',
        'version' => '2.3.0',
        'php'     => PHP_VERSION,
        'db'      => $dbOk ? 'ok' : 'error',
        'time'    => date('c'),
    ]);
}

// ============================================================
// AUTH — login de usuários internos (gestor/atendente) e
//        associados via CPF/CNPJ (SSO Conecta 2.0)
// POST /auth/login  { email, senha } ou { documento, senha }
// ============================================================
if ($method === 'POST' && $uri === '/auth/login') {
    $b = body();
    $senha = $b['senha'] ?? '';
    $login = trim($b['email'] ?? $b['documento'] ?? '');

    if (!$login || !$senha) {
        json_out(['error' => 'Preencha login e senha.'], 422);
    }

    // Detecta se é CPF/CNPJ (dígitos, pontos, traços, barras) ou e-mail
    $docClean = preg_replace('/\D/', '', $login);
    $isCpfCnpj = !str_contains($login, '@') && strlen($docClean) >= 11;

    if ($isCpfCnpj) {
        // ── LOGIN VIA CPF/CNPJ — fluxo em 3 etapas via Conecta 2.0 ──

        // STEP 1: Check — verifica se o documento existe no Conecta 2.0
        $checkResp = conectaApi('check', ['cpf_cnpj' => $docClean]);
        error_log("[CRM-SSO] check doc=$docClean: ok=" . ($checkResp['_ok'] ? 'Y' : 'N') . " http=" . $checkResp['_http']);

        if (!$checkResp['_ok'] && ($checkResp['_http'] ?? 0) >= 400) {
            json_out(['error' => 'CPF/CNPJ não encontrado como associado da ACIC-DF.'], 404);
        }

        $checkData = $checkResp['data'] ?? $checkResp;

        // STEP 2: Se primeiro_acesso=true → retorna para frontend criar senha
        $primeiroAcesso = ($checkData['primeiro_acesso'] ?? false);
        if ($primeiroAcesso) {
            $nome = $checkData['nome'] ?? '';
            // Tenta complementar nome do CRM local se Conecta não trouxe
            if (!$nome) {
                $fld2  = strlen($docClean) === 14 ? 'cnpj' : 'cpf';
                $st2   = pdo()->prepare("SELECT nome_fantasia, razao_social, nome_responsavel FROM associados WHERE $fld2 = ? AND tenant_id = ? LIMIT 1");
                $st2->execute([$docClean, tenant_id()]);
                $a2 = $st2->fetch();
                $nome = $a2['nome_fantasia'] ?? $a2['razao_social'] ?? $a2['nome_responsavel'] ?? '';
            }
            json_out([
                'primeiro_acesso' => true,
                'documento'       => $docClean,
                'nome'            => $nome,
            ]);
        }

        // STEP 3: primeiro_acesso=false → tenta login com senha
        $loginResp = conectaApi('login', ['cpf_cnpj' => $docClean, 'password' => $senha]);
        error_log("[CRM-SSO] login doc=$docClean: ok=" . ($loginResp['_ok'] ? 'Y' : 'N') . " http=" . $loginResp['_http']);

        if (!$loginResp['_ok']) {
            json_out(['error' => 'Credenciais inválidas'], 401);
        }

        // Login OK — busca associado no CRM
        $tid   = tenant_id();
        $field = strlen($docClean) === 14 ? 'cnpj' : 'cpf';
        $stmt  = pdo()->prepare("SELECT * FROM associados WHERE $field = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$docClean, $tid]);
        $assoc = $stmt->fetch();

        if (!$assoc) {
            json_out(['error' => 'Associado não encontrado no CRM. Entre em contato com a associação.'], 404);
        }

        // Determina role com base na categoria do associado
        $categoria = $assoc['categoria'] ?? 'empresa';
        $role = in_array($categoria, ['colaborador', 'dependente']) ? 'colaborador' : 'associado_empresa';

        // Token SSO do Conecta 2.0 (para Portal do Associado)
        $conectaToken = $loginResp['data']['token'] ?? $loginResp['token'] ?? null;

        $token = jwt_encode([
            'sub'           => $assoc['id'],
            'documento'     => $docClean,
            'nome'          => $assoc['nome_fantasia'] ?? $assoc['razao_social'] ?? $assoc['nome_responsavel'],
            'email'         => $assoc['email'],
            'role'          => $role,
            'tenant_id'     => $tid,
            'associado_id'  => (int)$assoc['id'],
            'sso'           => true,
        ]);

        $response = [
            'token' => $token,
            'user'  => [
                'id'           => $assoc['id'],
                'nome'         => $assoc['nome_fantasia'] ?? $assoc['razao_social'] ?? $assoc['nome_responsavel'],
                'email'        => $assoc['email'],
                'role'         => $role,
                'associado_id' => (int)$assoc['id'],
            ],
        ];
        if ($conectaToken) {
            $response['conecta_token'] = $conectaToken;
        }

        json_out($response);
    }

    // ── LOGIN VIA E-MAIL — fluxo original (usuarios internos) ──
    $stmt = pdo()->prepare(
        'SELECT * FROM usuarios WHERE email = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1'
    );
    $stmt->execute([$login, tenant_id()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha, $user['senha_hash'])) {
        json_out(['error' => 'Credenciais inválidas'], 401);
    }
    if (!$user['ativo']) json_out(['error' => 'Usuário inativo'], 403);

    pdo()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$user['id']]);

    $token = jwt_encode([
        'sub'       => $user['id'],
        'email'     => $user['email'],
        'nome'      => $user['nome'],
        'role'      => $user['role'],
        'tenant_id' => $user['tenant_id'],
    ]);

    json_out([
        'token' => $token,
        'user'  => [
            'id'    => $user['id'],
            'nome'  => $user['nome'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ]);
}

// ── GET /auth/me ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $uri === '/auth/me') {
    json_out(auth_required());
}

// ── POST /auth/primeiro-acesso — cria senha no Conecta 2.0 (público) ─────────
if ($method === 'POST' && $uri === '/auth/primeiro-acesso') {
    $b = body();
    $doc   = preg_replace('/\D/', '', $b['documento'] ?? '');
    $senha = $b['senha'] ?? '';

    if (!$doc || strlen($doc) < 11) json_out(['error' => 'Documento inválido'], 422);
    if (strlen($senha) < 8) json_out(['error' => 'A senha deve ter no mínimo 8 caracteres'], 422);

    // Chama Conecta 2.0 para definir senha no primeiro acesso
    $resp = conectaApi('first', ['cpf_cnpj' => $doc, 'password' => $senha]);

    if (!$resp['_ok']) {
        json_out(['error' => $resp['message'] ?? 'Erro ao criar senha no Conecta 2.0'], 502);
    }

    // Find or create associado no CRM
    $tid   = tenant_id();
    $field = strlen($doc) === 14 ? 'cnpj' : 'cpf';
    $stmt  = pdo()->prepare("SELECT * FROM associados WHERE $field = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$doc, $tid]);
    $assoc = $stmt->fetch();

    if (!$assoc) {
        // Cria associado automaticamente
        $nome = $resp['nome'] ?? $b['nome'] ?? 'Associado';
        pdo()->prepare(
            "INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, $field, status, criado_em)
             VALUES (?, ?, ?, ?, 'ativo', NOW())"
        )->execute([$tid, strlen($doc) === 14 ? 'pj' : 'pf', $nome, $doc]);
        $assocId = (int)pdo()->lastInsertId();
        $stmt->execute([$doc, $tid]);
        $assoc = $stmt->fetch();
    }

    $assocId  = (int)$assoc['id'];
    $categoria = $assoc['categoria'] ?? 'empresa';
    $role = in_array($categoria, ['colaborador', 'dependente']) ? 'colaborador' : 'associado_empresa';

    $token = jwt_encode([
        'sub'          => $assocId,
        'documento'    => $doc,
        'nome'         => $assoc['nome_fantasia'] ?? $assoc['razao_social'] ?? $assoc['nome_responsavel'],
        'email'        => $assoc['email'],
        'role'         => $role,
        'tenant_id'    => $tid,
        'associado_id' => $assocId,
        'sso'          => true,
    ]);

    $conectaToken = $resp['token'] ?? $resp['sso_token'] ?? null;

    $response = [
        'token' => $token,
        'user'  => [
            'id'           => $assocId,
            'nome'         => $assoc['nome_fantasia'] ?? $assoc['razao_social'] ?? $assoc['nome_responsavel'],
            'email'        => $assoc['email'],
            'role'         => $role,
            'associado_id' => $assocId,
        ],
    ];
    if ($conectaToken) $response['conecta_token'] = $conectaToken;

    json_out($response);
}

// ── POST /auth/criar-usuario — cria gestor/atendente (só superadmin/gestor) ───
if ($method === 'POST' && $uri === '/auth/criar-usuario') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    required_fields(['nome', 'email', 'senha', 'role']);
    $b = body();

    if (!in_array($b['role'], ['gestor', 'atendente', 'associado_empresa', 'colaborador'])) {
        json_out(['error' => 'Role inválida. Use: gestor, atendente, associado_empresa ou colaborador'], 422);
    }

    $check = pdo()->prepare('SELECT id FROM usuarios WHERE email = ? AND tenant_id = ?');
    $check->execute([$b['email'], tenant_id()]);
    if ($check->fetch()) json_out(['error' => 'E-mail já cadastrado'], 409);

    pdo()->prepare(
        'INSERT INTO usuarios (tenant_id, nome, email, senha_hash, role, ativo, criado_em)
         VALUES (?, ?, ?, ?, ?, 1, NOW())'
    )->execute([tenant_id(), $b['nome'], $b['email'], password_hash($b['senha'], PASSWORD_DEFAULT), $b['role']]);

    json_out(['message' => 'Usuário criado', 'id' => pdo()->lastInsertId()], 201);
}

// ============================================================
// ASSOCIADOS
// ============================================================

// GET /associados
if ($method === 'GET' && $uri === '/associados') {
    $p = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    // associado_empresa e colaborador: retornam apenas seu próprio registro
    if (in_array($p['role'] ?? '', ['associado_empresa', 'colaborador'])) {
        $assocId = $p['associado_id'] ?? $p['sub'];
        $stmt = pdo()->prepare(
            'SELECT a.id, a.tipo_pessoa, a.categoria, a.razao_social, a.nome_fantasia,
                    a.cpf, a.cnpj, a.email, a.telefone, a.whatsapp,
                    a.cidade, a.uf, a.status, a.data_associacao, a.data_vencimento,
                    a.conecta_user_id, a.criado_em,
                    p.nome AS plano_nome, p.valor AS plano_valor
             FROM associados a
             LEFT JOIN planos p ON p.id = a.plano_id
             WHERE a.id = ? AND a.tenant_id = ?'
        );
        $stmt->execute([$assocId, $tid]);
        $row = $stmt->fetch();
        json_out([
            'data'  => $row ? [$row] : [],
            'total' => $row ? 1 : 0,
            'page'  => 1,
            'limit' => 20,
            'pages' => 1,
        ]);
    }

    $where  = 'a.tenant_id = ?';
    $params = [$tid];

    if (!empty($_GET['busca'])) {
        $like     = '%' . $_GET['busca'] . '%';
        $where   .= ' AND (a.nome_fantasia LIKE ? OR a.razao_social LIKE ? OR a.cnpj LIKE ? OR a.cpf LIKE ?)';
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }
    if (!empty($_GET['status'])) {
        $where   .= ' AND a.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['plano_id'])) {
        $where   .= ' AND a.plano_id = ?';
        $params[] = $_GET['plano_id'];
    }
    if (!empty($_GET['categoria'])) {
        $where   .= ' AND a.categoria = ?';
        $params[] = $_GET['categoria'];
    }

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 20));
    $off   = ($page - 1) * $limit;

    $stmtT = pdo()->prepare("SELECT COUNT(*) FROM associados a WHERE $where");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetchColumn();

    $stmt = pdo()->prepare(
        "SELECT a.id, a.tipo_pessoa, a.categoria, a.razao_social, a.nome_fantasia,
                a.cpf, a.cnpj, a.email, a.telefone, a.whatsapp,
                a.cidade, a.uf, a.status, a.data_associacao, a.data_vencimento,
                a.conecta_user_id, a.criado_em,
                p.nome AS plano_nome, p.valor AS plano_valor
         FROM associados a
         LEFT JOIN planos p ON p.id = a.plano_id
         WHERE $where
         ORDER BY a.nome_fantasia, a.razao_social
         LIMIT $limit OFFSET $off"
    );
    $stmt->execute($params);

    json_out([
        'data'  => $stmt->fetchAll(),
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

// GET /associados/{id}
if ($method === 'GET' && preg_match('#^/associados/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    // associado_empresa e colaborador: apenas seu próprio registro
    if (in_array($p['role'] ?? '', ['associado_empresa', 'colaborador'])) {
        $ownId = $p['associado_id'] ?? $p['sub'];
        if ((int)$m[1] !== (int)$ownId) {
            json_out(['error' => 'Permissão insuficiente'], 403);
        }
    }

    $stmt = pdo()->prepare(
        'SELECT a.*, p.nome AS plano_nome, p.valor AS plano_valor
         FROM associados a
         LEFT JOIN planos p ON p.id = a.plano_id
         WHERE a.id = ? AND a.tenant_id = ?'
    );
    $stmt->execute([$m[1], $tid]);
    $row = $stmt->fetch();
    if (!$row) json_out(['error' => 'Associado não encontrado'], 404);
    json_out($row);
}

// POST /associados
if ($method === 'POST' && $uri === '/associados') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    required_fields(['nome_fantasia']);
    $b = body();

    // Verifica duplicidade por CNPJ ou CPF
    $doc = preg_replace('/\D/', '', $b['cnpj'] ?? $b['cpf'] ?? '');
    if ($doc) {
        $field = strlen($doc) === 14 ? 'cnpj' : 'cpf';
        $chk = pdo()->prepare("SELECT id FROM associados WHERE $field = ? AND tenant_id = ?");
        $chk->execute([$doc, $tid]);
        if ($chk->fetch()) json_out(['error' => 'Documento já cadastrado'], 409);
    }

    pdo()->prepare(
        'INSERT INTO associados
         (tenant_id, tipo_pessoa, categoria, razao_social, nome_fantasia, nome_responsavel,
          cpf, cnpj, email, telefone, whatsapp, cep, logradouro, numero, complemento,
          bairro, cidade, uf, status, plano_id, data_associacao, conecta_user_id, criado_por, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $tid,
        $b['tipo_pessoa']     ?? 'pj',
        $b['categoria']       ?? 'empresa',
        $b['razao_social']    ?? null,
        $b['nome_fantasia'],
        $b['nome_responsavel']?? null,
        isset($b['cpf'])  ? preg_replace('/\D/', '', $b['cpf'])  : null,
        isset($b['cnpj']) ? preg_replace('/\D/', '', $b['cnpj']) : null,
        $b['email']           ?? null,
        $b['telefone']        ?? null,
        $b['whatsapp']        ?? null,
        $b['cep']             ?? null,
        $b['logradouro']      ?? null,
        $b['numero']          ?? null,
        $b['complemento']     ?? null,
        $b['bairro']          ?? null,
        $b['cidade']          ?? null,
        $b['uf']              ?? null,
        $b['status']          ?? 'ativo',
        $b['plano_id']        ?? null,
        $b['data_associacao'] ?? date('Y-m-d'),
        $b['conecta_user_id'] ?? null,
        $p['sub'],
    ]);

    $newId = (int)pdo()->lastInsertId();

    // Sync to Conecta 2.0: register user if has documento
    if ($doc) {
        conectaSyncUser($doc, $b['nome_fantasia'] ?? $b['razao_social'] ?? 'Associado');
    }

    json_out(['message' => 'Associado criado', 'id' => $newId], 201);
}

// PUT /associados/{id}
if ($method === 'PUT' && preg_match('#^/associados/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $allowed = ['razao_social','nome_fantasia','nome_responsavel','email','telefone','whatsapp',
                'cep','logradouro','numero','complemento','bairro','cidade','uf',
                'status','plano_id','data_associacao','data_vencimento','conecta_user_id'];
    $set = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare('UPDATE associados SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?')
         ->execute($vals);
    json_out(['message' => 'Associado atualizado']);
}

// DELETE /associados/{id} — soft delete (status='cancelado')
if ($method === 'DELETE' && preg_match('#^/associados/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT id FROM associados WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Associado não encontrado'], 404);

    pdo()->prepare('UPDATE associados SET status = "cancelado", atualizado_em = NOW() WHERE id = ? AND tenant_id = ?')
         ->execute([$m[1], $tid]);
    json_out(['success' => true, 'message' => 'Associado cancelado']);
}

// GET /associados/{id}/cobrancas — cobranças de um associado
if ($method === 'GET' && preg_match('#^/associados/(\d+)/cobrancas$#', $uri, $m)) {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    // Verifica se associado existe e pertence ao tenant
    $stmtA = pdo()->prepare('SELECT id FROM associados WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmtA->execute([$m[1], $tid]);
    if (!$stmtA->fetch()) json_out(['error' => 'Associado não encontrado'], 404);

    $where  = 'c.associado_id = ? AND c.tenant_id = ?';
    $params = [$m[1], $tid];

    if (!empty($_GET['status'])) { $where .= ' AND c.status = ?'; $params[] = $_GET['status']; }

    $stmt = pdo()->prepare(
        "SELECT c.*, p.nome AS plano_nome
         FROM cobrancas c
         LEFT JOIN planos p ON p.id = c.plano_id
         WHERE $where
         ORDER BY c.data_vencimento DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// ============================================================
// PLANOS
// ============================================================

// GET /planos (dual-mode: public if no auth, full if authenticated)
if ($method === 'GET' && $uri === '/planos') {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $authenticated = false;
    $p = null;
    if (preg_match('/^Bearer\s+(.+)$/i', $h, $mAuth)) {
        $p = jwt_decode($mAuth[1]);
        if ($p) $authenticated = true;
    }

    if ($authenticated) {
        // Authenticated: show all active plans
        $tid = $p['tenant_id'] ?? tenant_id();
        $stmt = pdo()->prepare('SELECT * FROM planos WHERE tenant_id = ? AND ativo = 1 ORDER BY valor ASC');
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $stmtI = pdo()->prepare('SELECT pi.*, pa.nome AS parceiro_nome FROM plano_itens pi LEFT JOIN parceiros pa ON pa.id = pi.parceiro_id WHERE pi.plano_id = ? AND pi.tenant_id = ? ORDER BY pi.ordem');
            $stmtI->execute([$row['id'], $tid]);
            $row['itens'] = $stmtI->fetchAll();
        }
        unset($row);
    } else {
        // Public: only plans with tem_link_publico=1, safe columns only
        $tid = tenant_id();
        $stmt = pdo()->prepare(
            'SELECT id, nome, tipo, descricao, valor, periodicidade, slug_link, tem_conecta
             FROM planos
             WHERE tenant_id = ? AND ativo = 1 AND tem_link_publico = 1
             ORDER BY valor ASC'
        );
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();
    }

    json_out(["data" => $rows, "total" => count($rows)]);
}

// POST /planos
if ($method === 'POST' && $uri === '/planos') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    required_fields(['nome', 'tipo', 'valor', 'periodicidade']);
    $b   = body();
    $tid = $p['tenant_id'] ?? tenant_id();

    $tipos  = ['mei','me','epp','isento','combo','personalizado'];
    $period = ['mensal','trimestral','semestral','anual'];
    if (!in_array($b['tipo'], $tipos))   json_out(['error' => 'Tipo inválido: ' . implode(', ', $tipos)], 422);
    if (!in_array($b['periodicidade'], $period)) json_out(['error' => 'Periodicidade inválida'], 422);

    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($b['nome'])) . '-' . substr(md5(uniqid()), 0, 6);

    pdo()->prepare(
        'INSERT INTO planos (tenant_id, nome, tipo, descricao, valor, periodicidade,
          tem_conecta, desconto_avista, tem_link_publico, slug_link,
          split_ativo, split_percentual, valor_adesao, valor_recorrencia, ativo, criado_em)
         VALUES (?,?,?,?,?,?,?,?,1,?,?,?,?,?,1,NOW())'
    )->execute([
        $tid, $b['nome'], $b['tipo'], $b['descricao'] ?? null,
        $b['valor'], $b['periodicidade'],
        (int)($b['tem_conecta'] ?? 0),
        $b['desconto_avista'] ?? 0,
        $slug,
        (int)($b['split_ativo'] ?? 0),
        $b['split_percentual'] ?? null,
        $b['valor_adesao'] ?? null,
        $b['valor_recorrencia'] ?? null,
    ]);
    json_out(['message' => 'Plano criado', 'id' => (int)pdo()->lastInsertId(), 'slug' => $slug], 201);
}

// PUT /planos/{id} — atualiza plano
if ($method === 'PUT' && preg_match('#^/planos/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $stmt = pdo()->prepare('SELECT id FROM planos WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Plano não encontrado'], 404);

    $allowed = ['nome','tipo','descricao','valor','periodicidade','tem_conecta','desconto_avista',
                'split_ativo','split_percentual','tem_link_publico','valor_adesao','valor_recorrencia','descricao_adesao'];
    $set = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare('UPDATE planos SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?')
         ->execute($vals);
    json_out(['ok' => true, 'message' => 'Plano atualizado', 'id' => (int)$m[1]]);
}

// DELETE /planos/{id} — desativa plano (soft delete)
if ($method === 'DELETE' && preg_match('#^/planos/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT id FROM planos WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Plano não encontrado'], 404);

    pdo()->prepare('UPDATE planos SET ativo = 0 WHERE id = ? AND tenant_id = ?')
         ->execute([$m[1], $tid]);
    json_out(['success' => true, 'message' => 'Plano desativado']);
}

// ── PLANO ITENS ─────────────────────────────────────────────────────────────

// GET /planos/{id}/itens — público para planos com tem_link_publico=1
if ($method === 'GET' && preg_match('#^/planos/(\d+)/itens$#', $uri, $m)) {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $authenticated = false;
    if (preg_match('/^Bearer\s+(.+)$/i', $h, $mAuth)) {
        $p2 = jwt_decode($mAuth[1]);
        if ($p2) $authenticated = true;
    }
    $tid = $authenticated ? ($p2['tenant_id'] ?? tenant_id()) : tenant_id();

    if (!$authenticated) {
        $stmtPub = pdo()->prepare('SELECT id FROM planos WHERE id = ? AND tenant_id = ? AND tem_link_publico = 1 LIMIT 1');
        $stmtPub->execute([$m[1], $tid]);
        if (!$stmtPub->fetch()) json_out(['error' => 'Plano não encontrado'], 404);
    }

    $stmt = pdo()->prepare(
        'SELECT pi.nome, pi.tipo_cobranca, pi.valor_adesao, pi.valor_recorrencia, pa.nome AS parceiro_nome
         FROM plano_itens pi
         LEFT JOIN parceiros pa ON pa.id = pi.parceiro_id
         WHERE pi.plano_id = ? AND pi.tenant_id = ?
         ORDER BY pi.ordem, pi.nome'
    );
    $stmt->execute([$m[1], $tid]);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// POST /planos/{id}/itens
if ($method === 'POST' && preg_match('#^/planos/(\d+)/itens$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    required_fields(['nome']);
    $b   = body();
    $tid = $p['tenant_id'] ?? tenant_id();

    pdo()->prepare(
        'INSERT INTO plano_itens (tenant_id, plano_id, nome, descricao, conecta_produto_id, conecta_produto_nome,
          valor_adesao, valor_recorrencia, tipo_cobranca, parceiro_id, ordem, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $tid, $m[1], $b['nome'], $b['descricao'] ?? null,
        $b['conecta_produto_id'] ?? null, $b['conecta_produto_nome'] ?? null,
        $b['valor_adesao'] ?? null, $b['valor_recorrencia'] ?? null,
        $b['tipo_cobranca'] ?? null, $b['parceiro_id'] ?? null,
        $b['ordem'] ?? 0,
    ]);
    json_out(['message' => 'Item adicionado', 'id' => (int)pdo()->lastInsertId()], 201);
}

// PUT /planos/{id}/itens/{item_id}
if ($method === 'PUT' && preg_match('#^/planos/(\d+)/itens/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $stmt = pdo()->prepare('SELECT id FROM plano_itens WHERE id = ? AND plano_id = ? AND tenant_id = ?');
    $stmt->execute([$m[2], $m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Item não encontrado'], 404);

    $allowed = ['nome','descricao','conecta_produto_id','conecta_produto_nome',
                'valor_adesao','valor_recorrencia','tipo_cobranca','parceiro_id','ordem'];
    $set = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $vals[] = $m[2]; $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare('UPDATE plano_itens SET ' . implode(', ', $set) . ' WHERE id = ? AND plano_id = ? AND tenant_id = ?')
         ->execute($vals);
    json_out(['ok' => true, 'message' => 'Item atualizado', 'id' => (int)$m[2]]);
}

// DELETE /planos/{id}/itens/{item_id}
if ($method === 'DELETE' && preg_match('#^/planos/(\d+)/itens/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT id FROM plano_itens WHERE id = ? AND plano_id = ? AND tenant_id = ?');
    $stmt->execute([$m[2], $m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Item não encontrado'], 404);

    pdo()->prepare('DELETE FROM plano_itens WHERE id = ? AND plano_id = ? AND tenant_id = ?')
         ->execute([$m[2], $m[1], $tid]);
    json_out(['success' => true, 'message' => 'Item removido']);
}

// ============================================================
// COBRANÇAS
// ============================================================

// GET /cobrancas
if ($method === 'GET' && $uri === '/cobrancas') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    // colaborador: sem acesso a dados financeiros
    if (($p['role'] ?? '') === 'colaborador') {
        json_out(['error' => 'Permissão insuficiente'], 403);
    }

    $where  = 'c.tenant_id = ?';
    $params = [$tid];

    // associado_empresa: apenas suas próprias cobranças
    if (($p['role'] ?? '') === 'associado_empresa') {
        $assocId = $p['associado_id'] ?? $p['sub'];
        $where  .= ' AND c.associado_id = ?';
        $params[] = $assocId;
    }

    if (!empty($_GET['associado_id'])) { $where .= ' AND c.associado_id = ?'; $params[] = $_GET['associado_id']; }
    if (!empty($_GET['status']))        { $where .= ' AND c.status = ?';       $params[] = $_GET['status']; }
    if (!empty($_GET['gateway']))       { $where .= ' AND c.gateway = ?';      $params[] = $_GET['gateway']; }

    $stmt = pdo()->prepare(
        "SELECT c.*, a.nome_fantasia, a.razao_social
         FROM cobrancas c
         LEFT JOIN associados a ON a.id = c.associado_id
         WHERE $where
         ORDER BY c.data_vencimento DESC
         LIMIT 200"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(); json_out(["data" => $rows, "total" => count($rows)]);
}

// POST /cobrancas — cria cobrança via Asaas
if ($method === 'POST' && $uri === '/cobrancas') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    required_fields(['associado_id', 'valor', 'data_vencimento', 'modalidade']);
    $b = body();

    // Modalidades válidas
    $modalMap = ['pix' => 'PIX', 'boleto' => 'BOLETO', 'cartao' => 'CREDIT_CARD'];
    $mod = strtolower($b['modalidade']);
    if (!isset($modalMap[$mod])) json_out(['error' => 'Modalidade inválida: pix, boleto, cartao'], 422);

    // Busca associado
    $stmtA = pdo()->prepare('SELECT * FROM associados WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmtA->execute([$b['associado_id'], $tid]);
    $assoc = $stmtA->fetch();
    if (!$assoc) json_out(['error' => 'Associado não encontrado'], 404);

    // Garante customer no Asaas
    $customerId = asaasEnsureCustomer($assoc);

    // Cria cobrança no Asaas
    // Determina split: plano tem prioridade sobre gateway global
    $splitAtivo = false;
    $splitPct   = 0.0;
    $splitWallet = null;

    // Busca config do gateway (wallet sempre vem do gateway)
    $stmtGW = pdo()->prepare(
        'SELECT split_ativo, split_percentual, split_wallet_id
         FROM gateway_configs WHERE tenant_id = ? AND gateway = "asaas" LIMIT 1'
    );
    $stmtGW->execute([$tid]);
    $gwConfig = $stmtGW->fetch();
    if ($gwConfig) $splitWallet = $gwConfig['split_wallet_id'] ?? null;

    if (!empty($b['plano_id'])) {
        // Cobrança vinculada a plano — split do plano
        $stmtPl = pdo()->prepare('SELECT split_ativo, split_percentual FROM planos WHERE id = ? LIMIT 1');
        $stmtPl->execute([$b['plano_id']]);
        $plano = $stmtPl->fetch();
        if ($plano && $plano['split_ativo'] && $plano['split_percentual'] > 0) {
            $splitAtivo = true;
            $splitPct   = (float)$plano['split_percentual'];
        }
    } else {
        // Cobrança avulsa — split do gateway
        if ($gwConfig && $gwConfig['split_ativo'] && $gwConfig['split_percentual'] > 0) {
            $splitAtivo = true;
            $splitPct   = (float)$gwConfig['split_percentual'];
        }
    }

    $chargeData = [
        'customer'    => $customerId,
        'billingType' => $modalMap[$mod],
        'value'       => (float)$b['valor'],
        'dueDate'     => $b['data_vencimento'],
        'description' => $b['descricao'] ?? 'Taxa associativa',
    ];

    // Aplica split se configurado e wallet disponível
    if ($splitAtivo && $splitPct > 0 && !empty($splitWallet)) {
        $splitValor = round((float)$b['valor'] * $splitPct / 100, 2);
        $chargeData['split'] = [[
            'walletId'   => $splitWallet,
            'fixedValue' => $splitValor,
        ]];
    }

    $asaasResp = asaasReq('POST', '/payments', $chargeData);

    if (empty($asaasResp['id'])) {
        json_out(['error' => 'Erro no Asaas', 'detail' => $asaasResp], 502);
    }

    // Monta campos de retorno por modalidade
    $gatewayUrl    = null;
    $pixQrcode     = null;
    $pixCopiaCola  = null;

    if ($mod === 'boleto') {
        $gatewayUrl = $asaasResp['bankSlipUrl'] ?? null;
    } elseif ($mod === 'pix') {
        $pixData = asaasReq('GET', '/payments/' . $asaasResp['id'] . '/pixQrCode');
        $pixQrcode    = $pixData['encodedImage'] ?? null;
        $pixCopiaCola = $pixData['payload']      ?? null;
        $gatewayUrl   = $pixData['bankSlipUrl']  ?? null;
    }

    // Salva no banco
    pdo()->prepare(
        'INSERT INTO cobrancas
         (tenant_id, associado_id, plano_id, gateway, gateway_charge_id, gateway_url,
          valor, modalidade, data_vencimento, status, descricao, criado_por, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $tid,
        $b['associado_id'],
        $b['plano_id']       ?? null,
        'asaas',
        $asaasResp['id'],
        $gatewayUrl,
        $b['valor'],
        $mod,
        $b['data_vencimento'],
        'pendente',
        $b['descricao']      ?? null,
        $p['sub'],
    ]);

    $cobId = (int)pdo()->lastInsertId();

    json_out([
        'id'            => $cobId,
        'gateway_charge_id' => $asaasResp['id'],
        'status'        => 'pendente',
        'modalidade'    => $mod,
        'valor'         => $b['valor'],
        'data_vencimento' => $b['data_vencimento'],
        'gateway_url'   => $gatewayUrl,
        'pix_qrcode'    => $pixQrcode,
        'pix_copia_cola'=> $pixCopiaCola,
    ], 201);
}

// DELETE /cobrancas/{id}
if ($method === 'DELETE' && preg_match('#^/cobrancas/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT * FROM cobrancas WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$m[1], $tid]);
    $cob = $stmt->fetch();
    if (!$cob) json_out(['error' => 'Cobrança não encontrada'], 404);

    if (!empty($cob['gateway_charge_id'])) {
        asaasReq('DELETE', '/payments/' . $cob['gateway_charge_id']);
    }

    pdo()->prepare('UPDATE cobrancas SET status = "cancelado" WHERE id = ?')->execute([$m[1]]);
    json_out(['message' => 'Cobrança cancelada']);
}

// GET /cobrancas/{id}/status
if ($method === 'GET' && preg_match('#^/cobrancas/(\d+)/status$#', $uri, $m)) {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT * FROM cobrancas WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$m[1], $tid]);
    $cob = $stmt->fetch();
    if (!$cob) json_out(['error' => 'Cobrança não encontrada'], 404);

    if (!empty($cob['gateway_charge_id'])) {
        $r = asaasReq('GET', '/payments/' . $cob['gateway_charge_id']);
        if (!empty($r['status'])) {
            $map = ['RECEIVED' => 'pago', 'CONFIRMED' => 'pago', 'OVERDUE' => 'expirado',
                    'DELETED' => 'cancelado', 'REFUNDED' => 'estornado'];
            $novo = $map[$r['status']] ?? $cob['status'];
            if ($novo !== $cob['status']) {
                pdo()->prepare('UPDATE cobrancas SET status = ?, atualizado_em = NOW() WHERE id = ?')
                     ->execute([$novo, $m[1]]);
                $cob['status'] = $novo;
            }
        }
    }

    json_out([
        'id'               => $cob['id'],
        'status'           => $cob['status'],
        'valor'            => $cob['valor'],
        'data_vencimento'  => $cob['data_vencimento'],
        'modalidade'       => $cob['modalidade'],
        'gateway_charge_id'=> $cob['gateway_charge_id'],
    ]);
}

// ============================================================
// WEBHOOK ASAAS — POST /webhooks/asaas
// ============================================================
if ($method === 'POST' && $uri === '/webhooks/asaas') {
    $wToken = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';
    if (!empty($_ENV['ASAAS_WEBHOOK_TOKEN']) && $wToken !== $_ENV['ASAAS_WEBHOOK_TOKEN']) {
        json_out(['error' => 'Unauthorized'], 401);
    }

    $event = body();
    $type  = $event['event']       ?? '';
    $pid   = $event['payment']['id'] ?? '';

    // Log sempre
    try {
        pdo()->prepare(
            'INSERT INTO webhooks_log (tenant_id, gateway, evento, payload, criado_em)
             VALUES (?, "asaas", ?, ?, NOW())'
        )->execute([tenant_id(), $type, json_encode($event)]);
    } catch (\Throwable) {}

    if (!$pid) json_out(['ok' => true]);

    $statusMap = [
        'PAYMENT_RECEIVED'            => 'pago',
        'PAYMENT_CONFIRMED'           => 'pago',
        'PAYMENT_OVERDUE'             => 'expirado',
        'PAYMENT_DELETED'             => 'cancelado',
        'PAYMENT_RESTORED'            => 'pendente',
        'PAYMENT_REFUNDED'            => 'estornado',
        'PAYMENT_PARTIALLY_REFUNDED'  => 'estornado',
        'PAYMENT_CHARGEBACK_DISPUTE'  => 'falhou',
        'PAYMENT_CHARGEBACK_REQUESTED'=> 'falhou',
    ];

    if (isset($statusMap[$type])) {
        $novoStatus = $statusMap[$type];

        pdo()->prepare(
            'UPDATE cobrancas SET status = ?, webhook_em = NOW(),
             webhook_payload = ?, atualizado_em = NOW()
             WHERE gateway_charge_id = ?'
        )->execute([$novoStatus, json_encode($event['payment'] ?? []), $pid]);

        // Se pago: marca associado como ativo, atualiza vencimento, e atualiza inscrição
        if ($novoStatus === 'pago') {
            $stmtC = pdo()->prepare('SELECT id, associado_id FROM cobrancas WHERE gateway_charge_id = ?');
            $stmtC->execute([$pid]);
            $row = $stmtC->fetch();
            if ($row) {
                $venc = date('Y-m-d', strtotime('+1 year'));
                pdo()->prepare(
                    'UPDATE associados SET status = "ativo", data_vencimento = ?, atualizado_em = NOW()
                     WHERE id = ?'
                )->execute([$venc, $row['associado_id']]);
                // Atualiza inscrição vinculada para "pago"
                pdo()->prepare(
                    'UPDATE inscricoes_publicas SET status = "pago" WHERE cobranca_id = ? AND status = "aguardando_pagamento"'
                )->execute([$row['id']]);
            }
        }
    }

    json_out(['ok' => true]);
}

// ============================================================
// DASHBOARD — GET /dashboard
// ============================================================
if ($method === 'GET' && $uri === '/dashboard') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $q = function(string $sql, array $params = []) {
        $s = pdo()->prepare($sql); $s->execute($params); return $s->fetchColumn();
    };

    $total      = (int)$q('SELECT COUNT(*) FROM associados WHERE tenant_id=?', [$tid]);
    $ativos     = (int)$q('SELECT COUNT(*) FROM associados WHERE tenant_id=? AND status="ativo"', [$tid]);
    $inadimp    = (int)$q('SELECT COUNT(*) FROM associados WHERE tenant_id=? AND status="inadimplente"', [$tid]);
    $prospecto  = (int)$q('SELECT COUNT(*) FROM associados WHERE tenant_id=? AND status="prospecto"', [$tid]);
    $receitaMes = (float)$q('SELECT COALESCE(SUM(valor_pago),0) FROM cobrancas WHERE tenant_id=? AND status="pago" AND MONTH(data_pagamento)=MONTH(NOW()) AND YEAR(data_pagamento)=YEAR(NOW())', [$tid]);
    $pendentes  = (float)$q('SELECT COALESCE(SUM(valor),0) FROM cobrancas WHERE tenant_id=? AND status="pendente"', [$tid]);
    $vencidas   = (float)$q('SELECT COALESCE(SUM(valor),0) FROM cobrancas WHERE tenant_id=? AND status="expirado"', [$tid]);
    $inscPend   = (int)$q('SELECT COUNT(*) FROM inscricoes_publicas WHERE tenant_id=? AND status="pendente"', [$tid]);
    $fM = fn($v) => 'R$ ' . number_format($v, 2, ',', '.');

    json_out([
        'kpis' => [
            ['icon'=>'users',        'label'=>'Total Associados', 'value'=>$total,        'color'=>'blue'],
            ['icon'=>'user-check',   'label'=>'Ativos',           'value'=>$ativos,       'color'=>'green'],
            ['icon'=>'alert-circle', 'label'=>'Inadimplentes',    'value'=>$inadimp,      'color'=>'red'],
            ['icon'=>'clock',        'label'=>'Prospectos',       'value'=>$prospecto,    'color'=>'yellow'],
            ['icon'=>'dollar-sign',  'label'=>'Receita do Mes',   'value'=>$fM($receitaMes), 'color'=>'green'],
            ['icon'=>'trending-up',  'label'=>'A Receber',        'value'=>$fM($pendentes),  'color'=>'blue'],
            ['icon'=>'alert-circle', 'label'=>'Em Atraso',        'value'=>$fM($vencidas),   'color'=>'red'],
            ['icon'=>'inbox',        'label'=>'Inscrições Pendentes', 'value'=>$inscPend, 'color'=>'orange'],
        ],
        'total_associados'         => $total,
        'associados_ativos'        => $ativos,
        'associados_inadimplentes' => $inadimp,
        'receita_mes'              => $receitaMes,
        'cobrancas_pendentes'      => $pendentes,
        'cobrancas_vencidas'       => $vencidas,
        'inscricoes_pendentes'     => $inscPend,
    ]);
}

// ============================================================
// SSO — POST /sso/conecta
// Conecta 2.0 autentica associado e obtém token CRM
// Body: { "secret": "...", "documento": "...", "nome": "...", "email": "..." }
// ============================================================
if ($method === 'POST' && $uri === '/sso/conecta') {
    $b      = body();
    $secret = $_ENV['CRM_SECRET'] ?? '';

    if (empty($secret) || ($b['secret'] ?? '') !== $secret) {
        json_out(['error' => 'Unauthorized'], 401);
    }

    required_fields(['documento', 'nome']);
    $doc = preg_replace('/\D/', '', $b['documento']);
    $tid = tenant_id();

    $field = strlen($doc) === 14 ? 'cnpj' : 'cpf';
    $stmt  = pdo()->prepare("SELECT * FROM associados WHERE $field = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$doc, $tid]);
    $assoc = $stmt->fetch();

    if (!$assoc) {
        // Cria associado automaticamente via SSO
        pdo()->prepare(
            "INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, $field, email, status, criado_em)
             VALUES (?, ?, ?, ?, ?, 'ativo', NOW())"
        )->execute([$tid, strlen($doc) === 14 ? 'pj' : 'pf', $b['nome'], $doc, $b['email'] ?? null]);
        $assocId = (int)pdo()->lastInsertId();
    } else {
        $assocId = (int)$assoc['id'];
        // Atualiza conecta_user_id se vier no payload
        if (!empty($b['conecta_user_id'])) {
            pdo()->prepare('UPDATE associados SET conecta_user_id = ? WHERE id = ?')
                 ->execute([$b['conecta_user_id'], $assocId]);
        }
    }

    $token = jwt_encode([
        'sub'          => $assocId,
        'documento'    => $doc,
        'nome'         => $b['nome'],
        'role'         => 'associado',
        'tenant_id'    => $tid,
        'associado_id' => $assocId,
        'sso'          => true,
    ]);

    json_out([
        'token'        => $token,
        'associado_id' => $assocId,
    ]);
}

// ============================================================
// USUÁRIOS — GET /usuarios (gestão interna)
// ============================================================
if ($method === 'GET' && $uri === '/usuarios') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare(
        'SELECT id, nome, email, role, ativo, ultimo_login, criado_em
         FROM usuarios WHERE tenant_id = ? ORDER BY nome'
    );
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll(); json_out(["data" => $rows, "total" => count($rows)]);
}

// PATCH /usuarios/{id} — ativar/desativar usuário
if ($method === 'PATCH' && preg_match('#^/usuarios/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $stmt = pdo()->prepare('SELECT id FROM usuarios WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Usuário não encontrado'], 404);

    $set = []; $vals = [];
    if (array_key_exists('ativo', $b)) { $set[] = 'ativo = ?'; $vals[] = (int)$b['ativo']; }
    if (array_key_exists('role', $b))  { $set[] = 'role = ?';  $vals[] = $b['role']; }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare('UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?')
         ->execute($vals);
    json_out(['message' => 'Usuário atualizado', 'id' => (int)$m[1]]);
}

// ============================================================
// GATEWAY CONFIG
// ============================================================

// GET /gateway — retorna config atual do tenant (superadmin only)
if ($method === 'GET' && $uri === '/gateway') {
    $p   = auth_required();
    require_role($p, ['superadmin']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare(
        'SELECT id, gateway, ambiente, ativo, split_ativo, split_percentual,
                split_wallet_id, testado_em, criado_em
         FROM gateway_configs WHERE tenant_id = ? AND gateway = "asaas" LIMIT 1'
    );
    $stmt->execute([$tid]);
    $config = $stmt->fetch();

    // Nunca retorna api_key completa — só máscara
    if ($config) {
        $stmtKey = pdo()->prepare('SELECT api_key FROM gateway_configs WHERE id = ?');
        $stmtKey->execute([$config['id']]);
        $row = $stmtKey->fetch();
        $key = $row['api_key'] ?? '';
        $config['api_key_mask'] = $key ? substr($key, 0, 8) . str_repeat('*', 20) : null;
        $config['api_key_salva'] = !empty($key);
    }

    json_out($config ?: ['configurado' => false]);
}

// POST /gateway — salva ou atualiza config (superadmin only)
if ($method === 'POST' && $uri === '/gateway') {
    $p   = auth_required();
    require_role($p, ['superadmin']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    // Valida campos obrigatórios
    if (empty($b['api_key']) && empty($b['manter_key'])) {
        json_out(['error' => 'API Key é obrigatória'], 422);
    }

    $stmt = pdo()->prepare('SELECT id, api_key FROM gateway_configs WHERE tenant_id = ? AND gateway = "asaas" LIMIT 1');
    $stmt->execute([$tid]);
    $existing = $stmt->fetch();

    $apiKey = !empty($b['manter_key']) && $existing
        ? $existing['api_key']
        : ($b['api_key'] ?? '');

    $webhookToken = $b['webhook_token'] ?? ($existing['webhook_token'] ?? bin2hex(random_bytes(16)));

    if ($existing) {
        pdo()->prepare(
            'UPDATE gateway_configs SET
                api_key = ?, webhook_token = ?, ambiente = ?,
                split_ativo = ?, split_percentual = ?, split_wallet_id = ?,
                atualizado_em = NOW()
             WHERE id = ?'
        )->execute([
            $apiKey,
            $webhookToken,
            $b['ambiente']         ?? 'sandbox',
            (int)($b['split_ativo']     ?? 0),
            (float)($b['split_percentual'] ?? 0),
            $b['split_wallet_id']  ?? null,
            $existing['id'],
        ]);
        $configId = $existing['id'];
    } else {
        pdo()->prepare(
            'INSERT INTO gateway_configs
             (tenant_id, gateway, api_key, webhook_token, ambiente,
              split_ativo, split_percentual, split_wallet_id, ativo, criado_em)
             VALUES (?, "asaas", ?, ?, ?, ?, ?, ?, 1, NOW())'
        )->execute([
            $tid, $apiKey, $webhookToken,
            $b['ambiente']            ?? 'sandbox',
            (int)($b['split_ativo']        ?? 0),
            (float)($b['split_percentual']    ?? 0),
            $b['split_wallet_id']     ?? null,
        ]);
        $configId = (int)pdo()->lastInsertId();
    }

    // Atualiza .env com a nova key
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $env = file_get_contents($envFile);
        $env = preg_replace('/^ASAAS_API_KEY=.*/m', 'ASAAS_API_KEY=' . $apiKey, $env);
        $env = preg_replace('/^ASAAS_ENV=.*/m', 'ASAAS_ENV=' . ($b['ambiente'] === 'producao' ? 'production' : 'sandbox'), $env);
        $env = preg_replace('/^ASAAS_WEBHOOK_TOKEN=.*/m', 'ASAAS_WEBHOOK_TOKEN=' . $webhookToken, $env);
        file_put_contents($envFile, $env);
    }

    json_out(['message' => 'Configuração salva', 'id' => $configId, 'webhook_token' => $webhookToken]);
}

// POST /gateway/testar — testa conexão com Asaas (superadmin only)
if ($method === 'POST' && $uri === '/gateway/testar') {
    $p   = auth_required();
    require_role($p, ['superadmin']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $data = asaasReq('GET', '/myAccount');

    if (!empty($data['name'])) {
        pdo()->prepare('UPDATE gateway_configs SET testado_em = NOW(), ativo = 1 WHERE tenant_id = ? AND gateway = "asaas"')
             ->execute([$tid]);
        json_out([
            'sucesso'      => true,
            'conta_nome'   => $data['name'],
            'conta_email'  => $data['email'] ?? null,
            'conta_wallet' => $data['walletId'] ?? null,
            'ambiente'     => $_ENV['ASAAS_ENV'] ?? 'sandbox',
        ]);
    } else {
        json_out([
            'sucesso' => false,
            'erro'    => $data['errors'][0]['description'] ?? 'Credenciais invalidas',
            'http'    => $data['_http'] ?? 0,
        ]);
    }
}

// ============================================================
// COMUNICADOS — Templates e Envios
// ============================================================

// GET /comunicados/templates — lista todos os templates do tenant
if ($method === 'GET' && $uri === '/comunicados/templates') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare(
        'SELECT id, nome, slug, assunto_email, corpo_email, corpo_whatsapp,
                canal, ativo, criado_em, atualizado_em
         FROM comunicado_templates
         WHERE tenant_id = ?
         ORDER BY slug, nome'
    );
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// GET /comunicados/templates/{id} — detalhe de um template
if ($method === 'GET' && preg_match('#^/comunicados/templates/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare(
        'SELECT * FROM comunicado_templates WHERE id = ? AND tenant_id = ?'
    );
    $stmt->execute([$m[1], $tid]);
    $row = $stmt->fetch();
    if (!$row) json_out(['error' => 'Template não encontrado'], 404);
    json_out($row);
}

// PUT /comunicados/templates/{id} — edita template
if ($method === 'PUT' && preg_match('#^/comunicados/templates/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    // Verifica se o template existe e pertence ao tenant
    $stmt = pdo()->prepare('SELECT id FROM comunicado_templates WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Template não encontrado'], 404);

    $allowed = ['assunto_email', 'corpo_email', 'corpo_whatsapp', 'ativo'];
    $set = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $set[] = 'atualizado_em = NOW()';
    $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare(
        'UPDATE comunicado_templates SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?'
    )->execute($vals);

    json_out(['message' => 'Template atualizado']);
}

// GET /comunicados/envios — histórico de envios
if ($method === 'GET' && $uri === '/comunicados/envios') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $where  = 'e.tenant_id = ?';
    $params = [$tid];

    if (!empty($_GET['associado_id'])) { $where .= ' AND e.associado_id = ?'; $params[] = $_GET['associado_id']; }
    if (!empty($_GET['canal']))        { $where .= ' AND e.canal = ?';        $params[] = $_GET['canal']; }
    if (!empty($_GET['gatilho']))       { $where .= ' AND e.gatilho = ?';      $params[] = $_GET['gatilho']; }
    if (!empty($_GET['status']))        { $where .= ' AND e.status = ?';       $params[] = $_GET['status']; }

    $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));

    $stmt = pdo()->prepare(
        "SELECT e.*, a.nome_fantasia, a.razao_social
         FROM comunicado_envios e
         LEFT JOIN associados a ON a.id = e.associado_id
         WHERE $where
         ORDER BY e.criado_em DESC
         LIMIT $limit"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// ============================================================
// TENANT CONFIG
// ============================================================

// GET /tenant/config — retorna configurações do tenant (exceto senhas)
if ($method === 'GET' && $uri === '/tenant/config') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare(
        'SELECT id, chave, valor, criado_em, atualizado_em
         FROM tenant_configs
         WHERE tenant_id = ? AND chave NOT LIKE "%senha%" AND chave NOT LIKE "%password%" AND chave NOT LIKE "%secret%"
         ORDER BY chave'
    );
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// POST /tenant/config — salva ou atualiza uma chave de configuração
if ($method === 'POST' && $uri === '/tenant/config') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    required_fields(['chave', 'valor']);
    $b = body();

    $stmt = pdo()->prepare('SELECT id FROM tenant_configs WHERE tenant_id = ? AND chave = ? LIMIT 1');
    $stmt->execute([$tid, $b['chave']]);
    $existing = $stmt->fetch();

    if ($existing) {
        pdo()->prepare(
            'UPDATE tenant_configs SET valor = ?, atualizado_em = NOW() WHERE id = ?'
        )->execute([$b['valor'], $existing['id']]);
        json_out(['message' => 'Configuração atualizada', 'id' => (int)$existing['id']]);
    } else {
        pdo()->prepare(
            'INSERT INTO tenant_configs (tenant_id, chave, valor, criado_em) VALUES (?, ?, ?, NOW())'
        )->execute([$tid, $b['chave'], $b['valor']]);
        json_out(['message' => 'Configuração criada', 'id' => (int)pdo()->lastInsertId()], 201);
    }
}

// ============================================================
// INSCRIÇÕES PÚBLICAS
// ============================================================

// POST /inscricoes — inscrição pública (sem auth)
if ($method === 'POST' && $uri === '/inscricoes') {
    required_fields(['plano_id', 'razao_social', 'cpf_cnpj', 'telefone', 'email']);
    $b   = body();
    $tid = tenant_id();

    // Valida que o plano existe, está ativo e é público
    $stmtP = pdo()->prepare(
        'SELECT id, nome, valor, periodicidade FROM planos
         WHERE id = ? AND tenant_id = ? AND ativo = 1 AND tem_link_publico = 1 LIMIT 1'
    );
    $stmtP->execute([$b['plano_id'], $tid]);
    $plano = $stmtP->fetch();
    if (!$plano) json_out(['error' => 'Plano não encontrado ou não disponível para inscrição pública'], 404);

    // Rate-limit: máx 3 inscrições por email nas últimas 24h
    $stmtRL = pdo()->prepare(
        'SELECT COUNT(*) FROM inscricoes_publicas
         WHERE email = ? AND tenant_id = ? AND criado_em >= NOW() - INTERVAL 24 HOUR'
    );
    $stmtRL->execute([$b['email'], $tid]);
    if ((int)$stmtRL->fetchColumn() >= 3) {
        json_out(['error' => 'Limite de inscrições atingido. Tente novamente em 24 horas.'], 429);
    }

    // Hash senha se fornecida
    $senhaHash = null;
    if (!empty($b['senha'])) {
        if (strlen($b['senha']) < 6) {
            json_out(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422);
        }
        $senhaHash = password_hash($b['senha'], PASSWORD_BCRYPT);
    }

    pdo()->prepare(
        'INSERT INTO inscricoes_publicas
         (tenant_id, plano_id, nome_empresa, cnpj, nome_contato, email, whatsapp,
          nome_fantasia, senha_hash, telefone, cep, logradouro, complemento, bairro, cidade,
          capital_social, data_abertura, faturamento_mensal, num_funcionarios,
          status, ip_origem, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?,
          ?, ?, ?, ?, ?, ?, ?, ?,
          ?, ?, ?, ?,
          "pendente", ?, NOW())'
    )->execute([
        $tid,
        $b['plano_id'],
        $b['razao_social'],
        preg_replace('/\D/', '', $b['cpf_cnpj']),
        $b['razao_social'],
        $b['email'],
        $b['telefone'] ?? null,
        $b['nome_fantasia'] ?? null,
        $senhaHash,
        $b['telefone'] ?? null,
        isset($b['cep']) ? preg_replace('/\D/', '', $b['cep']) : null,
        $b['logradouro'] ?? null,
        $b['complemento'] ?? null,
        $b['bairro'] ?? null,
        $b['cidade'] ?? null,
        isset($b['capital_social']) ? (float)$b['capital_social'] : null,
        $b['data_abertura'] ?? null,
        $b['faturamento_mensal'] ?? null,
        $b['num_funcionarios'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    json_out([
        'message'    => 'Inscrição recebida com sucesso',
        'id'         => (int)pdo()->lastInsertId(),
        'plano_nome' => $plano['nome'],
    ], 201);
}

// GET /inscricoes — lista inscrições (auth, gestor only)
if ($method === 'GET' && $uri === '/inscricoes') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $where  = 'i.tenant_id = ?';
    $params = [$tid];

    if (!empty($_GET['status']))   { $where .= ' AND i.status = ?';   $params[] = $_GET['status']; }
    if (!empty($_GET['plano_id'])) { $where .= ' AND i.plano_id = ?'; $params[] = $_GET['plano_id']; }

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 20));
    $off   = ($page - 1) * $limit;

    $stmtT = pdo()->prepare("SELECT COUNT(*) FROM inscricoes_publicas i WHERE $where");
    $stmtT->execute($params);
    $total = (int)$stmtT->fetchColumn();

    $stmt = pdo()->prepare(
        "SELECT i.*, p.nome AS plano_nome, p.valor AS plano_valor
         FROM inscricoes_publicas i
         LEFT JOIN planos p ON p.id = i.plano_id
         WHERE $where
         ORDER BY i.criado_em DESC
         LIMIT $limit OFFSET $off"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_out([
        'data'  => $rows,
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

// GET /inscricoes/{id} — detalhe de uma inscrição
if ($method === 'GET' && preg_match('#^/inscricoes/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();
    $stmt = pdo()->prepare(
        'SELECT i.*, p.nome AS plano_nome, p.valor AS plano_valor, p.periodicidade AS plano_periodicidade
         FROM inscricoes_publicas i
         LEFT JOIN planos p ON p.id = i.plano_id
         WHERE i.id = ? AND i.tenant_id = ?'
    );
    $stmt->execute([$m[1], $tid]);
    $row = $stmt->fetch();
    if (!$row) json_out(['error' => 'Inscrição não encontrada'], 404);
    json_out($row);
}

// POST /inscricoes/{id}/aprovar — cria associado prospecto + aprova inscrição
if ($method === 'POST' && preg_match('#^/inscricoes/(\d+)/aprovar$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT * FROM inscricoes_publicas WHERE id = ? AND tenant_id = ? AND status = "pendente"');
    $stmt->execute([$m[1], $tid]);
    $insc = $stmt->fetch();
    if (!$insc) json_out(['error' => 'Inscrição não encontrada ou já processada'], 404);

    // Cria associado prospecto
    $doc = $insc['cnpj'] ? preg_replace('/\D/', '', $insc['cnpj']) : null;
    pdo()->prepare(
        'INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, razao_social, cnpj, email, whatsapp, status, plano_id, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, "prospecto", ?, ?, NOW())'
    )->execute([$tid, $doc && strlen($doc) === 14 ? 'pj' : 'pf', $insc['nome_empresa'], $insc['nome_empresa'], $doc, $insc['email'], $insc['whatsapp'], $insc['plano_id'], $p['sub']]);
    $assocId = (int)pdo()->lastInsertId();

    // Atualiza inscrição
    pdo()->prepare('UPDATE inscricoes_publicas SET status = "aprovado", prospecto_id = ?, convertido_em = NOW() WHERE id = ?')
         ->execute([$assocId, $m[1]]);

    // Sync to Conecta 2.0
    if ($doc) {
        conectaSyncUser($doc, $insc['nome_empresa'] ?? 'Associado');
    }

    json_out(['message' => 'Inscrição aprovada', 'associado_id' => $assocId]);
}

// POST /inscricoes/{id}/reprovar — reprova inscrição
if ($method === 'POST' && preg_match('#^/inscricoes/(\d+)/reprovar$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT id FROM inscricoes_publicas WHERE id = ? AND tenant_id = ? AND status IN ("pendente","aprovado")');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Inscrição não encontrada ou já processada'], 404);

    pdo()->prepare('UPDATE inscricoes_publicas SET status = "reprovado" WHERE id = ?')->execute([$m[1]]);
    json_out(['message' => 'Inscrição reprovada']);
}

// POST /inscricoes/{id}/converter — cria associado + cobrança + atualiza inscrição
if ($method === 'POST' && preg_match('#^/inscricoes/(\d+)/converter$#', $uri, $m)) {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $stmt = pdo()->prepare(
        'SELECT i.*, p.nome AS plano_nome, p.valor AS plano_valor
         FROM inscricoes_publicas i
         LEFT JOIN planos p ON p.id = i.plano_id
         WHERE i.id = ? AND i.tenant_id = ? AND i.status IN ("pendente","aprovado")'
    );
    $stmt->execute([$m[1], $tid]);
    $insc = $stmt->fetch();
    if (!$insc) json_out(['error' => 'Inscrição não encontrada ou já processada'], 404);

    // 1. Cria ou reutiliza associado
    $assocId = $insc['prospecto_id'] ? (int)$insc['prospecto_id'] : null;
    if (!$assocId) {
        $doc = $insc['cnpj'] ? preg_replace('/\D/', '', $insc['cnpj']) : null;
        pdo()->prepare(
            'INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, razao_social, cnpj, email, whatsapp, status, plano_id, criado_por, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, "prospecto", ?, ?, NOW())'
        )->execute([$tid, $doc && strlen($doc) === 14 ? 'pj' : 'pf', $insc['nome_empresa'], $insc['nome_empresa'], $doc, $insc['email'], $insc['whatsapp'], $insc['plano_id'], $p['sub']]);
        $assocId = (int)pdo()->lastInsertId();
    }

    // 2. Busca associado completo para Asaas
    $stmtA = pdo()->prepare('SELECT * FROM associados WHERE id = ? LIMIT 1');
    $stmtA->execute([$assocId]);
    $assoc = $stmtA->fetch();

    // 3. Garante customer no Asaas
    $customerId = asaasEnsureCustomer($assoc);

    // 4. Cria cobrança
    $mod = $b['modalidade'] ?? 'pix';
    $modalMap = ['pix' => 'PIX', 'boleto' => 'BOLETO'];
    $billingType = $modalMap[$mod] ?? 'PIX';
    $valor = (float)$insc['plano_valor'];
    $venc = date('Y-m-d', strtotime('+7 days'));

    $chargeData = [
        'customer'    => $customerId,
        'billingType' => $billingType,
        'value'       => $valor,
        'dueDate'     => $venc,
        'description' => 'Inscrição — ' . ($insc['plano_nome'] ?? 'Plano'),
    ];

    // Split (gateway global)
    $stmtGW = pdo()->prepare('SELECT split_ativo, split_percentual, split_wallet_id FROM gateway_configs WHERE tenant_id = ? AND gateway = "asaas" LIMIT 1');
    $stmtGW->execute([$tid]);
    $gw = $stmtGW->fetch();
    if ($gw && $gw['split_ativo'] && $gw['split_percentual'] > 0 && !empty($gw['split_wallet_id'])) {
        $chargeData['split'] = [['walletId' => $gw['split_wallet_id'], 'fixedValue' => round($valor * (float)$gw['split_percentual'] / 100, 2)]];
    }

    $asaasResp = asaasReq('POST', '/payments', $chargeData);
    if (empty($asaasResp['id'])) json_out(['error' => 'Erro no Asaas', 'detail' => $asaasResp], 502);

    // PIX QR code
    $gatewayUrl = $pixQrcode = $pixCopiaCola = null;
    if ($mod === 'pix') {
        $pixData = asaasReq('GET', '/payments/' . $asaasResp['id'] . '/pixQrCode');
        $pixQrcode    = $pixData['encodedImage'] ?? null;
        $pixCopiaCola = $pixData['payload']      ?? null;
    }
    $gatewayUrl = $asaasResp['bankSlipUrl'] ?? $asaasResp['invoiceUrl'] ?? null;

    // 5. Salva cobrança no banco
    pdo()->prepare(
        'INSERT INTO cobrancas (tenant_id, associado_id, plano_id, gateway, gateway_charge_id, gateway_url, valor, modalidade, data_vencimento, status, descricao, criado_por, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([$tid, $assocId, $insc['plano_id'], 'asaas', $asaasResp['id'], $gatewayUrl, $valor, $mod, $venc, 'pendente', 'Inscrição — ' . ($insc['plano_nome'] ?? 'Plano'), $p['sub']]);
    $cobId = (int)pdo()->lastInsertId();

    // 6. Atualiza inscrição
    pdo()->prepare('UPDATE inscricoes_publicas SET status = "aguardando_pagamento", prospecto_id = ?, cobranca_id = ?, convertido_em = NOW() WHERE id = ?')
         ->execute([$assocId, $cobId, $m[1]]);

    json_out([
        'message'         => 'Cobrança gerada com sucesso',
        'associado_id'    => $assocId,
        'cobranca_id'     => $cobId,
        'gateway_url'     => $gatewayUrl,
        'pix_qrcode'      => $pixQrcode,
        'pix_copia_cola'  => $pixCopiaCola,
        'valor'           => $valor,
        'vencimento'      => $venc,
    ]);
}

// ============================================================
// TESTE DE E-MAIL
// ============================================================
if ($method === 'POST' && $uri === '/test/email') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $to = $p['email'] ?? '';
    if (!$to) json_out(['error' => 'E-mail do usuário não encontrado no token'], 422);

    // Busca config do tenant para remetente
    $stmtCfg = pdo()->prepare('SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = "email_remetente" LIMIT 1');
    $stmtCfg->execute([$tid]);
    $from = $stmtCfg->fetchColumn() ?: 'noreply@acicdf.org.br';

    $stmtNome = pdo()->prepare('SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = "email_remetente_nome" LIMIT 1');
    $stmtNome->execute([$tid]);
    $fromName = $stmtNome->fetchColumn() ?: 'Conecta CRM';

    $subject = 'Teste de E-mail — Conecta CRM';
    $body = '<html><body style="font-family:Arial,sans-serif;padding:20px">'
          . '<h2 style="color:#1B2B6B">Conecta CRM — Teste de E-mail</h2>'
          . '<p>Este é um e-mail de teste enviado pelo Conecta CRM.</p>'
          . '<p>Se você recebeu esta mensagem, o envio de e-mails está funcionando corretamente.</p>'
          . '<p style="color:#6B7280;font-size:12px;margin-top:24px">Enviado em ' . date('d/m/Y H:i:s') . '</p>'
          . '</body></html>';

    $headers  = "From: $fromName <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $sent = @mail($to, $subject, $body, $headers);

    if ($sent) {
        json_out(['message' => 'E-mail de teste enviado para ' . $to, 'enviado_para' => $to]);
    } else {
        json_out(['error' => 'Falha ao enviar e-mail. Verifique a configuração do servidor de e-mail.'], 500);
    }
}

// ============================================================
// REDEFINIR SENHA — usuarios e associados (superadmin only)
// ============================================================

// POST /usuarios/{id}/redefinir-senha
if ($method === 'POST' && preg_match('#^/usuarios/(\d+)/redefinir-senha$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $novaSenha = $b['senha'] ?? '';
    if (strlen($novaSenha) < 6) json_out(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422);

    $stmt = pdo()->prepare('SELECT id, nome FROM usuarios WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
    $stmt->execute([$m[1], $tid]);
    $user = $stmt->fetch();
    if (!$user) json_out(['error' => 'Usuário não encontrado'], 404);

    pdo()->prepare('UPDATE usuarios SET senha_hash = ?, atualizado_em = NOW() WHERE id = ?')
         ->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $m[1]]);

    json_out(['message' => 'Senha redefinida com sucesso', 'usuario' => $user['nome']]);
}

// POST /associados/{id}/redefinir-senha
if ($method === 'POST' && preg_match('#^/associados/(\d+)/redefinir-senha$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $novaSenha = $b['senha'] ?? '';
    if (strlen($novaSenha) < 6) json_out(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422);

    $stmt = pdo()->prepare('SELECT id, nome_fantasia, razao_social, cnpj, cpf FROM associados WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    $assoc = $stmt->fetch();
    if (!$assoc) json_out(['error' => 'Associado não encontrado'], 404);

    // Tenta redefinir no Conecta 2.0 via API
    $doc = $assoc['cnpj'] ?: $assoc['cpf'];
    $docClean = preg_replace('/\D/', '', $doc ?? '');
    $conectaOk = false;

    if ($docClean) {
        $ch = curl_init('https://acicdf.org.br/conecta/auth.php?action=reset_password');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'cpf_cnpj'     => $docClean,
                'new_password' => $novaSenha,
                'admin_secret' => $_ENV['CRM_SECRET'] ?? '',
            ]),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respData = json_decode($resp ?: '{}', true);
        $conectaOk = $code < 400 && (($respData['ok'] ?? false) || ($respData['success'] ?? false));
        error_log("[CRM-PWD] Conecta reset for doc=$docClean: http=$code body=" . substr($resp ?: '', 0, 300));
    }

    // Salva hash pendente localmente (fallback se Conecta não processou)
    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $extras = json_decode($assoc['campos_extras'] ?? '{}', true) ?? [];
    $extras['pending_password_hash'] = $hash;
    pdo()->prepare('UPDATE associados SET campos_extras = ?, atualizado_em = NOW() WHERE id = ?')
         ->execute([json_encode($extras), $m[1]]);

    $nome = $assoc['nome_fantasia'] ?: $assoc['razao_social'];
    $msg  = $conectaOk
        ? "Senha redefinida no Conecta 2.0 e no CRM"
        : "Senha salva no CRM. O Conecta 2.0 será atualizado no próximo login.";

    json_out(['message' => $msg, 'associado' => $nome, 'conecta_atualizado' => $conectaOk]);
}

// ============================================================
// SYNC — Conecta 2.0 → CRM (webhook de sincronização)
// POST /sync/conecta  (autenticado via X-CRM-Secret header)
// ============================================================
if ($method === 'POST' && $uri === '/sync/conecta') {
    $secret = $_ENV['CRM_SECRET'] ?? '';
    $headerSecret = $_SERVER['HTTP_X_CRM_SECRET'] ?? '';

    if (!$secret || $headerSecret !== $secret) {
        json_out(['error' => 'Unauthorized'], 401);
    }

    $b      = body();
    $action = $b['action'] ?? '';
    $doc    = preg_replace('/\D/', '', $b['documento'] ?? $b['cpf_cnpj'] ?? '');
    $tid    = tenant_id();

    if (!$doc) json_out(['error' => 'Documento obrigatório'], 422);

    $field = strlen($doc) === 14 ? 'cnpj' : 'cpf';
    $stmt  = pdo()->prepare("SELECT * FROM associados WHERE $field = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$doc, $tid]);
    $assoc = $stmt->fetch();

    if ($action === 'upsert') {
        $nome   = $b['nome'] ?? $b['nome_fantasia'] ?? 'Associado';
        $status = $b['status'] ?? 'ativo';

        if ($assoc) {
            // Update
            $sets = ['atualizado_em = NOW()'];
            $vals = [];
            if (!empty($b['nome']))        { $sets[] = 'nome_fantasia = ?'; $vals[] = $b['nome']; }
            if (!empty($b['higestor_id'])) { $sets[] = 'higestor_id = ?';   $vals[] = $b['higestor_id']; }
            if (!empty($b['status']))      { $sets[] = 'status = ?';        $vals[] = $b['status']; }
            if (!empty($b['email']))       { $sets[] = 'email = ?';         $vals[] = $b['email']; }
            if (!empty($b['conecta_user_id'])) { $sets[] = 'conecta_user_id = ?'; $vals[] = $b['conecta_user_id']; }
            $vals[] = $assoc['id'];
            pdo()->prepare('UPDATE associados SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
            json_out(['message' => 'Associado atualizado', 'id' => $assoc['id']]);
        } else {
            // Create
            pdo()->prepare(
                "INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, $field, email, status, higestor_id, conecta_user_id, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $tid,
                strlen($doc) === 14 ? 'pj' : 'pf',
                $nome,
                $doc,
                $b['email'] ?? null,
                $status,
                $b['higestor_id'] ?? null,
                $b['conecta_user_id'] ?? null,
            ]);
            json_out(['message' => 'Associado criado via sync', 'id' => (int)pdo()->lastInsertId()], 201);
        }
    }

    if ($action === 'status_change') {
        if (!$assoc) json_out(['error' => 'Associado não encontrado'], 404);
        $newStatus = $b['status'] ?? '';
        if (!in_array($newStatus, ['ativo','inadimplente','suspenso','cancelado','prospecto'])) {
            json_out(['error' => 'Status inválido'], 422);
        }
        pdo()->prepare('UPDATE associados SET status = ?, atualizado_em = NOW() WHERE id = ?')
             ->execute([$newStatus, $assoc['id']]);
        json_out(['message' => 'Status atualizado', 'id' => $assoc['id'], 'status' => $newStatus]);
    }

    if ($action === 'password_set') {
        if (!$assoc) json_out(['error' => 'Associado não encontrado'], 404);
        // Clear pending password hash since Conecta 2.0 now has the password
        $extras = json_decode($assoc['campos_extras'] ?? '{}', true) ?? [];
        unset($extras['pending_password_hash']);
        pdo()->prepare('UPDATE associados SET campos_extras = ?, atualizado_em = NOW() WHERE id = ?')
             ->execute([json_encode($extras), $assoc['id']]);
        json_out(['message' => 'Password flag atualizada', 'id' => $assoc['id']]);
    }

    json_out(['error' => 'Action inválida: ' . $action], 422);
}

// ============================================================
// PARCEIROS
// ============================================================

// GET /parceiros
if ($method === 'GET' && $uri === '/parceiros') {
    $p = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();
    $stmt = pdo()->prepare('SELECT * FROM parceiros WHERE tenant_id = ? AND ativo = 1 ORDER BY nome');
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll();
    json_out(['data' => $rows, 'total' => count($rows)]);
}

// POST /parceiros
if ($method === 'POST' && $uri === '/parceiros') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    required_fields(['nome']);
    $b   = body();
    $tid = $p['tenant_id'] ?? tenant_id();

    pdo()->prepare(
        'INSERT INTO parceiros (tenant_id, nome, cnpj, wallet_id_asaas, split_percentual, email, telefone, ativo, criado_em)
         VALUES (?,?,?,?,?,?,?,1,NOW())'
    )->execute([
        $tid, $b['nome'], $b['cnpj'] ?? null,
        $b['wallet_id_asaas'] ?? null, $b['split_percentual'] ?? null,
        $b['email'] ?? null, $b['telefone'] ?? null,
    ]);
    json_out(['message' => 'Parceiro criado', 'id' => (int)pdo()->lastInsertId()], 201);
}

// PUT /parceiros/{id}
if ($method === 'PUT' && preg_match('#^/parceiros/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();
    $b   = body();

    $stmt = pdo()->prepare('SELECT id FROM parceiros WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Parceiro não encontrado'], 404);

    $allowed = ['nome','cnpj','wallet_id_asaas','split_percentual','email','telefone'];
    $set = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $b)) { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    }
    if (!$set) json_out(['error' => 'Nenhum campo para atualizar'], 422);

    $vals[] = $m[1]; $vals[] = $tid;
    pdo()->prepare('UPDATE parceiros SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?')
         ->execute($vals);
    json_out(['ok' => true, 'message' => 'Parceiro atualizado', 'id' => (int)$m[1]]);
}

// DELETE /parceiros/{id}
if ($method === 'DELETE' && preg_match('#^/parceiros/(\d+)$#', $uri, $m)) {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT id FROM parceiros WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$m[1], $tid]);
    if (!$stmt->fetch()) json_out(['error' => 'Parceiro não encontrado'], 404);

    pdo()->prepare('UPDATE parceiros SET ativo = 0 WHERE id = ? AND tenant_id = ?')
         ->execute([$m[1], $tid]);
    json_out(['success' => true, 'message' => 'Parceiro desativado']);
}

// ============================================================
// CONECTA PRODUTOS PROXY
// ============================================================

// GET /conecta/produtos
if ($method === 'GET' && $uri === '/conecta/produtos') {
    $p = auth_required();
    $cacheFile = '/tmp/conecta_produtos_cache.json';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) json_out($cached);
    }

    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $resp = @file_get_contents('https://acicdf.org.br/conecta/produtos.php?action=list&status=ativo', false, $ctx);

    if ($resp === false) {
        json_out(['error' => 'Erro ao buscar produtos do Conecta'], 502);
    }

    $data = json_decode($resp, true);
    if ($data === null) {
        json_out(['error' => 'Resposta inválida do Conecta'], 502);
    }

    file_put_contents($cacheFile, json_encode($data));
    json_out($data);
}

// ============================================================
// 404
// ============================================================
json_out(['error' => 'Rota não encontrada', 'path' => $uri, 'method' => $method], 404);
