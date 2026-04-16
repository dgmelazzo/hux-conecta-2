<?php
/**
 * ACIC CONECTA 2.0 — API Pública de Produtos e Parceiros
 * Protegida por X-Bridge-Secret para acesso do CRM
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Bridge-Secret');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/config.php';

function ok(mixed $d): never { echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE); exit; }
function err(int $c, string $m): never { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; }

// Auth: X-Bridge-Secret
$secret = $_SERVER['HTTP_X_BRIDGE_SECRET'] ?? '';
if (!$secret || $secret !== CRM_BRIDGE_SECRET) {
    err(401, 'Acesso não autorizado');
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$action = $_GET['action'] ?? '';

// ── GET ?action=produtos ─────────────────────────────────────
if ($action === 'produtos') {
    $stmt = $pdo->query("
        SELECT p.id, p.nome, p.descricao_curta, p.descricao, p.imagem, p.categoria_id,
               p.tipo, p.status, p.marca, p.link_venda_url, p.destaque, p.parceiro_id,
               c.nome AS categoria_nome,
               par.nome AS parceiro_nome, par.logo_url AS parceiro_logo,
               par.nome_fantasia AS parceiro_fantasia, par.split_percentual AS parceiro_split
        FROM conecta_produtos p
        LEFT JOIN conecta_categorias c ON c.id = p.categoria_id
        LEFT JOIN conecta_parceiros par ON par.id = p.parceiro_id
        WHERE p.status = 'ativo'
        ORDER BY p.destaque DESC, p.nome ASC
    ");
    ok($stmt->fetchAll());
}

// ── GET ?action=produto&id=X ─────────────────────────────────
if ($action === 'produto') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) err(400, 'ID obrigatório');

    $stmt = $pdo->prepare("
        SELECT p.*, c.nome AS categoria_nome,
               par.nome AS parceiro_nome, par.logo_url AS parceiro_logo,
               par.nome_fantasia AS parceiro_fantasia, par.site AS parceiro_site,
               par.split_percentual AS parceiro_split
        FROM conecta_produtos p
        LEFT JOIN conecta_categorias c ON c.id = p.categoria_id
        LEFT JOIN conecta_parceiros par ON par.id = p.parceiro_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) err(404, 'Produto não encontrado');

    // Subprodutos
    $subs = $pdo->prepare("SELECT * FROM conecta_subprodutos WHERE produto_id = ? ORDER BY id");
    $subs->execute([$id]);
    $row['subprodutos'] = $subs->fetchAll();

    // Imagens
    $imgs = $pdo->prepare("SELECT * FROM conecta_produto_imagens WHERE produto_id = ? ORDER BY ordem, id");
    $imgs->execute([$id]);
    $row['imagens'] = $imgs->fetchAll();

    ok($row);
}

// ── GET ?action=parceiros ────────────────────────────────────
if ($action === 'parceiros') {
    $stmt = $pdo->query("
        SELECT id, nome, nome_fantasia, cnpj, logo_url, categoria, site, email, split_percentual
        FROM conecta_parceiros
        WHERE ativo = 1
        ORDER BY nome ASC
    ");
    ok($stmt->fetchAll());
}

err(400, 'Ação inválida: ' . $action);
