<?php
/**
 * auth.php - Conecta 2.0 Authentication API
 * Location: https://conecta.acicdf.org.br/auth.php
 *
 * Fonte unica de verdade: banco local conecta_users (conecta2).
 * HiGestor removido. CRM usado apenas para endpoints de proxy (cobrancas, login-email, validate-sso, aceitar-convite).
 *
 * Actions: check, check-tipo, first, login, dados, validate, logout, admin_check, me,
 *          cobrancas, login-email, validate-sso, aceitar-convite
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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

function input(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    $cache = is_array($decoded) ? $decoded : [];
    return $cache;
}

$body   = input();
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

// Schema upgrade
try {
    $cols = $pdo->query("SHOW COLUMNS FROM conecta_users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('crm_associado_id', $cols, true)) {
        $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_associado_id INT NULL");
    }
    if (!in_array('crm_dados', $cols, true)) {
        try { $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_dados JSON NULL"); }
        catch (\Throwable $e) { $pdo->exec("ALTER TABLE conecta_users ADD COLUMN crm_dados TEXT NULL"); }
    }
} catch (\Throwable $e) { /* best-effort */ }

try {
    $scols = $pdo->query("SHOW COLUMNS FROM conecta_sessions")->fetchAll(PDO::FETCH_COLUMN);
    $adds = [
        'tipo'           => "VARCHAR(30) DEFAULT 'associado_empresa'",
        'permissoes'     => "JSON NULL",
        'empresa_cnpj'   => "VARCHAR(20) NULL",
        'crm_usuario_id' => "INT NULL",
        'nome'           => "VARCHAR(255) NULL",
    ];
    foreach ($adds as $col => $def) {
        if (!in_array($col, $scols, true)) {
            $pdo->exec("ALTER TABLE conecta_sessions ADD COLUMN $col $def");
        }
    }
} catch (\Throwable $e) { /* best-effort */ }

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
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

function makeToken(): string { return bin2hex(random_bytes(32)); }

