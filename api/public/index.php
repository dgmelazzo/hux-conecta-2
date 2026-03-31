<?php
declare(strict_types=1);

// ============================================================
// Conecta CRM API — v2.3
// VPS: /var/www/hux-crm-association/api/public/index.php
// Schema alinhado com banco real (conecta_crm)
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
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
// AUTH — login de usuários internos (gestor/atendente)
// POST /auth/login  { email, senha }
// ============================================================
if ($method === 'POST' && $uri === '/auth/login') {
    required_fields(['email', 'senha']);
    $b = body();

    $stmt = pdo()->prepare(
        'SELECT * FROM usuarios WHERE email = ? AND tenant_id = ? LIMIT 1'
    );
    $stmt->execute([$b['email'], tenant_id()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($b['senha'], $user['senha_hash'])) {
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

// ── POST /auth/criar-usuario — cria gestor/atendente (só superadmin/gestor) ───
if ($method === 'POST' && $uri === '/auth/criar-usuario') {
    $p = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    required_fields(['nome', 'email', 'senha', 'role']);
    $b = body();

    if (!in_array($b['role'], ['gestor', 'atendente'])) {
        json_out(['error' => 'Role inválida. Use: gestor ou atendente'], 422);
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

    json_out(['message' => 'Associado criado', 'id' => (int)pdo()->lastInsertId()], 201);
}

// PUT /associados/{id}
if ($method === 'PUT' && preg_match('#^/associados/(\d+)$#', $uri, $m)) {
    $p   = auth_required();
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

// ============================================================
// PLANOS
// ============================================================

// GET /planos
if ($method === 'GET' && $uri === '/planos') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();
    $stmt = pdo()->prepare('SELECT * FROM planos WHERE tenant_id = ? AND ativo = 1 ORDER BY valor ASC');
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll(); json_out(["data" => $rows, "total" => count($rows)]);
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
          tem_conecta, desconto_avista, tem_link_publico, slug_link, ativo, criado_em)
         VALUES (?,?,?,?,?,?,?,?,1,?,1,NOW())'
    )->execute([
        $tid, $b['nome'], $b['tipo'], $b['descricao'] ?? null,
        $b['valor'], $b['periodicidade'],
        (int)($b['tem_conecta'] ?? 0),
        $b['desconto_avista'] ?? 0,
        $slug,
    ]);
    json_out(['message' => 'Plano criado', 'id' => (int)pdo()->lastInsertId(), 'slug' => $slug], 201);
}

// ============================================================
// COBRANÇAS
// ============================================================

// GET /cobrancas
if ($method === 'GET' && $uri === '/cobrancas') {
    $p   = auth_required();
    $tid = $p['tenant_id'] ?? tenant_id();

    $where  = 'c.tenant_id = ?';
    $params = [$tid];

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
    $asaasResp = asaasReq('POST', '/payments', [
        'customer'    => $customerId,
        'billingType' => $modalMap[$mod],
        'value'       => (float)$b['valor'],
        'dueDate'     => $b['data_vencimento'],
        'description' => $b['descricao'] ?? 'Taxa associativa',
    ]);

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

        // Se pago: marca associado como ativo e atualiza data_vencimento
        if ($novoStatus === 'pago') {
            $stmtC = pdo()->prepare('SELECT associado_id FROM cobrancas WHERE gateway_charge_id = ?');
            $stmtC->execute([$pid]);
            $row = $stmtC->fetch();
            if ($row) {
                $venc = date('Y-m-d', strtotime('+1 year'));
                pdo()->prepare(
                    'UPDATE associados SET status = "ativo", data_vencimento = ?, atualizado_em = NOW()
                     WHERE id = ?'
                )->execute([$venc, $row['associado_id']]);
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
        ],
        'total_associados'         => $total,
        'associados_ativos'        => $ativos,
        'associados_inadimplentes' => $inadimp,
        'receita_mes'              => $receitaMes,
        'cobrancas_pendentes'      => $pendentes,
        'cobrancas_vencidas'       => $vencidas,
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


// ============================================================
// GATEWAY CONFIG
// ============================================================

// GET /gateway — retorna config atual do tenant
if ($method === 'GET' && $uri === '/gateway') {
    $p   = auth_required();
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

// POST /gateway — salva ou atualiza config
if ($method === 'POST' && $uri === '/gateway') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
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

// POST /gateway/testar — testa conexão com Asaas
if ($method === 'POST' && $uri === '/gateway/testar') {
    $p   = auth_required();
    require_role($p, ['superadmin', 'gestor']);
    $tid = $p['tenant_id'] ?? tenant_id();

    $stmt = pdo()->prepare('SELECT api_key, ambiente FROM gateway_configs WHERE tenant_id = ? AND gateway = "asaas" LIMIT 1');
    $stmt->execute([$tid]);
    $config = $stmt->fetch();

    if (!$config || empty($config['api_key'])) {
        json_out(['error' => 'API Key não configurada'], 422);
    }

    // Testa chamando /myAccount no Asaas
    $baseUrl = $config['ambiente'] === 'producao'
        ? 'https://api.asaas.com/v3'
        : 'https://sandbox.asaas.com/api/v3';

    $ch = curl_init($baseUrl . '/myAccount');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'access_token: ' . $config['api_key'],
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res ?: '{}', true);

    if ($code === 200 && !empty($data['name'])) {
        // Atualiza testado_em
        pdo()->prepare('UPDATE gateway_configs SET testado_em = NOW(), ativo = 1 WHERE tenant_id = ? AND gateway = "asaas"')
             ->execute([$tid]);

        json_out([
            'sucesso'      => true,
            'conta_nome'   => $data['name'],
            'conta_email'  => $data['email'] ?? null,
            'conta_wallet' => $data['walletId'] ?? null,
            'ambiente'     => $config['ambiente'],
        ]);
    } else {
        json_out([
            'sucesso' => false,
            'erro'    => $data['errors'][0]['description'] ?? 'Credenciais inválidas',
            'http'    => $code,
        ]);
    }
}

// ============================================================
// 404
// ============================================================
json_out(['error' => 'Rota não encontrada', 'path' => $uri, 'method' => $method], 404);
