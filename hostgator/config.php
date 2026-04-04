<?php
/**
 * config.php - Conecta 2.0 (HostGator)
 * Location: /public_html/conecta/config.php
 *
 * Constantes globais do Conecta 2.0.
 * A unica fonte de verdade de associados e o Conecta CRM ACIC.
 */

// ---- CRM Bridge (Conecta CRM ACIC) ------------------------------
define('CRM_API_URL',       'https://api-crm.acicdf.org.br');
define('CRM_BRIDGE_SECRET', 'conecta_crm_bridge_2026');

// ---- HostGator MySQL --------------------------------------------
// PREENCHER com credenciais reais do cPanel > MySQL Databases
define('DB_HOST', 'localhost');
define('DB_NAME', 'hg531e07_conecta2');
define('DB_USER', 'hg531e07_conecta2');
define('DB_PASS', '');

// ---- Admin local (CPF do administrador da ACIC) ------------------
// Login deste CPF nao consulta o CRM - fluxo local com senha em conecta_users
define('ADMIN_DOC', '00000000000');
