<?php
/**
 * auth.php - Conecta 2.0 Authentication API (HostGator)
 * Location: https://acicdf.org.br/conecta/auth.php
 *
 * Fonte unica de verdade: Conecta CRM ACIC (api-crm.acicdf.org.br).
 *
 * Actions: check, first, login, dados, validate, logout, admin_check
 *
 * Bridge para CRM protegida por X-Conecta-Secret (CRM_BRIDGE_SECRET).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Captura erros fatais e retorna JSON (evita body vazio em 500)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'PHP fatal: ' . $err['message'] . ' em ' . basename($err['file']) . ':' . $err['line'],
        ]);
    }
});
set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage() . ' em ' . basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    exit;
});

require_once __DIR__ . '/config.php';

// Le php://input UMA unica vez e cacheia (stream so pode ser lido uma vez)
function input(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    $cache = is_array($decoded) ? $decoded : [];
    return $cache;
}

$body   = input();
// action pode vir via query string, form POST, ou JSON body (o frontend envia nos 3)
$action = $_GET['action'] ?? $_POST['action'] ?? ($body['action'] ?? '');

// ------------------------------------------------------------
// DB
// ------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection error: ' . $e->getMessage()]);
    exit;
}

// Schema upgrade - garante colunas novas (silencioso se ja existirem)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM conecta_users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('crm_associado_id', $cols, true)) {
        $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_associado_id INT NULL");
    }
    if (!in_array('crm_dados', $cols, true)) {
        // Tenta JSON, cai pra TEXT se o MySQL nao suportar
        try { $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_dados JSON NULL"); }
        catch (\Throwable $e) { $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_dados TEXT NULL"); }
    }
} catch (\Throwable $e) { /* schema upgrade best-effort */ }

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
// Frontend (app-bundle.js authPost) espera {success:true,data:{...}} / {success:false,message:"..."}
function ok(array $data = [], int $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function err(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function normalizeDoc(string $raw): string {
    return (string)preg_replace('/\D/', '', $raw);
}

function crmPost(string $endpoint, array $body): array {
    $ch = curl_init(CRM_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Conecta-Secret: ' . CRM_BRIDGE_SECRET,
        ],
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) return ['ok' => false, '_http' => $code, 'error' => 'CRM indisponivel'];
    $data = json_decode($res, true);
    if (!is_array($data)) $data = ['ok' => false, 'error' => 'Resposta invalida do CRM'];
    $data['_http'] = $code;
    return $data;
}

function crmGetPublic(string $endpoint): array {
    $ch = curl_init(CRM_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'X-Conecta-Secret: ' . CRM_BRIDGE_SECRET,
        ],
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) return ['ok' => false, '_http' => $code, 'error' => 'CRM indisponivel'];
    $data = json_decode($res, true);
    if (!is_array($data)) $data = ['ok' => false, 'error' => 'Resposta invalida do CRM'];
    $data['_http'] = $code;
    return $data;
}

