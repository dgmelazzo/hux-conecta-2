<?php
/**
 * Conecta 2.0 — Auth Proxy
 *
 * Proxy fino para a CRM API. O CRM e a fonte unica de verdade
 * para autenticacao de todos os perfis.
 *
 * Endpoints CRM usados:
 *   POST /auth/conecta-check          → verifica documento
 *   POST /auth/conecta-login          → login (doc + senha)
 *   POST /auth/conecta-primeiro-acesso → define senha
 *   POST /auth/conecta-validate       → valida token JWT
 *   GET  /auth/me                     → dados do usuario logado
 *   GET  /cobrancas                   → cobrancas do associado
 */

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://conecta.acicdf.org.br', 'https://hml.conecta.acicdf.org.br', 'https://crm.acicdf.org.br', 'https://hml.crm.acicdf.org.br'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) header('Access-Control-Allow-Origin: ' . $origin);
else header('Access-Control-Allow-Origin: https://conecta.acicdf.org.br');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Config
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$bearer = str_replace('Bearer ', '', trim($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

// ============================================================
// Helper: proxy para CRM API
// ============================================================
function crmApi(string $method, string $endpoint, array $data = [], ?string $token = null): array {
    $url = CRM_API_URL . $endpoint;
    $ch  = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("[CONECTA-PROXY] $method $endpoint curl_error: $err");
        return ['_ok' => false, '_http' => 0, 'error' => 'Erro de conexao com o servidor'];
    }

    $decoded = json_decode($resp ?: '{}', true) ?? [];
    $decoded['_ok']   = $code >= 200 && $code < 400;
    $decoded['_http'] = $code;
    return $decoded;
}

function ok($data) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function err($code, $msg) { http_response_code($code); echo json_encode(['success' => false, 'message' => $msg]); exit; }

function getDB(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}

// ============================================================
// ACTION: check — verifica se documento existe no CRM
// ============================================================
if ($action === 'check') {
    $doc = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? $input['documento'] ?? '');
    if (strlen($doc) < 11) err(400, 'CPF/CNPJ obrigatorio');

    $resp = crmApi('POST', '/auth/conecta-check', ['documento' => $doc]);

    if (!$resp['_ok']) err($resp['_http'] ?: 500, $resp['error'] ?? 'Erro ao verificar documento');

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: check-tipo — retorna tipo do usuario (para login unificado email)
// ============================================================
if ($action === 'check-tipo') {
    $email = trim($input['email'] ?? '');
    if (!$email) err(400, 'Email obrigatorio');

    $resp = crmApi('POST', '/auth/conecta-check', ['documento' => $email]);
    if (!$resp['_ok']) err($resp['_http'] ?: 500, $resp['error'] ?? 'Erro ao verificar email');

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: login — autentica doc + senha via CRM
// ============================================================
if ($action === 'login') {
    $doc   = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? $input['documento'] ?? '');
    $senha = $input['password'] ?? $input['senha'] ?? '';
    if (strlen($doc) < 11) err(400, 'CPF/CNPJ obrigatorio');
    if (!$senha) err(400, 'Senha obrigatoria');

    $resp = crmApi('POST', '/auth/conecta-login', [
        'documento' => $doc,
        'senha'     => $senha,
    ]);

    if (!$resp['_ok']) {
        $code = $resp['_http'] ?: 401;
        $msg  = $resp['error'] ?? 'Credenciais invalidas';
        err($code, $msg);
    }

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: login-email — login unificado via email + senha
// ============================================================
if ($action === 'login-email') {
    $email = trim($input['email'] ?? '');
    $senha = $input['senha'] ?? $input['password'] ?? '';
    if (!$email || !$senha) err(400, 'Email e senha obrigatorios');

    $resp = crmApi('POST', '/auth/conecta-login', [
        'documento' => $email,
        'senha'     => $senha,
    ]);

    if (!$resp['_ok']) {
        err($resp['_http'] ?: 401, $resp['error'] ?? 'Credenciais invalidas');
    }

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);

    // Compatibilidade com app-bundle.js
    $data['tipo']        = $data['role'] ?? 'associado_empresa';
    $data['is_admin']    = in_array($data['role'] ?? '', ['superadmin', 'gestor']);
    $data['sso_token']   = $data['token'] ?? null;

    ok($data);
}

// ============================================================
// ACTION: token-info — valida JWT de primeiro-acesso e retorna dados
// ============================================================
if ($action === 'token-info') {
    $token = $input['token'] ?? '';
    if (!$token) err(400, 'Token obrigatorio');
    $resp = crmApi('POST', '/auth/primeiro-acesso-token', ['token' => $token]);
    if (!$resp['_ok']) err($resp['_http'] ?: 401, $resp['error'] ?? 'Link inválido ou expirado');
    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: token-set — define senha via JWT de primeiro-acesso (seguro)
// ============================================================
if ($action === 'token-set') {
    $token = $input['token'] ?? '';
    $senha = $input['senha'] ?? $input['password'] ?? '';
    if (!$token) err(400, 'Token obrigatorio');
    if (strlen($senha) < 8) err(400, 'Senha minima 8 caracteres');
    $resp = crmApi('POST', '/auth/primeiro-acesso-via-token', ['token' => $token, 'senha' => $senha]);
    if (!$resp['_ok']) err($resp['_http'] ?: 500, $resp['error'] ?? 'Erro ao definir senha');
    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: first — primeiro acesso, define senha via CRM
// ============================================================
if ($action === 'first') {
    $doc   = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? $input['documento'] ?? '');
    $senha = $input['password'] ?? $input['senha'] ?? '';
    if (strlen($doc) < 11 || strlen($senha) < 8) err(400, 'Documento e senha (min 8 chars) obrigatorios');

    $resp = crmApi('POST', '/auth/conecta-primeiro-acesso', [
        'documento' => $doc,
        'senha'     => $senha,
    ]);

    if (!$resp['_ok']) err($resp['_http'] ?: 500, $resp['error'] ?? 'Erro ao definir senha');

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: validate — valida token JWT via CRM
// ============================================================
if ($action === 'validate') {
    $token = $input['token'] ?? $bearer;
    if (!$token) err(401, 'Token obrigatorio');

    $resp = crmApi('POST', '/auth/conecta-validate', ['token' => $token]);

    if (!$resp['_ok']) err(401, 'Token invalido ou expirado');

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: dados — retorna dados do associado logado
// ============================================================
if ($action === 'dados') {
    $token = $input['token'] ?? $bearer;
    if (!$token) err(401, 'Token obrigatorio');

    $resp = crmApi('GET', '/auth/me', [], $token);
    if (!$resp['_ok']) err(401, 'Sessao invalida');

    $data = $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: cobrancas — lista cobrancas do associado logado
// ============================================================
if ($action === 'cobrancas') {
    $token = $input['token'] ?? $bearer;
    if (!$token) err(401, 'Token obrigatorio');

    // Primeiro valida o token e pega o associado_id
    $validate = crmApi('POST', '/auth/conecta-validate', ['token' => $token]);
    if (!$validate['_ok']) err(401, 'Sessao invalida');

    $vdata = $validate['data']['data'] ?? $validate['data'] ?? $validate;

    // Admin vê todas as cobrancas (sem filtro)
    if ($vdata['is_admin'] ?? false) {
        $resp = crmApi('GET', '/cobrancas?limit=50', [], $token);
    } else {
        // Associado vê só as próprias
        $doc = $vdata['documento'] ?? '';
        $resp = crmApi('GET', '/cobrancas?busca=' . urlencode($doc) . '&limit=50', [], $token);
    }

    if (!$resp['_ok']) err(500, 'Erro ao buscar cobrancas');

    $data = $resp['data'] ?? $resp;
    $cobrancas = $data['data'] ?? $data['cobrancas'] ?? [];
    ok(['cobrancas' => $cobrancas]);
}

// ============================================================
// ACTION: admin_check — verifica se sessao e admin
// ============================================================
if ($action === 'admin_check') {
    $token = $input['token'] ?? $bearer;
    if (!$token) err(401, 'Token obrigatorio');

    $resp = crmApi('POST', '/auth/conecta-validate', ['token' => $token]);
    if (!$resp['_ok']) ok(['is_admin' => false]);

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    ok([
        'is_admin'      => (bool)($data['is_admin'] ?? false),
        'is_superadmin' => in_array($data['role'] ?? '', ['superadmin']),
        'nome'          => $data['nome'] ?? '',
    ]);
}

// ============================================================
// ACTION: permissoes — busca modulos permitidos para o perfil
// ============================================================
if ($action === 'permissoes') {
    $token = $input['token'] ?? $bearer;
    if (!$token) err(401, 'Token obrigatorio');

    $resp = crmApi('GET', '/permissoes/modulos', [], $token);
    if (!$resp['_ok']) err(401, 'Token invalido');

    $data = $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    ok($data);
}

// ============================================================
// ACTION: logout — noop (JWT stateless)
// ============================================================
if ($action === 'logout') {
    ok(['logged_out' => true]);
}

// ============================================================
// ACTION: validate-sso — compatibilidade com SSO antigo
// ============================================================
if ($action === 'validate-sso') {
    $ssoToken = $input['sso_token'] ?? $input['token'] ?? $bearer;
    if (!$ssoToken) err(401, 'Token obrigatorio');

    $resp = crmApi('POST', '/auth/conecta-validate', ['token' => $ssoToken]);
    if (!$resp['_ok']) err(401, 'Token invalido');

    $data = $resp['data']['data'] ?? $resp['data'] ?? $resp;
    unset($data['_ok'], $data['_http']);
    $data['tipo']     = $data['role'] ?? 'associado_empresa';
    $data['is_admin'] = (bool)($data['is_admin'] ?? false);
    ok($data);
}

// ============================================================
// ACTION: register_from_crm — provisiona usuário vindo do CRM
// Idempotente: cpf_cnpj já existente retorna registro atual.
// ============================================================
if ($action === 'register_from_crm') {
    $secret = $_SERVER['HTTP_X_CONECTA_BRIDGE_SECRET'] ?? '';
    if (!$secret || !hash_equals(CONECTA_BRIDGE_SECRET, $secret)) {
        err(401, 'Unauthorized');
    }

    $doc  = preg_replace('/\D/', '', $input['cpf_cnpj'] ?? '');
    $tipo = $input['tipo'] ?? 'empresa';
    if (strlen($doc) < 11) err(400, 'cpf_cnpj obrigatorio');

    try {
        $db = getDB();

        $st = $db->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj = ? LIMIT 1');
        $st->execute([$doc]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            ok(['conecta_user_id' => (int)$existing['id'], 'already_existed' => true]);
        }

        $db->prepare(
            'INSERT INTO conecta_users (cpf_cnpj, tipo, higestor_id, primeiro_acesso, ativo, created_at)
             VALUES (?, ?, \'\', 1, 1, NOW())'
        )->execute([$doc, $tipo]);

        ok(['conecta_user_id' => (int)$db->lastInsertId(), 'already_existed' => false]);

    } catch (\Throwable $e) {
        error_log('[register_from_crm] ' . $e->getMessage());
        err(500, 'Erro interno ao provisionar usuario');
    }
}

// Acao desconhecida
err(400, 'Acao invalida');