// ------------------------------------------------------------
// ACTION: check - verifica usuario no banco local conecta_users
// ------------------------------------------------------------
if ($action === 'check') {
    $doc = normalizeDoc($body['cpf_cnpj'] ?? '');
    if (!$doc) err(422, 'CPF/CNPJ obrigatorio');

    $st = $pdo->prepare('SELECT id, nome, password, primeiro_acesso, ativo, tipo, is_admin FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc]);
    $u = $st->fetch();

    if (!$u) {
        ok(['existe' => false]);
    }

    if (!(int)$u['ativo']) {
        err(403, 'Usuario inativo. Contate a ACIC-DF.');
    }

    $primeiro = empty($u['password']) || !empty($u['primeiro_acesso']);
    $isAdmin = ($doc === ADMIN_DOC) || !empty($u['is_admin']);

    ok([
        'existe'          => true,
        'primeiro_acesso' => $primeiro,
        'nome'            => $u['nome'] ?: (($doc === ADMIN_DOC) ? (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador') : ''),
        'status'          => 'ativo',
        'plano'           => $isAdmin ? 'Admin' : '',
        'is_admin'        => $isAdmin,
    ]);
}

// ------------------------------------------------------------
// ACTION: check-tipo - retorna tipo do usuario via token
// ------------------------------------------------------------
if ($action === 'check-tipo') {
    $token = $body['token'] ?? $_GET['token'] ?? '';
    if (!$token) err(422, 'Token obrigatorio');

    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.tipo, u.nome, u.is_admin, s.tipo as sess_tipo, s.nome as sess_nome
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');

    $doc = $u['cpf_cnpj'] ?: '';
    $tipo = $u['sess_tipo'] ?: $u['tipo'] ?: 'associado_empresa';
    $isAdmin = ($doc === ADMIN_DOC) || !empty($u['is_admin']) || in_array($tipo, ['superadmin', 'admin', 'gestor']);

    ok([
        'tipo'             => $tipo,
        'nome'             => $u['sess_nome'] ?: $u['nome'] ?: '',
        'is_admin'         => $isAdmin,
        'destino_possivel' => $isAdmin ? 'crm' : 'conecta',
    ]);
}

// ------------------------------------------------------------
// ACTION: first - primeiro acesso, define senha (local)
// ------------------------------------------------------------
if ($action === 'first') {
    $doc    = normalizeDoc($body['cpf_cnpj'] ?? '');
    $passwd = $body['password'] ?? '';
    if (!$doc) err(422, 'CPF/CNPJ obrigatorio');
    if (strlen($passwd) < 6) err(422, 'Senha minima de 6 caracteres');

    $tipo    = strlen($doc) === 14 ? 'empresa' : 'contribuinte';
    $isAdmin = ($doc === ADMIN_DOC);
    $hash    = password_hash($passwd, PASSWORD_BCRYPT);

    $st = $pdo->prepare('SELECT id, nome, is_admin, ativo FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc]);
    $u = $st->fetch();

    if ($u && !(int)$u['ativo']) {
        err(403, 'Usuario inativo. Contate a ACIC-DF.');
    }

    if (!$u) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, password, primeiro_acesso, ativo, created_at)
                       VALUES (?, ?, ?, 0, 1, NOW())")->execute([$doc, $isAdmin ? 'admin' : $tipo, $hash]);
        $uid = (int)$pdo->lastInsertId();
        $nome = $isAdmin ? (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador') : '';
    } else {
        $uid = (int)$u['id'];
        $pdo->prepare('UPDATE conecta_users SET password = ?, primeiro_acesso = 0 WHERE id = ?')
            ->execute([$hash, $uid]);
        $nome = $u['nome'] ?: ($isAdmin ? (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador') : '');
        $isAdmin = $isAdmin || !empty($u['is_admin']);
    }

    $tok = makeToken();
    $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                   VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$uid, $tok]);

    ok([
        'token'            => $tok,
        'tipo'             => $isAdmin ? 'admin' : $tipo,
        'cpf_cnpj'         => $doc,
        'nome'             => $nome,
        'is_admin'         => $isAdmin,
        'is_superadmin'    => $isAdmin,
        'crm_associado_id' => null,
        'primeiro_acesso'  => false,
    ]);
}

// ------------------------------------------------------------
// ACTION: login - autenticacao local via banco conecta_users
// ------------------------------------------------------------
if ($action === 'login') {
    $doc    = normalizeDoc($body['cpf_cnpj'] ?? '');
    $passwd = $body['password'] ?? '';
    if (!$doc || !$passwd) err(422, 'CPF/CNPJ e senha obrigatorios');

    $st = $pdo->prepare('SELECT * FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc]);
    $u = $st->fetch();

    if (!$u) err(404, 'CPF/CNPJ nao encontrado.');
    if (!(int)$u['ativo']) err(403, 'Usuario inativo. Contate a ACIC-DF.');
    if (empty($u['password'])) err(401, 'primeiro_acesso');
    if (!password_verify($passwd, $u['password'])) err(401, 'Senha incorreta.');

    $isAdmin = ($doc === ADMIN_DOC) || !empty($u['is_admin']);
    $tipo    = $isAdmin ? 'admin' : ($u['tipo'] ?: (strlen($doc) === 14 ? 'empresa' : 'contribuinte'));
    $cached  = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;

    $tok = makeToken();
    $pdo->prepare('INSERT INTO conecta_sessions (user_id, token, created_at, expires_at)
                   VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))')->execute([$u['id'], $tok]);

    ok([
        'token'            => $tok,
        'tipo'             => $tipo,
        'cpf_cnpj'         => $doc,
        'nome'             => $u['nome'] ?: ($cached['razao_social'] ?? $cached['nome_fantasia'] ?? ''),
        'status'           => 'ativo',
        'plano'            => $cached['plano_nome'] ?? '',
        'plano_valor'      => $cached['plano_valor'] ?? 0,
        'data_associacao'  => $cached['data_associacao'] ?? null,
        'data_vencimento'  => $cached['data_vencimento'] ?? null,
        'crm_associado_id' => $u['crm_associado_id'] ? (int)$u['crm_associado_id'] : null,
        'primeiro_acesso'  => false,
        'is_admin'         => $isAdmin,
        'is_superadmin'    => $isAdmin,
    ]);
}

// ------------------------------------------------------------
// ACTION: dados - retorna dados do associado (cache local)
// ------------------------------------------------------------
if ($action === 'dados') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');

    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.tipo, u.nome, u.is_admin, u.crm_associado_id, u.crm_dados
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');

    $doc = $u['cpf_cnpj'] ?: '';
    $isAdmin = ($doc === ADMIN_DOC) || !empty($u['is_admin']);

    if ($isAdmin) {
        ok([
            'tipo'     => 'admin',
            'nome'     => $u['nome'] ?: (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador'),
            'cpf_cnpj' => $doc,
            'is_admin' => true,
        ]);
    }

    $cached = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;
    $tipo = $cached && !empty($cached['cnpj']) ? 'empresa' : ($u['tipo'] ?: 'contribuinte');

    $attrs = [
        'razao_social'             => $cached['razao_social'] ?? $u['nome'] ?? '',
        'nome'                     => $u['nome'] ?: ($cached['nome_fantasia'] ?? $cached['razao_social'] ?? ''),
        'cnpj'                     => $cached['cnpj'] ?? '',
        'cpf_cnpj'                 => $doc,
        'email'                    => $cached['email'] ?? '',
        'telefone'                 => $cached['telefone'] ?? '',
        'celular'                  => $cached['whatsapp'] ?? '',
        'associado'                => 1,
        'ativo'                    => 1,
        'status'                   => 'ativo',
        'associado_data_registro'  => $cached['data_associacao'] ?? null,
        'associado_registro'       => $cached['plano_nome'] ?? '',
        'categoria'                => $cached['plano_nome'] ?? '',
    ];
    ok([
        'tipo' => $tipo,
        'data' => [
            'id'         => $u['crm_associado_id'] ? (int)$u['crm_associado_id'] : (int)$u['id'],
            'attributes' => $attrs,
        ],
    ]);
}

// ------------------------------------------------------------
// ACTION: cobrancas - lista cobrancas do associado logado
// ------------------------------------------------------------
if ($action === 'cobrancas') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');
    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.crm_associado_id
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');
    $aid = (int)($u['crm_associado_id'] ?? 0);
    if (!$aid) err(404, 'Associado nao vinculado');

    $crm = crmGetPublic('/public/associados/' . $aid . '/cobrancas');
    if (!$crm['ok']) err((int)($crm['_http'] ?? 500) ?: 500, $crm['error'] ?? 'Falha ao buscar cobrancas');
    ok(['cobrancas' => $crm['data']['data'] ?? [], 'total' => $crm['data']['total'] ?? 0]);
}

// ------------------------------------------------------------
// ACTION: validate - valida token e retorna dados basicos
// ------------------------------------------------------------
if ($action === 'validate') {
    $token = $body['token'] ?? '';
    if (!$token) err(422, 'token obrigatorio');
    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.nome, u.is_admin, u.crm_associado_id, u.crm_dados, s.tipo, s.permissoes, s.empresa_cnpj, s.nome as sess_nome
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');
    $doc = $u['cpf_cnpj'] ?: '';
    $cached = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;
    $tipo = $u['tipo'] ?: ($doc === ADMIN_DOC ? 'superadmin' : 'associado_empresa');
    $nome = $u['sess_nome'] ?: $u['nome'] ?: (($doc === ADMIN_DOC) ? (defined('ADMIN_NOME') ? ADMIN_NOME : 'Administrador')
           : ($cached['razao_social'] ?? $cached['nome_fantasia'] ?? ''));
    $permissoes = $u['permissoes'] ? json_decode($u['permissoes'], true) : null;
    $isAdmin = in_array($tipo, ['superadmin', 'gestor']) || $doc === ADMIN_DOC || !empty($u['is_admin']);
    ok([
        'data' => [
            'id'               => (int)$u['id'],
            'cpf_cnpj'         => $doc,
            'nome'             => $nome,
            'crm_associado_id' => $u['crm_associado_id'] ? (int)$u['crm_associado_id'] : null,
            'tipo'             => $tipo,
            'permissoes'       => $permissoes,
            'empresa_cnpj'     => $u['empresa_cnpj'] ?? null,
            'is_admin'         => $isAdmin,
            'is_superadmin'    => $tipo === 'superadmin' || $doc === ADMIN_DOC,
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
        'SELECT u.cpf_cnpj, u.is_admin FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');
    $doc = $u['cpf_cnpj'] ?: '';
    ok(['is_admin' => $doc === ADMIN_DOC || !empty($u['is_admin'])]);
}

// ------------------------------------------------------------
// ACTION: login-email — login unificado via email + senha (proxy CRM)
// ------------------------------------------------------------
if ($action === 'login-email') {
    $email = trim($body['email'] ?? '');
    $senha = $body['senha'] ?? $body['password'] ?? '';
    if (!$email || !$senha) err(422, 'Email e senha obrigatorios');

    $crm = crmPost('/auth/login-unificado', ['email' => $email, 'senha' => $senha]);
    if (($crm['_http'] ?? 0) === 401) err(401, 'Credenciais invalidas');
    if (($crm['_http'] ?? 0) === 403) err(403, $crm['error'] ?? 'Usuario inativo');
    if (($crm['_http'] ?? 200) >= 400) err((int)($crm['_http'] ?? 500), $crm['error'] ?? 'Falha ao autenticar');

    $d = $crm['data'] ?? $crm;
    $ssoToken = $d['sso_token'] ?? '';
    $tipo = $d['tipo'] ?? 'associado_empresa';
    $destino = $d['destino'] ?? 'conecta';
    $nome = $d['nome'] ?? '';
    $empresaCnpj = $d['empresa_cnpj'] ?? null;

    $localToken = makeToken();
    $permissoes = null;

    if ($ssoToken) {
        $crmValidate = crmPost('/auth/validate-sso', ['sso_token' => $ssoToken]);
        $dv = $crmValidate['data'] ?? $crmValidate;
        if (($crmValidate['_http'] ?? 200) < 300) {
            $permissoes = $dv['permissoes'] ?? null;
        }
    }

    $crmLogin2 = crmPost('/auth/login-unificado', ['email' => $email, 'senha' => $senha]);
    $d2 = $crmLogin2['data'] ?? $crmLogin2;
    $newSsoToken = $d2['sso_token'] ?? '';

    $st = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? OR cpf_cnpj = ? LIMIT 1');
    $doc = normalizeDoc($empresaCnpj ?? '');
    $st->execute([$doc ?: $email, $empresaCnpj ?: $email]);
    $u = $st->fetch();
    $uid = $u ? (int)$u['id'] : 0;

    if (!$uid) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, primeiro_acesso, ativo, created_at) VALUES (?, ?, 0, 1, NOW())")
            ->execute([$doc ?: $email, $tipo === 'superadmin' ? 'admin' : $tipo]);
        $uid = (int)$pdo->lastInsertId();
    }

    $pdo->prepare(
        'INSERT INTO conecta_sessions (user_id, token, created_at, expires_at, tipo, permissoes, empresa_cnpj, nome, crm_usuario_id)
         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?, ?, ?, ?)'
    )->execute([$uid, $localToken, $tipo, json_encode($permissoes), $empresaCnpj, $nome, $d['usuario_id'] ?? null]);

    $isAdmin = in_array($tipo, ['superadmin', 'gestor']);

    ok([
        'token'            => $localToken,
        'sso_token'        => $newSsoToken,
        'tipo'             => $tipo,
        'destino'          => $destino,
        'nome'             => $nome,
        'email'            => $email,
        'empresa_cnpj'     => $empresaCnpj,
        'is_admin'         => $isAdmin,
        'is_superadmin'    => $tipo === 'superadmin',
        'permissoes'       => $permissoes,
        'redirect_crm'     => $d2['redirect_crm'] ?? null,
        'redirect_conecta' => $d2['redirect_conecta'] ?? null,
    ]);
}

// ------------------------------------------------------------
// ACTION: validate-sso
// ------------------------------------------------------------
if ($action === 'validate-sso') {
    $ssoToken = $body['sso_token'] ?? $_GET['sso_token'] ?? '';
    if (!$ssoToken) err(422, 'sso_token obrigatorio');

    $crm = crmPost('/auth/validate-sso', ['sso_token' => $ssoToken]);
    if (($crm['_http'] ?? 0) === 401) err(401, 'Token invalido ou expirado');
    if (($crm['_http'] ?? 200) >= 400) err((int)($crm['_http'] ?? 500), $crm['error'] ?? 'Falha ao validar SSO');

    $d = $crm['data'] ?? $crm;
    $tipo = $d['tipo'] ?? 'associado_empresa';
    $nome = $d['nome'] ?? '';
    $email = $d['email'] ?? '';
    $empresaCnpj = $d['empresa_cnpj'] ?? null;
    $permissoes = $d['permissoes'] ?? null;
    $associadoId = $d['associado_id'] ?? null;

    $localToken = makeToken();
    $doc = normalizeDoc($empresaCnpj ?? '');

    $st = $pdo->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? OR cpf_cnpj = ? LIMIT 1');
    $st->execute([$doc ?: $email, $empresaCnpj ?: $email]);
    $u = $st->fetch();
    $uid = $u ? (int)$u['id'] : 0;
    if (!$uid) {
        $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, primeiro_acesso, ativo, crm_associado_id, created_at) VALUES (?, ?, 0, 1, ?, NOW())")
            ->execute([$doc ?: $email, $tipo === 'superadmin' ? 'admin' : $tipo, $associadoId]);
        $uid = (int)$pdo->lastInsertId();
    }

    $pdo->prepare(
        'INSERT INTO conecta_sessions (user_id, token, created_at, expires_at, tipo, permissoes, empresa_cnpj, nome, crm_usuario_id)
         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?, ?, ?, ?)'
    )->execute([$uid, $localToken, $tipo, json_encode($permissoes), $empresaCnpj, $nome, $d['usuario_id'] ?? null]);

    $isAdmin = in_array($tipo, ['superadmin', 'gestor']);

    ok([
        'token'            => $localToken,
        'tipo'             => $tipo,
        'nome'             => $nome,
        'email'            => $email,
        'empresa_cnpj'     => $empresaCnpj,
        'is_admin'         => $isAdmin,
        'is_superadmin'    => $tipo === 'superadmin',
        'permissoes'       => $permissoes,
        'primeiro_acesso'  => $crm['primeiro_acesso'] ?? false,
        'crm_associado_id' => $associadoId,
    ]);
}

