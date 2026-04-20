<?php
require_once __DIR__ . '/auth-helper.php';
/**
 * ACIC CONECTA 2.0 — API Superadmin
 * ====================================
 * Arquivo: admin.php
 * Todas as rotas exigem token de superadmin
 *
 * GET  ?action=usuarios          → lista todos os usuários
 * GET  ?action=usuario&id=X      → detalhe + dados HiGestor
 * POST ?action=bloquear          → ativa/bloqueia usuário
 * POST ?action=resetar_senha     → reseta senha (exige novo acesso)
 * GET  ?action=metricas          → dashboard completo
 * GET  ?action=acessos           → acessos por dia (últimos 30 dias)
 * GET  ?action=sem_acesso        → associados que nunca acessaram
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://conecta.acicdf.org.br', 'https://hml.conecta.acicdf.org.br', 'https://crm.acicdf.org.br', 'https://hml.crm.acicdf.org.br'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) header('Access-Control-Allow-Origin: ' . $origin);
else header('Access-Control-Allow-Origin: https://conecta.acicdf.org.br');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── DB ──────────────────────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

// ── HELPERS ─────────────────────────────────────────────────
function ok($d)     { echo json_encode(['success'=>true,'data'=>$d]); exit; }
function err($c,$m) { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit; }
function input()    { static $c=null; if($c!==null)return $c; $c=json_decode(file_get_contents('php://input'),true)?:[]; return $c; }

// ── AUTH SUPERADMIN ─────────────────────────────────────────
function requireSuperAdmin() {
    // CRM JWT primeiro (fonte unica de verdade)
    $user = requireCrmAdmin();
    if ($user) return $user['token'] ?? '';
    // Fallback legado: conecta_sessions
    $db  = getDB();
    $tok = str_replace('Bearer ','',trim($_SERVER['HTTP_AUTHORIZATION']??''));
    if (!$tok) {
        $in  = input();
        $tok = $in['token'] ?? '';
    }
    if (!$tok) err(401,'Token ausente.');
    $st = $db->prepare('SELECT u.cpf_cnpj, u.tipo, s.tipo AS sess_tipo FROM conecta_sessions s JOIN conecta_users u ON u.id=s.user_id WHERE s.token=? AND s.expires_at>NOW() AND u.ativo=1');
    $st->execute([$tok]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) err(401,'Sessão inválida ou expirada.');
    // Admin por CPF (fluxo legado) ou por tipo de sessão SSO (superadmin/gestor)
    $docMatch = preg_replace('/\D/','',$row['cpf_cnpj']) === preg_replace('/\D/','',ADMIN_DOC);
    $tipoMatch = in_array($row['tipo'] ?? $row['sess_tipo'] ?? '', ['admin','superadmin','gestor']);
    if (!$docMatch && !$tipoMatch)
        err(403,'Acesso restrito ao superadmin.');
    return $tok;
}

// ── CRM BRIDGE ──────────────────────────────────────────────
function crmGet($path) {
    $ch = curl_init(CRM_API_URL.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['X-Conecta-Secret: '.CRM_BRIDGE_SECRET,'Content-Type: application/json']]);
    $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200) return null;
    return json_decode($res,true);
}

// ── AUTH: qualquer associado logado ─────────────────────────
function requireAuth() {
    // CRM JWT primeiro
    $user = requireCrmAuth();
    if ($user) return $user;
    // Fallback legado
    $db  = getDB();
    $tok = str_replace('Bearer ','',trim($_SERVER['HTTP_AUTHORIZATION']??''));
    if (!$tok) { $in = input(); $tok = $in['token'] ?? ''; }
    if (!$tok) err(401,'Token ausente.');
    $st = $db->prepare('SELECT u.id FROM conecta_sessions s JOIN conecta_users u ON u.id=s.user_id WHERE s.token=? AND s.expires_at>NOW() AND u.ativo=1');
    $st->execute([$tok]);
    if (!$st->fetch()) err(401,'Sessão inválida ou expirada.');
}

// ── SETUP TABELAS ────────────────────────────────────────────
getDB()->exec("CREATE TABLE IF NOT EXISTS conecta_acessos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    cpf_cnpj   VARCHAR(20),
    ip         VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

getDB()->exec("CREATE TABLE IF NOT EXISTS conecta_links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titulo     VARCHAR(200) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    icone      VARCHAR(10)  DEFAULT '🔗',
    ordem      INT          DEFAULT 0,
    cliques    INT          DEFAULT 0,
    ativo      TINYINT(1)   DEFAULT 1,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ── ROTAS ────────────────────────────────────────────────────
$action = $_GET['action'] ?? (input()['action'] ?? '');

// Rotas públicas (qualquer associado autenticado) — NÃO exigem superadmin
switch ($action) {
    case 'links_listar':
        requireAuth();
        $links = getDB()->query(
            "SELECT id, titulo, url, icone, ordem, cliques FROM conecta_links WHERE ativo=1 ORDER BY ordem ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        ok($links);
        break;

    case 'links_clique':
        requireAuth();
        $in = input();
        if (!empty($in['id'])) {
            getDB()->prepare("UPDATE conecta_links SET cliques = cliques + 1 WHERE id=?")->execute([(int)$in['id']]);
        }
        ok(true);
        break;

    case 'admin_check':
        $tok = str_replace('Bearer ','',trim($_SERVER['HTTP_AUTHORIZATION']??''));
        if (!$tok) { $in = input(); $tok = $in['token'] ?? ''; }
        if (!$tok) { ok(['is_admin'=>false]); }
        $st = getDB()->prepare('SELECT u.cpf_cnpj FROM conecta_sessions s JOIN conecta_users u ON u.id=s.user_id WHERE s.token=? AND s.expires_at>NOW() AND u.ativo=1');
        $st->execute([$tok]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { ok(['is_admin'=>false]); }
        ok(['is_admin' => preg_replace('/\D/','',$row['cpf_cnpj']) === preg_replace('/\D/','',ADMIN_DOC)]);
        break;
}

// Rotas de superadmin — exigem token de admin
requireSuperAdmin();

switch ($action) {

    // ── Lista todos os usuários cadastrados no portal ──
    case 'usuarios':
        $db   = getDB();
        $stmt = $db->query("
            SELECT u.id, u.cpf_cnpj, u.tipo, u.higestor_id,
                   u.primeiro_acesso, u.ativo, u.created_at,
                   COALESCE(
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.razao_social')),
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome')),
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome_fantasia')),
                     ''
                   ) AS nome,
                   MAX(s.created_at) AS ultimo_acesso,
                   COUNT(DISTINCT s.id) AS total_sessoes
            FROM conecta_users u
            LEFT JOIN conecta_sessions s ON s.user_id = u.id
            GROUP BY u.id, u.cpf_cnpj, u.tipo, u.higestor_id,
                     u.primeiro_acesso, u.ativo, u.created_at, u.crm_dados
            ORDER BY u.created_at DESC
        ");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata CPF/CNPJ para exibição
        foreach ($usuarios as &$u) {
            $doc = $u['cpf_cnpj'];
            if (strlen($doc) === 11) {
                $u['doc_fmt'] = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/','$1.$2.$3-$4',$doc);
            } else {
                $u['doc_fmt'] = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/','$1.$2.$3/$4-$5',$doc);
            }
            $u['status_label'] = $u['ativo'] ? 'Ativo' : 'Bloqueado';
            $u['acesso_label'] = $u['primeiro_acesso'] ? 'Pendente' : 'Cadastrado';
        }
        ok($usuarios);
        break;

    // ── Busca por associado (autocomplete em comunicados) ──
    case 'buscar_associado':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) err(400, 'Termo muito curto.');
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.cpf_cnpj, u.tipo,
                   COALESCE(
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.razao_social')),
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome_fantasia')),
                     JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome')),
                     ''
                   ) AS nome,
                   JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.email')) AS email
            FROM conecta_users u
            WHERE u.ativo = 1 AND (
              u.cpf_cnpj LIKE :q
              OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.razao_social'))) LIKE LOWER(:q)
              OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome_fantasia'))) LIKE LOWER(:q)
              OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.nome'))) LIKE LOWER(:q)
              OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(u.crm_dados, '$.email'))) LIKE LOWER(:q)
            )
            ORDER BY nome ASC
            LIMIT 15
        ");
        $stmt->execute([':q' => '%' . $q . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as &$u) {
            $doc = $u['cpf_cnpj'];
            if (strlen($doc) === 11) {
                $u['doc_fmt'] = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
            } elseif (strlen($doc) === 14) {
                $u['doc_fmt'] = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
            } else {
                $u['doc_fmt'] = $doc;
            }
        }
        ok($results);
        break;

    // ── Detalhe do usuário + dados HiGestor ──
    case 'usuario':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err(400,'ID obrigatório.');
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT u.*, MAX(s.created_at) AS ultimo_acesso, COUNT(DISTINCT s.id) AS total_sessoes
            FROM conecta_users u
            LEFT JOIN conecta_sessions s ON s.user_id = u.id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) err(404,'Usuário não encontrado.');

        // Dados do CRM
        $crm_id = $u['crm_associado_id'] ?? 0;
        $attrs = [];
        if ($crm_id) {
            $crm = crmGet('/associados/'.$crm_id.'/resumo');
            $attrs = $crm['data'] ?? [];
        }

        // Últimos 10 acessos
        $acessos = $db->prepare("SELECT created_at, ip FROM conecta_acessos WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
        $acessos->execute([$id]);

        ok([
            'usuario'  => $u,
            'crm'      => $attrs,
            'acessos'  => $acessos->fetchAll(PDO::FETCH_ASSOC),
        ]);
        break;

    // ── Ativar / Bloquear usuário ──
    case 'bloquear':
        $in = input();
        if (empty($in['id'])) err(400,'ID obrigatório.');
        $db   = getDB();
        $stmt = $db->prepare('SELECT ativo FROM conecta_users WHERE id=?');
        $stmt->execute([$in['id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) err(404,'Usuário não encontrado.');

        $novoStatus = $u['ativo'] ? 0 : 1;
        $db->prepare('UPDATE conecta_users SET ativo=? WHERE id=?')->execute([$novoStatus,$in['id']]);

        // Encerra sessões se bloqueando
        if (!$novoStatus) {
            $db->prepare('DELETE FROM conecta_sessions WHERE user_id=?')->execute([$in['id']]);
        }

        ok(['id'=>$in['id'],'ativo'=>$novoStatus,'label'=>$novoStatus?'Ativo':'Bloqueado']);
        break;

    // ── Resetar senha (DEPRECADO — reset deve passar pelo CRM) ──
    case 'resetar_senha':
        // Brecha: este caminho permitia resetar senha sem auth do CRM.
        // O reset agora deve ser feito via /associados/me/colaboradores/{id}/reenviar-convite (CRM)
        // que gera novo JWT e envia email. Mantemos só o invalidate de sessão local.
        $in = input();
        if (empty($in['id'])) err(400,'ID obrigatório.');
        $db = getDB();
        // NÃO mexe em password — apenas invalida sessões locais (forçando re-login via CRM)
        $db->prepare('DELETE FROM conecta_sessions WHERE user_id=?')->execute([$in['id']]);
        ok([
          'reset'=>true,
          'message'=>'Sessões locais invalidadas. Para reenviar convite, use o CRM (Meu Perfil > Colaboradores).',
          'note'=>'reset_senha_via_crm_apenas',
        ]);
        break;

    // ── Dashboard de métricas ──
    case 'metricas':
        $db = getDB();

        // Totais gerais via CRM (source of truth) — fallback se sem acesso cross-DB
        // TODO refator: chamar API CRM via HTTP em vez de cross-DB direto
        $totais = ['total_usuarios'=>0,'usuarios_ativos'=>0,'usuarios_bloqueados'=>0,'aguardando_acesso'=>0,'empresas'=>0,'contribuintes'=>0,'_indisponivel'=>true];
        try {
            $crmDb = new PDO('mysql:host='.DB_HOST.';dbname=conecta_crm;charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $totais = $crmDb->query("
                SELECT
                    COUNT(*) AS total_usuarios,
                    SUM(status='ativo') AS usuarios_ativos,
                    SUM(status IN ('cancelado','suspenso')) AS usuarios_bloqueados,
                    0 AS aguardando_acesso,
                    SUM(categoria='empresa') AS empresas,
                    SUM(categoria='colaborador') AS contribuintes
                FROM associados WHERE tenant_id = 1
            ")->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[METRICAS] CRM cross-DB sem acesso: '.$e->getMessage());
        }

        // Helper inline pra blindar queries opcionais
        $tryQuery = function($sql, $fetchAll = false) use ($db) {
            try {
                $st = $db->query($sql);
                return $fetchAll ? $st->fetchAll(PDO::FETCH_ASSOC) : $st->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('[METRICAS] '.$e->getMessage());
                return $fetchAll ? [] : null;
            }
        };

        $produtos    = $tryQuery("SELECT COUNT(*) AS total_produtos, SUM(status='ativo') AS produtos_ativos, SUM(views) AS total_views, SUM(clicks) AS total_clicks FROM conecta_produtos") ?: ['total_produtos'=>0,'produtos_ativos'=>0,'total_views'=>0,'total_clicks'=>0];
        $top_views   = $tryQuery("SELECT nome, categoria_id, views, clicks, slug FROM conecta_produtos WHERE status='ativo' ORDER BY views DESC LIMIT 5", true);
        $top_clicks  = $tryQuery("SELECT p.nome, c.nome AS categoria, p.views, p.clicks, p.slug FROM conecta_produtos p LEFT JOIN conecta_categorias c ON c.id = p.categoria_id WHERE p.status='ativo' ORDER BY p.clicks DESC LIMIT 5", true);
        $acessos_30d = $tryQuery("SELECT DATE(created_at) AS dia, COUNT(*) AS total FROM conecta_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY dia ASC", true);
        $sem_acesso  = $tryQuery("SELECT u.cpf_cnpj, u.tipo, u.higestor_id, u.created_at FROM conecta_users u LEFT JOIN conecta_sessions s ON s.user_id = u.id WHERE s.id IS NULL OR u.primeiro_acesso = 1 GROUP BY u.id ORDER BY u.created_at DESC LIMIT 20", true);
        $tracking    = $tryQuery("SELECT tipo_evento, COUNT(*) AS total FROM conecta_tracking GROUP BY tipo_evento", true);

        ok([
            'totais'      => $totais,
            'produtos'    => $produtos,
            'top_views'   => $top_views,
            'top_clicks'  => $top_clicks,
            'acessos_30d' => $acessos_30d,
            'sem_acesso'  => $sem_acesso,
            'tracking'    => $tracking,
        ]);
        break;

    // ── Registrar acesso (chamado internamente no login) ──
    case 'log_acesso':
        $in  = input();
        $uid = (int)($in['user_id'] ?? 0);
        $cpf = $in['cpf_cnpj'] ?? '';
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($uid) {
            getDB()->prepare('INSERT INTO conecta_acessos(user_id,cpf_cnpj,ip)VALUES(?,?,?)')->execute([$uid,$cpf,$ip]);
        }
        ok(['logged'=>true]);
        break;

    // ── Links Importantes ──
    case 'links_criar':
        $in = input();
        if (empty($in['titulo']) || empty($in['url'])) err(400,'Título e URL obrigatórios.');
        getDB()->prepare(
            "INSERT INTO conecta_links (titulo, url, icone, ordem) VALUES (?, ?, ?, ?)"
        )->execute([
            trim($in['titulo']),
            trim($in['url']),
            trim($in['icone'] ?? '🔗'),
            (int)($in['ordem'] ?? 0),
        ]);
        ok(['id' => getDB()->lastInsertId()]);
        break;

    case 'links_editar':
        $in = input();
        if (empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare(
            "UPDATE conecta_links SET titulo=?, url=?, icone=?, ordem=? WHERE id=?"
        )->execute([
            trim($in['titulo']),
            trim($in['url']),
            trim($in['icone'] ?? '🔗'),
            (int)($in['ordem'] ?? 0),
            (int)$in['id'],
        ]);
        ok(true);
        break;

    case 'links_excluir':
        $in = input();
        if (empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("DELETE FROM conecta_links WHERE id=?")->execute([(int)$in['id']]);
        ok(true);
        break;

    // ── Parceiros CRUD ──
    case 'parceiros':
        $rows = getDB()->query("
            SELECT id, nome, nome_fantasia, cnpj, email, telefone, site, logo_url, categoria, descricao, split_percentual, ativo
            FROM conecta_parceiros ORDER BY nome ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        ok($rows);
        break;

    case 'parceiro_criar':
        $in = input();
        if (empty($in['nome'])) err(400, 'Nome obrigatório.');
        getDB()->prepare("
            INSERT INTO conecta_parceiros (nome, nome_fantasia, cnpj, email, telefone, site, logo_url, categoria, descricao, split_percentual)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            trim($in['nome']),
            trim($in['nome_fantasia'] ?? ''),
            trim($in['cnpj'] ?? ''),
            trim($in['email'] ?? ''),
            trim($in['telefone'] ?? ''),
            trim($in['site'] ?? ''),
            trim($in['logo_url'] ?? ''),
            trim($in['categoria'] ?? ''),
            trim($in['descricao'] ?? ''),
            floatval($in['split_percentual'] ?? 0),
        ]);
        ok(['id' => getDB()->lastInsertId()]);
        break;

    case 'parceiro_editar':
        $in = input();
        if (empty($in['id'])) err(400, 'ID obrigatório.');
        getDB()->prepare("
            UPDATE conecta_parceiros SET nome=?, nome_fantasia=?, cnpj=?, email=?, telefone=?, site=?, logo_url=?, categoria=?, descricao=?, split_percentual=?
            WHERE id=?
        ")->execute([
            trim($in['nome'] ?? ''),
            trim($in['nome_fantasia'] ?? ''),
            trim($in['cnpj'] ?? ''),
            trim($in['email'] ?? ''),
            trim($in['telefone'] ?? ''),
            trim($in['site'] ?? ''),
            trim($in['logo_url'] ?? ''),
            trim($in['categoria'] ?? ''),
            trim($in['descricao'] ?? ''),
            floatval($in['split_percentual'] ?? 0),
            (int)$in['id'],
        ]);
        ok(true);
        break;

    case 'parceiro_toggle':
        $in = input();
        if (empty($in['id'])) err(400, 'ID obrigatório.');
        getDB()->prepare("UPDATE conecta_parceiros SET ativo = NOT ativo WHERE id=?")->execute([(int)$in['id']]);
        ok(true);
        break;

    case 'parceiro_excluir':
        $in = input();
        if (empty($in['id'])) err(400, 'ID obrigatório.');
        // Check if any products use this partner
        $st = getDB()->prepare("SELECT COUNT(*) FROM conecta_produtos WHERE parceiro_id=?");
        $st->execute([(int)$in['id']]);
        if ((int)$st->fetchColumn() > 0) {
            err(400, 'Parceiro possui produtos vinculados. Desvincule-os primeiro.');
        }
        getDB()->prepare("DELETE FROM conecta_parceiros WHERE id=?")->execute([(int)$in['id']]);
        ok(true);
        break;

    // ── Vincular produto a parceiro ──
    case 'produto_parceiro':
        $in = input();
        if (empty($in['produto_id'])) err(400, 'produto_id obrigatório.');
        $parcId = !empty($in['parceiro_id']) ? (int)$in['parceiro_id'] : null;
        getDB()->prepare("UPDATE conecta_produtos SET parceiro_id=? WHERE id=?")->execute([$parcId, (int)$in['produto_id']]);
        ok(true);
        break;

    case 'listar_admins':
        $admins = getDB()->query(
            "SELECT id, cpf_cnpj, tipo, ativo, created_at FROM conecta_users WHERE is_admin=1 ORDER BY created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        ok($admins);
        break;

    case 'promover_admin':
        $in = input();
        if (empty($in['cpf_cnpj'])) err(400,'CPF/CNPJ obrigatorio.');
        $doc = preg_replace('/\D/', '', $in['cpf_cnpj']);
        $db = getDB();
        $st = $db->prepare('SELECT id FROM conecta_users WHERE cpf_cnpj=?');
        $st->execute([$doc]);
        if (!$st->fetch()) err(404,'Usuario nao encontrado.');
        $db->prepare('UPDATE conecta_users SET is_admin=1 WHERE cpf_cnpj=?')->execute([$doc]);
        ok(['promovido' => true]);
        break;

    case 'revogar_admin':
        $in = input();
        if (empty($in['cpf_cnpj'])) err(400,'CPF/CNPJ obrigatorio.');
        $doc = preg_replace('/\D/', '', $in['cpf_cnpj']);
        getDB()->prepare('UPDATE conecta_users SET is_admin=0 WHERE cpf_cnpj=?')->execute([$doc]);
        ok(['revogado' => true]);
        break;

        default:
        err(400,'Ação inválida.');
}
