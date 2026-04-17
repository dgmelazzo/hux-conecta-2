<?php
/**
 * Conecta 2.0 — Auth Helper
 * Valida JWT do CRM (fonte unica de verdade).
 * Substituiu conecta_sessions.
 */

if (!defined('CRM_API_URL')) require_once __DIR__ . '/config.php';

function validateCrmToken(?string $token = null): ?array {
    if (!$token) {
        $token = str_replace('Bearer ', '', trim($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $token = $input['token'] ?? $_GET['token'] ?? '';
        }
    }
    if (!$token) return null;

    // Cache: evita chamar CRM em toda request (5min TTL)
    $cacheKey = '/tmp/crm_token_' . hash('sha256', $token);
    if (file_exists($cacheKey) && (time() - filemtime($cacheKey)) < 300) {
        $cached = json_decode(file_get_contents($cacheKey), true);
        if ($cached) return $cached;
    }

    $ch = curl_init(CRM_API_URL . '/auth/conecta-validate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['token' => $token]),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;

    $data = json_decode($resp, true);
    $inner = $data['data']['data'] ?? $data['data'] ?? $data;

    if (empty($inner['valid']) && empty($inner['nome'])) return null;

    $result = [
        'id' => $inner['sub'] ?? $inner['id'] ?? $inner['associado_id'] ?? 0,
        'sub' => $inner['sub'] ?? $inner['id'] ?? 0,
        'associado_id' => $inner['associado_id'] ?? $inner['sub'] ?? null,
        'nome' => $inner['nome'] ?? '',
        'email' => $inner['email'] ?? '',
        'role' => $inner['role'] ?? '',
        'is_admin' => (bool)($inner['is_admin'] ?? false),
        'is_superadmin' => (bool)($inner['is_superadmin'] ?? false),
        'documento' => $inner['documento'] ?? $inner['cpf'] ?? $inner['cnpj'] ?? '',
        'cpf_cnpj' => $inner['documento'] ?? $inner['cpf'] ?? $inner['cnpj'] ?? '',
        'plano' => $inner['plano'] ?? $inner['plano_nome'] ?? '',
        'tenant_id' => $inner['tenant_id'] ?? 1,
        'token' => $inner['token'] ?? $token,
    ];

    // Salvar cache
    $oldMask = umask(0077); @file_put_contents($cacheKey, json_encode($result)); umask($oldMask);

    return $result;
}

function requireCrmAdmin(): array {
    $user = validateCrmToken();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessao invalida ou expirada.']);
        exit;
    }
    if (!$user['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso restrito ao administrador.']);
        exit;
    }
    return $user;
}

function requireCrmAuth(): ?array {
    return validateCrmToken();
}
