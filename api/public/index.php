<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Tenant');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Autoload
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(503);
    echo json_encode(['error' => 'vendor/ nao encontrado. Rode composer install.']);
    exit;
}
require $autoload;

// .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($envFile));
    $dotenv->load();
}

// DB
try {
    $pdo = new PDO(
        "mysql:host=" . ($_ENV['DB_HOST'] ?? '127.0.0.1') . 
        ";dbname=" . ($_ENV['DB_NAME'] ?? 'conecta_crm') . ";charset=utf8mb4",
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $GLOBALS['pdo'] = $pdo;
} catch (\Exception $e) {
    http_response_code(503);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ── Rotas ──────────────────────────────────────────────
// Health check
if ($uri === '/' || $uri === '/api') {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
    $row  = $stmt->fetch();
    echo json_encode([
        'status'  => 'ok',
        'app'     => 'Conecta CRM API',
        'version' => '1.0.0',
        'php'     => PHP_VERSION,
        'db'      => 'connected',
        'tenants' => (int) $row['total'],
    ]);
    exit;
}

// Tabelas
if ($uri === '/api/status') {
    $stmt   = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['tables' => $tables, 'total' => count($tables)]);
    exit;
}

// Planos públicos
if (preg_match('#^/api/publica/plano/([^/]+)/([^/]+)$#', $uri, $m)) {
    $stmt = $pdo->prepare(
        "SELECT p.*, t.nome as tenant_nome FROM planos p
         JOIN tenants t ON t.id = p.tenant_id
         WHERE t.slug = ? AND p.slug_link = ? AND p.ativo = 1 LIMIT 1"
    );
    $stmt->execute([$m[1], $m[2]]);
    $plano = $stmt->fetch();
    if (!$plano) { http_response_code(404); echo json_encode(['error' => 'Plano não encontrado']); exit; }
    echo json_encode($plano);
    exit;
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Rota não encontrada', 'path' => $uri, 'method' => $method]);