// ------------------------------------------------------------
// ACTION: aceitar-convite
// ------------------------------------------------------------
if ($action === 'aceitar-convite') {
    $conviteToken = $body['convite_token'] ?? '';
    $senha = $body['senha'] ?? '';
    $confirmar = $body['confirmar_senha'] ?? '';
    if (!$conviteToken) err(422, 'convite_token obrigatorio');
    if (strlen($senha) < 8) err(422, 'Senha minima: 8 caracteres');
    if ($senha !== $confirmar) err(422, 'Senhas nao conferem');

    $crm = crmPost('/auth/aceitar-convite', [
        'convite_token'  => $conviteToken,
        'senha'          => $senha,
        'confirmar_senha' => $confirmar,
    ]);
    if (($crm['_http'] ?? 0) === 401) err(401, 'Convite invalido ou expirado');
    if (($crm['_http'] ?? 200) >= 400) err((int)($crm['_http'] ?? 500), $crm['error'] ?? 'Falha ao aceitar convite');

    $dc = $crm['data'] ?? $crm;
    $ssoToken = $dc['sso_token'] ?? '';
    if (!$ssoToken) err(500, 'SSO token nao retornado');

    $validate = crmPost('/auth/validate-sso', ['sso_token' => $ssoToken]);
    if (($validate['_http'] ?? 200) >= 400) err(500, 'Falha ao validar SSO apos convite');

    $dv = $validate['data'] ?? $validate;
    $tipo = $dv['tipo'] ?? 'colaborador';
    $nome = $dv['nome'] ?? '';
    $localToken = makeToken();
    $permissoes = $dv['permissoes'] ?? null;

    $pdo->prepare("INSERT INTO conecta_users (cpf_cnpj, tipo, primeiro_acesso, ativo, created_at) VALUES (?, ?, 0, 1, NOW())")
        ->execute([$dv['email'] ?? '', $tipo]);
    $uid = (int)$pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO conecta_sessions (user_id, token, created_at, expires_at, tipo, permissoes, empresa_cnpj, nome, crm_usuario_id)
         VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?, ?, ?, ?)'
    )->execute([$uid, $localToken, $tipo, json_encode($permissoes), $dv['empresa_cnpj'] ?? null, $nome, $dv['usuario_id'] ?? null]);

    ok([
        'token'       => $localToken,
        'tipo'        => $tipo,
        'nome'        => $nome,
        'permissoes'  => $permissoes,
        'redirect'    => '/',
    ]);
}