function crmGet(string $endpoint, string $token): ?array {
    $ch = curl_init(CRM_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function makeToken(): string { return bin2hex(random_bytes(32)); }

// ------------------------------------------------------------
// ACTION: check - verifica associado no CRM (sem senha)
// ------------------------------------------------------------
if ($action === 'check') {
    $doc = normalizeDoc($body['cpf_cnpj'] ?? '');
    if (!$doc) err(422, 'CPF/CNPJ obrigatorio');

    // Admin: fluxo local, nao consulta CRM
    if ($doc === ADMIN_DOC) {
        $st = $pdo->prepare('SELECT id, password, primeiro_acesso FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
        $st->execute([$doc]);
        $u = $st->fetch();
        $primeiro = !$u || empty($u['password']) || !empty($u['primeiro_acesso']);
        ok([
            'existe'          => true,
            'primeiro_acesso' => $primeiro,
            'nome'            => defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador',
            'status'          => 'ativo',
            'plano'           => 'Admin',
            'is_admin'        => true,
        ]);
    }

    // Associado: consulta CRM
    $crm = crmGetPublic('/auth/check-associado?documento=' . urlencode($doc));
    if (!$crm['ok']) {
        err(404, 'CPF/CNPJ nao encontrado como associado da ACIC-DF.');
    }
    $d = $crm['data'] ?? [];
    if (($d['status'] ?? '') !== 'ativo') {
        err(403, 'Associado inativo. Contate a ACIC-DF.');
    }

    // Garante user local (cache/sessao)
    $tipo = strlen($doc) === 14 ? 'empresa' : 'contribuinte';
    $stU = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $stU->execute([$doc]);
    if (!$stU->fetch()) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, higestor_id, primeiro_acesso, ativo, created_at) VALUES (?, ?, '', 1, 1, NOW())")
            ->execute([$doc, $tipo]);
    }

    ok([
        'existe'          => true,
        'primeiro_acesso' => (bool)($d['primeiro_acesso'] ?? true),
        'nome'            => $d['nome'] ?? '',
        'status'          => $d['status'] ?? '',
        'plano'           => $d['plano'] ?? '',
        'is_admin'        => false,
    ]);
}

// ------------------------------------------------------------
// ACTION: first - primeiro acesso, define senha
// ------------------------------------------------------------
if ($action === 'first') {
    $doc    = normalizeDoc($body['cpf_cnpj'] ?? '');
    $passwd = $body['password'] ?? '';
    if (!$doc) err(422, 'CPF/CNPJ obrigatorio');
    if (strlen($passwd) < 6) err(422, 'Senha minima de 6 caracteres');

    $tipo    = strlen($doc) === 14 ? 'empresa' : 'contribuinte';
    $isAdmin = ($doc === ADMIN_DOC);

    if ($isAdmin) {
        // Admin: so local
        $hash = password_hash($passwd, PASSWORD_BCRYPT);
        $st = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
        $st->execute([$doc]);
        $u = $st->fetch();
        if (!$u) {
            $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, higestor_id, password, primeiro_acesso, ativo, created_at)
                           VALUES (?, 'admin', '', ?, 0, 1, NOW())")->execute([$doc, $hash]);
            $uid = (int)$pdo->lastInsertId();
        } else {
            $uid = (int)$u['id'];
            $pdo->prepare('UPDATE conecta_users SET password = ?, primeiro_acesso = 0 WHERE id = ?')
                ->execute([$hash, $uid]);
        }
        $tok = makeToken();
        $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                       VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$uid, $tok]);
        ok([
            'token'            => $tok,
            'tipo'             => 'admin',
            'cpf_cnpj'         => $doc,
            'nome'             => defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador',
            'is_admin'         => true,
            'is_superadmin'    => true,
            'crm_associado_id' => null,
            'primeiro_acesso'  => false,
        ]);
    }

    // Associado: define senha no CRM (fonte de verdade)
    $crm = crmPost('/auth/set-senha-associado', ['documento' => $doc, 'senha' => $passwd]);
    if (!($crm['ok'] ?? false)) {
        err($crm['_http'] ?: 500, $crm['error'] ?? 'Falha ao definir senha no CRM');
    }
    $d = $crm['data'] ?? [];

    // Cache local + sessao
    $hash = password_hash($passwd, PASSWORD_BCRYPT);
    $st = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc]);
    $u = $st->fetch();
    if (!$u) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, higestor_id, password, primeiro_acesso, ativo, crm_associado_id, crm_dados, created_at)
                       VALUES (?, ?, '', ?, 0, 1, ?, ?, NOW())")
            ->execute([$doc, $tipo, $hash, $d['associado_id'] ?? null, json_encode($d, JSON_UNESCAPED_UNICODE)]);
        $uid = (int)$pdo->lastInsertId();
    } else {
        $uid = (int)$u['id'];
        $pdo->prepare('UPDATE conecta_users SET password = ?, primeiro_acesso = 0, crm_associado_id = ?, crm_dados = ? WHERE id = ?')
            ->execute([$hash, $d['associado_id'] ?? null, json_encode($d, JSON_UNESCAPED_UNICODE), $uid]);
    }

    $tok = makeToken();
    $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                   VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$uid, $tok]);

    ok([
        'token'            => $tok,
        'tipo'             => $d['cnpj'] ? 'empresa' : 'contribuinte',
        'cpf_cnpj'         => $d['cnpj'] ?: $d['cpf'] ?: $doc,
        'nome'             => $d['razao_social'] ?: $d['nome_fantasia'] ?: '',
        'status'           => $d['status'] ?? '',
        'plano'            => $d['plano_nome'] ?? '',
        'plano_valor'      => $d['plano_valor'] ?? 0,
        'data_associacao'  => $d['data_associacao'] ?? null,
        'data_vencimento'  => $d['data_vencimento'] ?? null,
        'crm_associado_id' => $d['associado_id'] ?? null,
        'primeiro_acesso'  => false,
        'is_admin'         => false,
        'is_superadmin'    => false,
    ]);
}

