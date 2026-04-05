<?php
/**
 * Régua de Cobrança Automática — Conecta CRM
 * VPS: /var/www/hux-crm-association/scripts/regua_cobranca.php
 * Cron: 0 8 * * * php /var/www/hux-crm-association/scripts/regua_cobranca.php >> /var/log/regua_cobranca.log 2>&1
 */

declare(strict_types=1);

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

date_default_timezone_set('America/Sao_Paulo');

// ── DB ───────────────────────────────────────────────────────────────────────
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $conn;
}

// ── LOG ──────────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ── SUBSTITUIÇÃO DE VARIÁVEIS ────────────────────────────────────────────────
function substituir_variaveis(string $template, array $vars): string {
    foreach ($vars as $k => $v) {
        $template = str_replace('{{' . $k . '}}', (string)($v ?? ''), $template);
    }
    return $template;
}

// ── ENVIO DE EMAIL ────────────────────────────────────────────────────────────
function enviar_email(string $para, string $assunto, string $corpo, int $tenantId): bool {
    // Busca configurações de email do tenant
    $smtp = pdo()->prepare("SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = 'email_remetente'");
    $smtp->execute([$tenantId]);
    $remetente = $smtp->fetchColumn() ?: 'noreply@acicdf.org.br';

    $nomeStmt = pdo()->prepare("SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = 'email_remetente_nome'");
    $nomeStmt->execute([$tenantId]);
    $nomeRemetente = $nomeStmt->fetchColumn() ?: 'ACIC-DF';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$nomeRemetente} <{$remetente}>\r\n";
    $headers .= "Reply-To: {$remetente}\r\n";
    $headers .= "X-Mailer: ConectaCRM/1.0\r\n";

    $resultado = mail($para, $assunto, $corpo, $headers);
    return $resultado;
}

// ── ENVIO WHATSAPP (Z-API) ────────────────────────────────────────────────────
function enviar_whatsapp(string $telefone, string $mensagem, int $tenantId): bool {
    $stmtI = pdo()->prepare("SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = 'zapi_instance'");
    $stmtI->execute([$tenantId]);
    $instance = $stmtI->fetchColumn();

    $stmtT = pdo()->prepare("SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = 'zapi_token'");
    $stmtT->execute([$tenantId]);
    $token = $stmtT->fetchColumn();

    $stmtCT = pdo()->prepare("SELECT valor FROM tenant_configs WHERE tenant_id = ? AND chave = 'zapi_client_token'");
    $stmtCT->execute([$tenantId]);
    $clientToken = $stmtCT->fetchColumn() ?: ($_ENV['ZAPI_CLIENT_TOKEN'] ?? '');

    // Fallback para .env se tenant_configs estiver vazio
    $instance = $instance ?: ($_ENV['ZAPI_INSTANCE'] ?? '');
    $token    = $token    ?: ($_ENV['ZAPI_TOKEN']    ?? '');

    if (!$instance || !$token) {
        log_msg("[ZAPI] credenciais ausentes (instance ou token vazio) — nao enviou");
        return false;
    }

    // Formata telefone
    $tel = preg_replace('/\D/', '', $telefone);
    if (strlen($tel) === 10 || strlen($tel) === 11) $tel = '55' . $tel;

    $url = "https://api.z-api.io/instances/{$instance}/token/{$token}/send-text";
    $headers = ['Content-Type: application/json'];
    if ($clientToken) $headers[] = 'Client-Token: ' . $clientToken;

    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['phone' => $tel, 'message' => $mensagem], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        log_msg("[ZAPI] falha http=$code tel=$tel resp=" . substr($res ?: '', 0, 200));
        return false;
    }
    return true;
}

// ── REGISTRA ENVIO ────────────────────────────────────────────────────────────
function registrar_envio(array $dados): void {
    pdo()->prepare(
        'INSERT INTO comunicado_envios
         (tenant_id, template_id, associado_id, cobranca_id, canal, destinatario, assunto, corpo, status, erro, gatilho, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $dados['tenant_id'],
        $dados['template_id']  ?? null,
        $dados['associado_id'] ?? null,
        $dados['cobranca_id']  ?? null,
        $dados['canal'],
        $dados['destinatario'],
        $dados['assunto']      ?? null,
        $dados['corpo']        ?? null,
        $dados['status'],
        $dados['erro']         ?? null,
        $dados['gatilho']      ?? null,
    ]);
}