// ------------------------------------------------------------
// ACTION: me - retorna dados do usuario logado via token (GET-friendly)
// ------------------------------------------------------------
if ($action === 'me') {
    $token = $body['token'] ?? $_GET['token'] ?? '';
    if (!$token) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = $m[1];
        }
    }
    if (!$token) err(422, 'Token obrigatorio');

    $st = $pdo->prepare(
        'SELECT u.id, u.cpf_cnpj, u.tipo, u.nome, u.is_admin, u.crm_associado_id, u.crm_dados,
                s.tipo AS sess_tipo, s.permissoes, s.empresa_cnpj, s.nome AS sess_nome
         FROM conecta_sessions s
         JOIN conecta_users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1'
    );
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) err(401, 'Sessao invalida ou expirada');

    $doc = $u['cpf_cnpj'] ?: '';
    $cached = $u['crm_dados'] ? json_decode($u['crm_dados'], true) : null;
    $tipo = $u['sess_tipo'] ?: $u['tipo'] ?: 'associado_empresa';
    $nome = $u['sess_nome'] ?: $u['nome'] ?: ($cached['razao_social'] ?? $cached['nome_fantasia'] ?? '');
    $permissoes = $u['permissoes'] ? json_decode($u['permissoes'], true) : null;
    $isAdmin = in_array($tipo, ['superadmin', 'gestor']) || $doc === ADMIN_DOC || !empty($u['is_admin']);

    ok([
        'id'               => (int)$u['id'],
        'cpf_cnpj'         => $doc,
        'nome'             => $nome,
        'tipo'             => $tipo,
        'email'            => $cached['email'] ?? '',
        'empresa_cnpj'     => $u['empresa_cnpj'] ?? null,
        'crm_associado_id' => $u['crm_associado_id'] ? (int)$u['crm_associado_id'] : null,
        'is_admin'         => $isAdmin,
        'is_superadmin'    => $tipo === 'superadmin' || $doc === ADMIN_DOC,
        'permissoes'       => $permissoes,
    ]);
}

// ------------------------------------------------------------
// Unknown action
// ------------------------------------------------------------
err(400, 'Action nao reconhecida: ' . $action);
