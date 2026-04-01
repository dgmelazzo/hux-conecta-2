<?php
/**
 * sync_higestor.php — Sincroniza associados do HiGestor → CRM
 * Cron: 0 6 * * * php /var/www/hux-crm-association/scripts/sync_higestor.php >> /var/log/sync_higestor.log 2>&1
 */
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sync HiGestor → CRM\n";

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

$higestorApiKey = $_ENV['HIGESTOR_API_KEY'] ?? '';
$higestorUrl    = $_ENV['HIGESTOR_API_URL'] ?? 'https://api.higestor.com.br';
$crmSecret      = $_ENV['CRM_SECRET'] ?? '';
$crmApiUrl      = 'https://api.acicdf.org.br';

if (!$higestorApiKey) {
    echo "[WARN] HIGESTOR_API_KEY not configured, skipping sync.\n";
    exit(0);
}

// Database connection
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_NAME'] ?? 'conecta_crm'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo "[ERROR] DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$tenantId = (int)($_ENV['DEFAULT_TENANT_ID'] ?? 1);

// Fetch associados from HiGestor
function higestorGet(string $path): ?array {
    global $higestorApiKey, $higestorUrl;
    $ch = curl_init($higestorUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $higestorApiKey,
            'Content-Type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code >= 400) {
        echo "[ERROR] HiGestor API error: code=$code err=$err\n";
        return null;
    }
    return json_decode($resp ?: '{}', true);
}

// Fetch all associados (paginated)
$page = 1;
$totalSynced = 0;
$totalCreated = 0;
$totalUpdated = 0;
$totalErrors = 0;

do {
    $data = higestorGet("/associados?page=$page&per_page=100&status=ativo");
    if (!$data || empty($data['data'])) break;

    $associados = $data['data'];
    $hasMore    = ($data['meta']['current_page'] ?? $page) < ($data['meta']['last_page'] ?? $page);

    foreach ($associados as $hg) {
        $doc = preg_replace('/\D/', '', $hg['cnpj'] ?? $hg['cpf'] ?? '');
        if (!$doc) {
            $totalErrors++;
            continue;
        }

        $field = strlen($doc) === 14 ? 'cnpj' : 'cpf';
        $nome  = $hg['razao_social'] ?? $hg['nome_fantasia'] ?? $hg['nome'] ?? 'Associado';
        $email = $hg['email'] ?? null;

        // Map HiGestor status to CRM status
        $statusMap = [
            'ativo'        => 'ativo',
            'inadimplente' => 'inadimplente',
            'suspenso'     => 'suspenso',
            'cancelado'    => 'cancelado',
            'inativo'      => 'cancelado',
        ];
        $status = $statusMap[strtolower($hg['status'] ?? 'ativo')] ?? 'ativo';

        try {
            $stmt = $pdo->prepare("SELECT id, higestor_id FROM associados WHERE $field = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$doc, $tenantId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update
                $pdo->prepare(
                    'UPDATE associados SET nome_fantasia = COALESCE(?, nome_fantasia),
                     email = COALESCE(?, email), status = ?, higestor_id = ?,
                     atualizado_em = NOW() WHERE id = ?'
                )->execute([$nome, $email, $status, $hg['id'] ?? null, $existing['id']]);
                $totalUpdated++;
            } else {
                // Create
                $pdo->prepare(
                    "INSERT INTO associados (tenant_id, tipo_pessoa, nome_fantasia, $field, email, status, higestor_id, criado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                )->execute([
                    $tenantId,
                    strlen($doc) === 14 ? 'pj' : 'pf',
                    $nome,
                    $doc,
                    $email,
                    $status,
                    $hg['id'] ?? null,
                ]);
                $totalCreated++;
            }
            $totalSynced++;
        } catch (PDOException $e) {
            echo "[ERROR] doc=$doc: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
    }

    $page++;
} while ($hasMore);

echo "[" . date('Y-m-d H:i:s') . "] Sync complete: synced=$totalSynced created=$totalCreated updated=$totalUpdated errors=$totalErrors\n";
