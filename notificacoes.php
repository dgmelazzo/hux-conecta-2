<?php
require_once __DIR__ . '/auth-helper.php';
require_once __DIR__ . '/config.php';
?>
/**
 * ACIC CONECTA 2.0 — Notificações
 * ====================================
 * GET  ?action=nao_lidas   → contagem de não lidas (polling)
 * GET  ?action=listar      → lista completa do usuário
 * POST ?action=marcar_lida → marca uma notificação como lida
 * POST ?action=marcar_todas→ marca todas como lidas
 * POST ?action=enviar      → envia notificação (admin only)
 */
require_once 'config.php';
require_once 'email.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── DB ──────────────────────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}

function ok($d)     { echo json_encode(['success'=>true,'data'=>$d], JSON_UNESCAPED_UNICODE); exit; }
function err($c,$m) { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m], JSON_UNESCAPED_UNICODE); exit; }

// Lê o body uma única vez (php://input só pode ser lido uma vez)
$_rawInput = null;
function input() {
    global $_rawInput;
    if ($_rawInput === null) {
        $_rawInput = json_decode(file_get_contents('php://input'), true) ?: [];
    }
    return $_rawInput;
}

// ── SETUP TABELAS ────────────────────────────────────────────
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS conecta_notificacoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo        ENUM('info','aviso','alerta','sucesso') DEFAULT 'info',
    titulo      VARCHAR(200) NOT NULL,
    mensagem    TEXT NOT NULL,
    link        VARCHAR(500) DEFAULT NULL,
    enviou_email TINYINT(1)  DEFAULT 0,
    enviado_por INT DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Garante colunas que podem não existir em instalações antigas