// ── PROCESSAR GATILHO ─────────────────────────────────────────────────────────
function processar_gatilho(string $gatilho, int $diasOffset, int $tenantId): void {
    log_msg("Processando gatilho: {$gatilho}");

    // Busca configuração da régua
    $stmtR = pdo()->prepare(
        'SELECT r.*, t.assunto_email, t.corpo_email, t.corpo_whatsapp, t.canal
         FROM regua_config r
         LEFT JOIN comunicado_templates t ON t.id = r.template_id
         WHERE r.tenant_id = ? AND r.gatilho = ? AND r.ativo = 1'
    );
    $stmtR->execute([$tenantId, $gatilho]);
    $regua = $stmtR->fetch();

    if (!$regua) {
        log_msg("  Gatilho {$gatilho} inativo ou sem template. Pulando.");
        return;
    }

    // Determina a data alvo
    $dataAlvo = date('Y-m-d', strtotime("{$diasOffset} days"));

    // Para D+15: marca associado como inadimplente e suspende
    $suspender = ($gatilho === 'regua_d+15');

    // Busca cobranças pendentes/vencidas na data alvo
    $statusBusca = $diasOffset <= 0 ? 'pendente' : 'expirado';
    if ($diasOffset < 0) {
        // D-7: cobrança pendente com vencimento daqui 7 dias
        $sql = "SELECT c.*, a.nome_fantasia, a.razao_social, a.email, a.whatsapp, a.telefone
                FROM cobrancas c
                JOIN associados a ON a.id = c.associado_id
                WHERE c.tenant_id = ? AND c.status = 'pendente'
                AND c.data_vencimento = ?
                AND a.status = 'ativo'";
    } elseif ($diasOffset === 0) {
        // D0: vence hoje
        $sql = "SELECT c.*, a.nome_fantasia, a.razao_social, a.email, a.whatsapp, a.telefone
                FROM cobrancas c
                JOIN associados a ON a.id = c.associado_id
                WHERE c.tenant_id = ? AND c.status = 'pendente'
                AND c.data_vencimento = ?";
    } else {
        // D+N: venceu N dias atrás
        $sql = "SELECT c.*, a.nome_fantasia, a.razao_social, a.email, a.whatsapp, a.telefone
                FROM cobrancas c
                JOIN associados a ON a.id = c.associado_id
                WHERE c.tenant_id = ? AND c.status IN ('pendente','expirado')
                AND c.data_vencimento = ?
                AND a.status != 'cancelado'";
    }

    $stmtC = pdo()->prepare($sql);
    $stmtC->execute([$tenantId, $dataAlvo]);
    $cobrancas = $stmtC->fetchAll();

    log_msg("  Data alvo: {$dataAlvo} | Cobranças encontradas: " . count($cobrancas));

    foreach ($cobrancas as $cob) {
        $nome        = $cob['nome_fantasia'] ?: ($cob['razao_social'] ?: 'Associado');
        $linkBoleto  = $cob['gateway_url'] ?: '';
        $pixCopiaCola = '';

        // Pix copia-e-cola: coluna nao existe atualmente, link Asaas cumpre o papel

        $vars = [
            'nome_fantasia'   => $nome,
            'valor'           => 'R$ ' . number_format((float)$cob['valor'], 2, ',', '.'),
            'data_vencimento' => date('d/m/Y', strtotime($cob['data_vencimento'])),
            'link_boleto'     => $linkBoleto,
            'pix_copia_cola'  => $pixCopiaCola,
        ];

        $canal = $regua['canal'] ?? 'ambos';
        $enviouAlgo = false;

        // EMAIL
        if (in_array($canal, ['email', 'ambos']) && !empty($cob['email'])) {
            $assunto = substituir_variaveis($regua['assunto_email'] ?? 'Aviso ACIC-DF', $vars);
            $corpo   = substituir_variaveis($regua['corpo_email']   ?? '', $vars);

            $ok = enviar_email($cob['email'], $assunto, $corpo, $tenantId);
            registrar_envio([
                'tenant_id'   => $tenantId,
                'template_id' => $regua['template_id'],
                'associado_id'=> $cob['associado_id'],
                'cobranca_id' => $cob['id'],
                'canal'       => 'email',
                'destinatario'=> $cob['email'],
                'assunto'     => $assunto,
                'corpo'       => $corpo,
                'status'      => $ok ? 'enviado' : 'falhou',
                'gatilho'     => $gatilho,
            ]);
            log_msg("  Email para {$cob['email']}: " . ($ok ? 'OK' : 'FALHOU'));
            $enviouAlgo = true;
        }

        // WHATSAPP
        $tel = $cob['whatsapp'] ?: $cob['telefone'];
        if (in_array($canal, ['whatsapp', 'ambos']) && !empty($tel)) {
            $msg = substituir_variaveis($regua['corpo_whatsapp'] ?? '', $vars);
            $ok  = enviar_whatsapp($tel, $msg, $tenantId);
            registrar_envio([
                'tenant_id'   => $tenantId,
                'template_id' => $regua['template_id'],
                'associado_id'=> $cob['associado_id'],
                'cobranca_id' => $cob['id'],
                'canal'       => 'whatsapp',
                'destinatario'=> $tel,
                'corpo'       => $msg,
                'status'      => $ok ? 'enviado' : 'falhou',
                'erro'        => $ok ? null : 'Z-API não configurada ou erro no envio',
                'gatilho'     => $gatilho,
            ]);
            log_msg("  WhatsApp para {$tel}: " . ($ok ? 'OK' : 'FALHOU'));
            $enviouAlgo = true;
        }

        // D+15: suspende o associado
        if ($suspender) {
            pdo()->prepare(
                'UPDATE associados SET status = "inadimplente", atualizado_em = NOW() WHERE id = ?'
            )->execute([$cob['associado_id']]);
            // Atualiza status da cobrança
            pdo()->prepare(
                'UPDATE cobrancas SET status = "expirado", atualizado_em = NOW() WHERE id = ? AND status != "pago"'
            )->execute([$cob['id']]);
            log_msg("  Associado ID {$cob['associado_id']} marcado como inadimplente.");
        }
    }
}

// ── MAIN ─────────────────────────────────────────────────────────────────────
log_msg("=== Régua de Cobrança iniciada ===");

// Busca todos os tenants ativos
$tenants = pdo()->query("SELECT DISTINCT tenant_id FROM regua_config WHERE ativo = 1")->fetchAll();

foreach ($tenants as $t) {
    $tid = (int)$t['tenant_id'];
    log_msg("Tenant ID: {$tid}");

    processar_gatilho('regua_d-7',  -7,  $tid);
    processar_gatilho('regua_d0',    0,  $tid);
    processar_gatilho('regua_d+3',   3,  $tid); // Nota: busca cobrança de 3 dias atrás
    processar_gatilho('regua_d+7',   7,  $tid);
    processar_gatilho('regua_d+15', 15,  $tid);
}

log_msg("=== Régua de Cobrança concluída ===");
