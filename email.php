<?php
/**
 * ACIC CONECTA 2.0 — Envio de E-mail
 * ====================================
 * Utilitário de envio de e-mails HTML.
 * Requer que o servidor tenha sendmail/SMTP configurado.
 * Para produção recomenda-se usar PHPMailer ou a API de SMTP do provedor.
 */

if (!defined('EMAIL_FROM')) require_once __DIR__ . '/config.php';

/**
 * Envia e-mail de notificação para um associado.
 */
function enviarEmailNotificacao(string $para, string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null): bool {
    $assunto  = '[ACIC Conecta] ' . $titulo;
    $corBanda = match($tipo) {
        'sucesso' => '#22c55e',
        'aviso'   => '#f59e0b',
        'alerta'  => '#ef4444',
        default   => '#E8651A',   // laranja ACIC
    };
    $iconeTipo = match($tipo) {
        'sucesso' => '✅',
        'aviso'   => '⚠️',
        'alerta'  => '🔔',
        default   => 'ℹ️',
    };
    $labelTipo = match($tipo) {
        'sucesso' => 'Sucesso',
        'aviso'   => 'Aviso',
        'alerta'  => 'Alerta',
        default   => 'Informação',
    };

    $linkHtml = '';
    if ($link) {
        $linkEsc  = htmlspecialchars($link, ENT_QUOTES);
        $linkHtml = <<<HTML
        <div style="text-align:center;margin-top:28px">
          <a href="{$linkEsc}"
             style="display:inline-block;background:#E8651A;color:#fff;
                    text-decoration:none;padding:12px 28px;border-radius:8px;
                    font-weight:600;font-size:15px">
            Acessar
          </a>
        </div>
HTML;
    }

    $mensagemHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES));
    $ano          = date('Y');
    $from_nome    = defined('EMAIL_FROM_NOME') ? EMAIL_FROM_NOME : 'ACIC Conecta';
    $from_email   = defined('EMAIL_FROM')      ? EMAIL_FROM      : 'noreply@acicdf.org.br';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8">
  
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>{$assunto}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Helvetica Neue',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0"
             style="max-width:600px;width:100%;background:#fff;border-radius:12px;
                    overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">

        <!-- Header banda colorida -->
        <tr>
          <td style="background:{$corBanda};padding:0;height:6px"></td>
        </tr>

        <!-- Logo -->
        <tr>
          <td style="padding:32px 40px 20px;text-align:center;border-bottom:1px solid #f0f0f0">
            <img src="https://conecta.acicdf.org.br/uploads/logo-light-320.png"
                 alt="ACIC Conecta" style="height:44px;max-width:200px"/>
          </td>
        </tr>

        <!-- Badge de tipo -->
        <tr>
          <td style="padding:28px 40px 0;text-align:center">
            <span style="display:inline-block;background:{$corBanda}18;color:{$corBanda};
                         font-size:12px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
                         padding:5px 14px;border-radius:20px;border:1px solid {$corBanda}35">
              {$iconeTipo} {$labelTipo}
            </span>
          </td>
        </tr>

        <!-- Título -->
        <tr>
          <td style="padding:16px 40px 0;text-align:center">
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#111827;line-height:1.3">
              {$titulo}
            </h1>
          </td>
        </tr>

        <!-- Mensagem -->
        <tr>
          <td style="padding:16px 40px 0">
            <p style="margin:0;font-size:15px;color:#374151;line-height:1.7;text-align:center">
              {$mensagemHtml}
            </p>
          </td>
        </tr>

        <!-- CTA opcional -->
        {$linkHtml}

        <!-- Divider -->
        <tr>
          <td style="padding:32px 40px 0">
            <hr style="border:none;border-top:1px solid #f0f0f0"/>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px 32px;text-align:center">
            <p style="margin:0 0 6px;font-size:12px;color:#9ca3af">
              Você recebeu esta mensagem por ser associado da ACIC-DF.
            </p>
            <p style="margin:0;font-size:12px;color:#9ca3af">
              Para acessar o portal: 
              <a href="https://conecta.acicdf.org.br/"
                 style="color:#E8651A;text-decoration:none">conecta.acicdf.org.br</a>
            </p>
            <p style="margin:10px 0 0;font-size:11px;color:#d1d5db">
              © {$ano} ACIC-DF — Associação Comercial e Industrial do DF
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    // Versão texto plano (fallback)
    $texto = strip_tags($mensagem);
    if ($link) $texto .= "\n\nAcessar: {$link}";
    $texto .= "\n\n—\nPortal ACIC Conecta: https://conecta.acicdf.org.br/";

    // Boundary para multipart
    $boundary = md5(uniqid('acic_', true));

    $headers  = "From: =?UTF-8?B?" . base64_encode($from_nome) . "?= <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: ACIC-Conecta/2.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($texto)) . "\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";

    $body .= "--{$boundary}--";

    $assunto_enc = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

    $enviado = @mail($para, $assunto_enc, $body, $headers);
    if (!$enviado) {
        error_log("ACIC email falhou para {$para}: " . error_get_last()['message'] ?? 'unknown');
    }
    return $enviado;
}