try { $db->exec("ALTER TABLE conecta_notificacoes ADD COLUMN enviou_email TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE conecta_notificacoes ADD COLUMN enviado_por INT DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE conecta_notificacoes ADD COLUMN link VARCHAR(500) DEFAULT NULL"); } catch(Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS conecta_notif_destinatarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    notificacao_id  INT NOT NULL,
    user_id         INT NOT NULL,
    lida            TINYINT(1) DEFAULT 0,
    lida_at         DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notif_user (notificacao_id, user_id),
    INDEX idx_user_lida (user_id, lida),
    INDEX idx_notif (notificacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── AUTH ─────────────────────────────────────────────────────
function getAuthUser() {
    $db  = getDB();
    $user = requireCrmAuth();
    if (!$user) err(401, 'Sessão inválida ou expirada.');
    return $user;
}

function isAdmin($user) {
    return preg_replace('/\D/', '', $user['cpf_cnpj']) === preg_replace('/\D/', '', ADMIN_DOC);
}

// ── ROTAS ────────────────────────────────────────────────────
$_inputData = input(); // força leitura do body aqui
$action = $_GET['action'] ?? ($_inputData['action'] ?? '');

switch ($action) {

    // ── Contagem de não lidas (polling a cada 60s) ──
    case 'nao_lidas':
        $user = getAuthUser();
        $st   = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM conecta_notif_destinatarios
             WHERE user_id = ? AND lida = 0'
        );
        $st->execute([$user['id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        ok(['total' => (int)$row['total']]);
        break;

    // ── Lista todas as notificações do usuário ──
    case 'listar':
        $user = getAuthUser();
        $st   = $db->prepare(
            'SELECT n.id, n.tipo, n.titulo, n.mensagem, n.link, n.created_at,
                    d.lida, d.lida_at
             FROM conecta_notificacoes n
             JOIN conecta_notif_destinatarios d ON d.notificacao_id = n.id
             WHERE d.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT 50'
        );
        $st->execute([$user['id']]);
        $notifs = $st->fetchAll(PDO::FETCH_ASSOC);

        // Conta não lidas
        $naoLidas = array_filter($notifs, fn($n) => !$n['lida']);

        ok([
            'notificacoes' => $notifs,
            'nao_lidas'    => count($naoLidas),
        ]);
        break;

    // ── Marca uma notificação como lida ──
    case 'marcar_lida':
        $user = getAuthUser();
        $in   = input();
        if (empty($in['id'])) err(400, 'ID obrigatório.');
        $db->prepare(
            'UPDATE conecta_notif_destinatarios
             SET lida = 1, lida_at = NOW()
             WHERE notificacao_id = ? AND user_id = ?'
        )->execute([(int)$in['id'], $user['id']]);
        ok(true);
        break;

    // ── Marca todas como lidas ──
    case 'marcar_todas':
        $user = getAuthUser();
        $db->prepare(
            'UPDATE conecta_notif_destinatarios
             SET lida = 1, lida_at = NOW()
             WHERE user_id = ? AND lida = 0'
        )->execute([$user['id']]);
        ok(true);
        break;

    // ── Enviar notificação (admin only) ──
    case 'enviar':
        $user = getAuthUser();
        if (!isAdmin($user)) err(403, 'Acesso restrito ao administrador.');

        $in = input();
        if (empty($in['titulo']) || empty($in['mensagem'])) err(400, 'Título e mensagem obrigatórios.');

        $tipo      = in_array($in['tipo'] ?? '', ['info','aviso','alerta','sucesso']) ? $in['tipo'] : 'info';
        $titulo    = trim($in['titulo']);
        $mensagem  = trim($in['mensagem']);
        $link      = !empty($in['link']) ? trim($in['link']) : null;
        $userId    = !empty($in['user_id']) ? (int)$in['user_id'] : null;
        $envEmail  = !empty($in['enviar_email']);

        // Cria a notificação
        $db->prepare(
            'INSERT INTO conecta_notificacoes (tipo, titulo, mensagem, link, enviou_email, enviado_por)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$tipo, $titulo, $mensagem, $link, $envEmail ? 1 : 0, $user['id']]);
        $notifId = $db->lastInsertId();

        // Busca destinatários — suporta user_id, user_ids[] ou broadcast
        $userIds = !empty($in['user_ids']) && is_array($in['user_ids'])
            ? array_map('intval', $in['user_ids'])
            : ($userId ? [$userId] : []);

        if (count($userIds) > 0) {
            $placeholders   = implode(',', array_fill(0, count($userIds), '?'));
            $destStmt       = $db->prepare("SELECT id, cpf_cnpj FROM conecta_users WHERE id IN ($placeholders) AND ativo=1");
            $destStmt->execute($userIds);
            $destinatarios  = $destStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $destinatarios = $db->query('SELECT id, cpf_cnpj FROM conecta_users WHERE ativo = 1')->fetchAll(PDO::FETCH_ASSOC);
        }

        // Insere registros de destinatário
        $insStmt = $db->prepare(
            'INSERT IGNORE INTO conecta_notif_destinatarios (notificacao_id, user_id) VALUES (?, ?)'
        );
        $enviadas = 0;
        foreach ($destinatarios as $dest) {
            $insStmt->execute([$notifId, $dest['id']]);
            $enviadas++;
        }

        // Envio de e-mail
        $emailsEnviados = 0;
        if ($envEmail && $enviadas > 0) {
            // Busca e-mails dos destinatários via HiGestor (simplificado — usa cpf_cnpj como referência)
            foreach ($destinatarios as $dest) {
                $email = buscarEmailAssociado($dest['cpf_cnpj'], $dest['id']);
                if ($email) {
                    $ok = enviarEmailNotificacao($email, $titulo, $mensagem, $tipo, $link);
                    if ($ok) $emailsEnviados++;
                }
            }
        }

        ok([
            'notificacao_id' => (int)$notifId,
            'enviadas'       => $enviadas,
            'emails_enviados'=> $emailsEnviados,
        ]);
        break;

    // ── Histórico de comunicados enviados (admin) ──
    case 'historico':
        $user = getAuthUser();
        if (!isAdmin($user)) err(403, 'Acesso restrito ao administrador.');
        $rows = $db->query(
            "SELECT n.id, n.tipo, n.titulo, n.mensagem, n.link, n.created_at,
                    COUNT(d.id) AS total_dest,
                    SUM(d.lida) AS total_lidas,
                    n.enviou_email
             FROM conecta_notificacoes n
             LEFT JOIN conecta_notif_destinatarios d ON d.notificacao_id = n.id
             GROUP BY n.id
             ORDER BY n.created_at DESC
             LIMIT 30"
        )->fetchAll(PDO::FETCH_ASSOC);
        ok($rows);
        break;

    case 'excluir_comunicado':
        $user = getAuthUser();
        if (!isAdmin($user)) err(403, 'Acesso restrito ao administrador.');
        $in  = input();
        $id  = (int)($in['id'] ?? 0);
        if (!$id) err(400, 'ID obrigatório.');
        $db->prepare('DELETE FROM conecta_notif_destinatarios WHERE notificacao_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM conecta_notificacoes WHERE id = ?')->execute([$id]);
        ok(['deleted' => true]);
        break;

    case 'destinatarios_comunicado':
        $user = getAuthUser();
        if (!isAdmin($user)) err(403, 'Acesso restrito ao administrador.');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err(400, 'ID obrigatório.');
        $rows = $db->prepare(
            "SELECT u.cpf_cnpj, u.nome,
                CASE WHEN LENGTH(u.cpf_cnpj)=11
                    THEN CONCAT(SUBSTR(u.cpf_cnpj,1,3),'.',SUBSTR(u.cpf_cnpj,4,3),'.',SUBSTR(u.cpf_cnpj,7,3),'-',SUBSTR(u.cpf_cnpj,10,2))
                    ELSE CONCAT(SUBSTR(u.cpf_cnpj,1,2),'.',SUBSTR(u.cpf_cnpj,3,3),'.',SUBSTR(u.cpf_cnpj,6,3),'/',SUBSTR(u.cpf_cnpj,9,4),'-',SUBSTR(u.cpf_cnpj,13,2))
                END AS doc_fmt,
                d.lida, d.lida_at
             FROM conecta_notif_destinatarios d
             JOIN conecta_users u ON u.id = d.user_id
             WHERE d.notificacao_id = ?
             ORDER BY u.nome ASC"
        );
        $rows->execute([$id]);
        ok($rows->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        err(400, 'Ação inválida.');
}

// ── Busca e-mail do associado ────────────────────────────────
function buscarEmailAssociado($cpfCnpj, $userId) {
    // Tenta buscar e-mail da sessão/HiGestor
    $db   = getDB();
    $user = $db->prepare('SELECT tipo, higestor_id FROM conecta_users WHERE id = ?');
    $user->execute([$userId]);
    $u = $user->fetch(PDO::FETCH_ASSOC);
    if (!$u || !$u['higestor_id']) return null;

    $ep  = $u['tipo'] === 'empresa'
        ? '/empresas/' . $u['higestor_id']
        : '/contribuintes/' . $u['higestor_id'];

    $ch = curl_init(HIGESTOR_URL . $ep);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Auth-Token: ' . HIGESTOR_TOKEN, 'Content-Type: application/json'],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$res) return null;
    $data  = json_decode($res, true);
    $attrs = $data['data']['attributes'] ?? $data['data'][0]['attributes'] ?? [];
    return $attrs['email'] ?? null;
}
