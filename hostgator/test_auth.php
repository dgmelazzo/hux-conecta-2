<?php
/**
 * test_auth.php - Diagnostico do auth.php no HostGator
 * Upload em /public_html/conecta/test_auth.php e acessar via browser
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json; charset=utf-8');

$result = [
    'php_version'          => PHP_VERSION,
    'php_version_id'       => PHP_VERSION_ID,
    'has_curl'             => extension_loaded('curl'),
    'has_pdo_mysql'        => extension_loaded('pdo_mysql'),
    'has_json'             => extension_loaded('json'),
    'config_exists'        => file_exists(__DIR__ . '/config.php'),
    'config_readable'      => is_readable(__DIR__ . '/config.php'),
    'auth_exists'          => file_exists(__DIR__ . '/auth.php'),
    'auth_readable'        => is_readable(__DIR__ . '/auth.php'),
];

// Tenta incluir config.php
try {
    require_once __DIR__ . '/config.php';
    $result['config_loaded'] = true;
    $result['CRM_API_URL']       = defined('CRM_API_URL') ? CRM_API_URL : 'UNDEFINED';
    $result['CRM_BRIDGE_SECRET'] = defined('CRM_BRIDGE_SECRET') ? (strlen(CRM_BRIDGE_SECRET) > 0 ? 'SET (' . strlen(CRM_BRIDGE_SECRET) . ' chars)' : 'EMPTY') : 'UNDEFINED';
    $result['DB_HOST']           = defined('DB_HOST') ? DB_HOST : 'UNDEFINED';
    $result['DB_NAME']           = defined('DB_NAME') ? DB_NAME : 'UNDEFINED';
    $result['DB_USER']           = defined('DB_USER') ? DB_USER : 'UNDEFINED';
    $result['DB_PASS_set']       = defined('DB_PASS') ? (strlen(DB_PASS) > 0 ? 'YES' : 'EMPTY') : 'UNDEFINED';
    $result['ADMIN_DOC']         = defined('ADMIN_DOC') ? ADMIN_DOC : 'UNDEFINED';
} catch (\Throwable $e) {
    $result['config_error'] = $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
}

// Tenta conectar no DB
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $result['db_connection'] = 'OK';
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        $result['db_version'] = $ver;

        // Verifica tabela conecta_users
        $cols = $pdo->query("SHOW COLUMNS FROM conecta_users")->fetchAll(PDO::FETCH_COLUMN);
        $result['conecta_users_cols'] = $cols;
        $result['has_crm_associado_id'] = in_array('crm_associado_id', $cols, true);
        $result['has_crm_dados'] = in_array('crm_dados', $cols, true);

        // Verifica conecta_sessions
        $stmtS = $pdo->query("SHOW TABLES LIKE 'conecta_sessions'");
        $result['conecta_sessions_exists'] = (bool)$stmtS->fetch();
    } catch (\Throwable $e) {
        $result['db_error'] = $e->getMessage();
    }
}

// Tenta alcancar o CRM
if (defined('CRM_API_URL') && function_exists('curl_init')) {
    $ch = curl_init(CRM_API_URL . '/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errCurl = curl_error($ch);
    curl_close($ch);
    $result['crm_health_http'] = $code;
    $result['crm_health_body'] = $body ? substr($body, 0, 200) : null;
    if ($errCurl) $result['crm_curl_error'] = $errCurl;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