// ------------------------------------------------------------
// ACTION: login
// ------------------------------------------------------------
if ($action === 'login') {
    $doc    = normalizeDoc($body['cpf_cnpj'] ?? '');
    $passwd = $body['password'] ?? '';
    if (!$doc || !$passwd) err(422, 'CPF/CNPJ e senha obrigatorios');

    $tipo    = strlen($doc) === 14 ? 'empresa' : 'contribuinte';
    $isAdmin = ($doc === ADMIN_DOC);

    if ($isAdmin) {
        // Admin: verifica senha local
        $st = $pdo->prepare('SELECT * FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
        $st->execute([$doc]);
        $u = $st->fetch();
        if (!$u || empty($u['password'])) err(401, 'primeiro_acesso');
        if (!password_verify($passwd, $u['password'])) err(401, 'Senha incorreta.');

        $tok = makeToken();
        $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                       VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$u['id'], $tok]);
        ok([
            'token'            => $tok,
            'tipo'             => 'admin',
            'cpf_cnpj'         => $doc,
            'nome'             => defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador',
            'is_admin'         => true,
            'is_superadmin'    => true,
            'crm_associado_id' => null,
            'primeiro_acesso'  => false,
        ]);
    }

    // Associado: valida no CRM
    $crm = crmPost('/auth/login-associado', ['documento' => $doc, 'senha' => $passwd]);
    if (!($crm['ok'] ?? false)) {
        if (!empty($crm['primeiro_acesso'])) err(401, 'primeiro_acesso');
        if ((int)($crm['_http'] ?? 0) === 401) err(401, 'Senha incorreta.');
        err((int)($crm['_http'] ?? 500) ?: 500, $crm['error'] ?? 'Falha ao autenticar');
    }
    $d = $crm['data'] ?? [];

    // Cache local + sessao
    $st = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc]);
    $u = $st->fetch();
    if (!$u) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, higestor_id, primeiro_acesso, ativo, crm_associado_id, crm_dados, created_at)
                       VALUES (?, ?, '', 0, 1, ?, ?, NOW())")
            ->execute([$doc, $tipo, $d['associado_id'] ?? null, json_encode($d, JSON_UNESCAPED_UNICODE)]);
        $uid = (int)$pdo->lastInsertId();
    } else {
        $uid = (int)$u['id'];
        $pdo->prepare('UPDATE conecta_users SET crm_associado_id = ?, crm_dados = ? WHERE id = ?')
            ->execute([$d['associado_id'] ?? null, json_encode($d, JSON_UNESCAPED_UNICODE), $uid]);
    }

    $tok = makeToken();
    $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                   VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$uid, $tok]);

    ok([
        'token'            => $tok,
        'tipo'             => $d['cnpj'] ? 'empresa' : 'contribuinte',
        'cpf_cnpj'         => $d['cnpj'] ?: $d['cpf'] ?: $doc,
        'nome'             => $d['razao_social'] ?: $d['nome_fantasia'] ?: '',
        'status'           => $d['status'] ?? '',
        'plano'            => $d['plano_nome'] ?? '',
        'plano_valor'      => $d['plano_valor'] ?? 0,
        'data_associacao'  => $d['data_associacao'] ?? null,
        'data_vencimento'  => $d['data_vencimento'] ?? null,
        'crm_associado_id' => $d['associado_id'] ?? null,
        'primeiro_acesso'  => false,
        'is_admin'         => false,
        'is_superadmin'    => false,
    ]);
}

