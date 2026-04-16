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

    return [
        'nome' => $inner['nome'] ?? '',
        'role' => $inner['role'] ?? '',
        'is_admin' => (bool)($inner['is_admin'] ?? false),
        'is_superadmin' => (bool)($inner['is_superadmin'] ?? false),
        'documento' => $inner['documento'] ?? '',
        'token' => $inner['token'] ?? $token,
    ];
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
