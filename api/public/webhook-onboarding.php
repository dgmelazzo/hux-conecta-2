<?php
/**
 * Webhook complement for onboarding user activation
 * Called from webhook handler after payment confirmed
 */
function processOnboardingActivation(int $associadoId): void {
    $stU = pdo()->prepare('SELECT id, email, nome FROM usuarios WHERE associado_id = ? AND ativo = 1 LIMIT 1');
    $stU->execute([$associadoId]);
    $usr = $stU->fetch();
    if (!$usr) return;

    pdo()->prepare('UPDATE usuarios SET primeiro_acesso = 0 WHERE id = ?')->execute([$usr['id']]);

    // Email de confirmacao
    $tplFile = '/var/www/hux-crm-association/emails/onboarding_confirmado.html';
    if (file_exists($tplFile)) {
        $stPl = pdo()->prepare('SELECT p.nome FROM associados a LEFT JOIN planos p ON p.id = a.plano_id WHERE a.id = ?');
        $stPl->execute([$associadoId]);
        $plRow = $stPl->fetch();
        $html = file_get_contents($tplFile);
        $html = str_replace(
            ['{{nome}}','{{empresa}}','{{plano}}'],
            [$usr['nome'] ?? '', $usr['nome'] ?? '', $plRow['nome'] ?? ''],
            $html
        );
        $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: ACIC Conecta <noreply@acicdf.org.br>\r\n";
        @mail($usr['email'], 'Pagamento confirmado - Acesse o Portal do Associado', $html, $h);
    }

    pdo()->prepare(
        'INSERT INTO notificacoes_log (tenant_id, usuario_id, tipo, destinatario_email, assunto, status, enviado_at)
         VALUES (?, ?, "onboarding_confirmado", ?, "Pagamento confirmado", "enviado", NOW())'
    )->execute([tenant_id(), $usr['id'], $usr['email']]);
}