// ------------------------------------------------------------
// ACTION: dados - retorna dados do associado (cache local, fallback CRM)
// ------------------------------------------------------------
if ($action === 'dados') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');

    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.tipo, u.crm_associado_id, u.crm_dados
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');

    $doc = $u['cpf_cnpj'] ?: '';
    $isAdmin = ($doc === ADMIN_DOC);

    if ($isAdmin) {
        ok([
            'tipo'     => 'admin',
            'nome'     => defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador',
            'cpf_cnpj' => $doc,
            'is_admin' => true,
        ]);
    }

    // Retorna dados do CRM (sem JWT - usa cache local, CRM publico precisa do bridge)
    // O endpoint /associados/{id}/resumo precisa JWT do CRM - usa o cache crm_dados
    $cached = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;
    if (!$cached) err(404, 'Dados do associado nao encontrados no cache');

    // Monta resposta no formato mapEmpresa/mapContribuinte esperado pelo app-bundle
    $tipo = $cached['cnpj'] ? 'empresa' : 'contribuinte';
    $attrs = [
        'razao_social'             => $cached['razao_social'] ?? '',
        'nome'                     => $cached['nome_fantasia'] ?: $cached['razao_social'] ?: '',
        'cnpj'                     => $cached['cnpj'] ?? '',
        'cpf_cnpj'                 => $cached['cnpj'] ?: $cached['cpf'] ?: '',
        'email'                    => $cached['email'] ?? '',
        'telefone'                 => $cached['telefone'] ?? '',
        'celular'                  => $cached['whatsapp'] ?? '',
        'associado'                => ($cached['status'] ?? '') === 'ativo' ? 1 : 0,
        'ativo'                    => ($cached['status'] ?? '') === 'ativo' ? 1 : 0,
        'status'                   => $cached['status'] ?? '',
        'associado_data_registro'  => $cached['data_associacao'] ?? null,
        'associado_registro'       => $cached['plano_nome'] ?? '',
        'categoria'                => $cached['plano_nome'] ?? '',
    ];
    ok([
        'tipo' => $tipo,
        'data' => [
            'id'         => $cached['associado_id'] ?? null,
            'attributes' => $attrs,
        ],
    ]);
}

// ------------------------------------------------------------
// ACTION: validate - valida token e retorna dados basicos
// ------------------------------------------------------------
if ($action === 'validate') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');
    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.crm_associado_id, u.crm_dados
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');
    $doc = $u['cpf_cnpj'] ?: '';
    $cached = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;
    $nome = ($doc === ADMIN_DOC) ? (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador')
           : ($cached['razao_social'] ?? $cached['nome_fantasia'] ?? '');
    ok([
        'data' => [
            'id'               => (int)$u['id'],
            'cpf_cnpj'         => $doc,
            'nome'             => $nome,
            'crm_associado_id' => $u['crm_associado_id'] ? (int)$u['crm_associado_id'] : null,
        ],
    ]);
}

// ------------------------------------------------------------
// ACTION: logout
// ------------------------------------------------------------
if ($action === 'logout') {
    $token = $body['token'] ?? '';
    if ($token) {
        $pdo->prepare('DELETE FROM conecta_sessions WHERE token = ?')->execute([$token]);
    }
    ok();
}

// ------------------------------------------------------------
// ACTION: admin_check - verifica se sessao atual e admin
// ------------------------------------------------------------
if ($action === 'admin_check') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');
    $st = $pdo->prepare(
        'SELECT u.cpf_cnpj FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');
    $doc = $u['cpf_cnpj'] ?: '';
    ok(['is_admin' => $doc === ADMIN_DOC]);
}

// ------------------------------------------------------------
// Unknown action
// ------------------------------------------------------------
err(400, 'Action nao reconhecida: ' . $action);
