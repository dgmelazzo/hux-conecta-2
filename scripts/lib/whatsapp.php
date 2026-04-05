<?php
/**
 * Helper de envio de WhatsApp via Z-API.
 * Requer .env com: ZAPI_INSTANCE, ZAPI_TOKEN, ZAPI_CLIENT_TOKEN
 */
declare(strict_types=1);

function zapi_configured(): bool {
    return !empty($_ENV['ZAPI_INSTANCE']) && !empty($_ENV['ZAPI_TOKEN']);
}

function sendWhatsApp(string $phone, string $message): array {
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) < 10) return ['ok'=>false,'error'=>'Telefone invalido'];
    if (strlen($phone) === 10 || strlen($phone) === 11) $phone = '55' . $phone;

    if (!zapi_configured()) {
        error_log('[ZAPI] credenciais nao configuradas — to='.$phone.' msg='.substr($message,0,60));
        return ['ok'=>false,'error'=>'Z-API nao configurado','simulated'=>true];
    }

    $instance = $_ENV['ZAPI_INSTANCE'];
    $token    = $_ENV['ZAPI_TOKEN'];
    $client   = $_ENV['ZAPI_CLIENT_TOKEN'] ?? '';

    $url = "https://api.z-api.io/instances/{$instance}/token/{$token}/send-text";
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($client) $headers[] = 'Client-Token: ' . $client;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode(['phone'=>$phone,'message'=>$message], JSON_UNESCAPED_UNICODE),
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        return ['ok'=>true, 'http'=>$code, 'response'=>$res];
    }
    error_log("[ZAPI] falha http=$code to=$phone resp=" . substr($res ?: '', 0, 200));
    return ['ok'=>false, 'http'=>$code, 'error'=>'Z-API retornou ' . $code, 'response'=>$res];
}

function loadTemplate(string $name, array $vars = []): string {
    $path = __DIR__ . '/../templates_whatsapp/' . $name . '.txt';
    if (!file_exists($path)) return '';
    $content = file_get_contents($path);
    foreach ($vars as $k => $v) {
        $content = str_replace('{{' . $k . '}}', (string)$v, $content);
    }
    return $content;
}
