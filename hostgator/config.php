<?php
/**
 * config.php - Conecta 2.0 (HostGator)
 * Conecta CRM ACIC é a única fonte de verdade de associados.
 * HiGestor: REMOVIDO
 */

// ---- CRM Bridge (Conecta CRM ACIC) ------------------------------
define('CRM_API_URL',       'https://api-crm.acicdf.org.br');
define('CRM_BRIDGE_SECRET', 'conecta_crm_bridge_2026');

// ---- HostGator MySQL --------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'hg531e07_conecta2');
define('DB_USER', 'hg531e07_conecta2');
define('DB_PASS', 'Conecta@2026');

// ---- Admin local ------------------------------------------------
define('ADMIN_DOC',      '01057808121');
define('ADMIN_NOME',     'Diego Gadia Melazzo Cruz');

// ---- Sessão -----------------------------------------------------
define('SESSION_TTL',    28800);
define('SECRET_KEY',     'acic_conecta_' . md5('hg531e07_conecta2hg531e07_conecta2'));

// ---- Email ------------------------------------------------------
define('EMAIL_FROM',     'noreply@acicdf.org.br');
define('EMAIL_FROM_NOME','ACIC Conecta');