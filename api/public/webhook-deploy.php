<?php
$secret = 'hux-deploy-2026';
$payload = file_get_contents('php://input');
$sig = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($sig, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
    http_response_code(403);
    exit('Unauthorized');
}
$output = shell_exec('cd /var/www/hux-crm-association && git pull origin main 2>&1');
// Deploy dashboard.html
$dash = shell_exec('cp /var/www/hux-crm-association/web/dashboard.html /var/www/crm-frontend/dashboard.html 2>&1');
// Deploy index.html (login)
$login = shell_exec('cp /var/www/hux-crm-association/web/index.html /var/www/crm-frontend/index.html 2>&1');
echo json_encode(['ok' => true, 'output' => $output, 'dashboard' => $dash, 'login' => $login]);
