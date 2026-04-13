<?php
// ── Admin detection (GRU-2) ──────────────────────────────────────
$is_admin = false;
$admin_nome = '';
$admin_cpf = '01057808121';

$token_get = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token_get !== '') {
    $ch = curl_init('https://api.acicdf.org.br/api/auth/validate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['token' => $token_get]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $resp) {
        $data = json_decode($resp, true);
        if ($data) {
            $cpf = preg_replace('/\D/', '', $data['cpf_cnpj'] ?? $data['cpf'] ?? '');
            if ($cpf === $admin_cpf) {
                $is_admin  = true;
                $admin_nome = $data['nome'] ?? 'Administrador ACIC';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minhas Cobranas — Conecta ACIC</title>
<link rel="stylesheet" href="/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="/uploads/favicon-32x32.png"/>
<script>
(function(){var t=localStorage.getItem('acic_theme')||(window.matchMedia('(prefers-color-scheme:light)').matches?'light':'dark');document.documentElement.setAttribute('data-theme',t)})()
</script>
<style>
:root{--blue:#1B2B6B;--orange:#E8701A}
/* ── Summary card (topo) ── */
.taxas-summary{background:linear-gradient(135deg,var(--blue) 0%,#2d3f8a 100%);border-radius:16px;padding:24px 22px;color:#fff;margin-bottom:22px;position:relative;overflow:hidden}
.taxas-summary::after{content:'';position:absolute;top:-40px;right:-40px;width:140px;height:140px;background:rgba(255,255,255,.06);border-radius:50%}
.taxas-summary-title{font-family:var(--font-display,Montserrat,sans-serif);font-size:15px;font-weight:700;margin-bottom:4px;text-transform:uppercase;letter-spacing:.8px;opacity:.85}
.taxas-summary-value{font-size:28px;font-weight:800;font-family:var(--font-display,Montserrat,sans-serif)}
.taxas-summary-hint{font-size:12px;opacity:.7;margin-top:2px}
.taxas-summary-stats{display:flex;gap:18px;margin-top:16px;flex-wrap:wrap}
.taxas-summary-stat{text-align:center;min-width:60px}
.taxas-summary-stat-num{font-size:20px;font-weight:700}
.taxas-summary-stat-lbl{font-size:11px;opacity:.7;display:block;margin-top:2px}

/* ── Cobrança cards ── */
.cob-card{background:var(--surface,#fff);border-radius:14px;padding:18px 20px;margin-bottom:12px;border-left:4px solid var(--border,#ddd);transition:box-shadow .2s}
.cob-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.cob-card.paid{border-left-color:#22c55e}
.cob-card.pending{border-left-color:var(--orange)}
.cob-card.overdue{border-left-color:#ef4444}
.cob-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.cob-info{flex:1;min-width:0}
.cob-title{font-weight:600;font-size:14px;color:var(--text,#1a1a1a);margin-bottom:4px}
.cob-meta{font-size:12px;color:var(--text3,#888);display:flex;gap:12px;flex-wrap:wrap}
.cob-valor{font-family:var(--font-display,Montserrat,sans-serif);font-size:18px;font-weight:700;color:var(--text,#1a1a1a);white-space:nowrap}
.cob-actions{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center}
.cob-btn-pay{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:#fff;background:var(--orange);border:none;cursor:pointer;transition:opacity .2s}
.cob-btn-pay:hover{opacity:.85}
.cob-btn-pay.overdue{background:#ef4444}
.cob-btn-view{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;background:var(--surface2,#f5f5f5);color:var(--text,#1a1a1a);border:1px solid var(--border,#ddd);cursor:pointer;transition:opacity .2s}
.cob-btn-view:hover{opacity:.85}
.cob-modalidade{font-size:11px;color:var(--text3,#888);display:inline-flex;align-items:center;gap:4px}
.cob-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.cob-badge.paid{background:#dcfce7;color:#166534}
.cob-badge.pending{background:#fef3c7;color:#92400e}
.cob-badge.overdue{background:#fee2e2;color:#991b1b}

/* ── Admin card ── */
.admin-card{background:linear-gradient(135deg,var(--orange) 0%,#d35f00 100%);border-radius:14px;padding:20px;color:#fff;margin-bottom:18px}
.admin-card h3{margin:0 0 4px;font-size:15px;font-weight:700;font-family:var(--font-display,Montserrat,sans-serif)}
.admin-card p{margin:0;font-size:13px;opacity:.85}

/* ── Skeleton ── */
.taxas-skel{height:80px;background:var(--surface2,#f0f0f0);border-radius:14px;margin-bottom:12px;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:.6}50%{opacity:1}}

/* ── Empty / error states ── */
.empty-state{text-align:center;padding:48px 24px;color:var(--text3,#888)}
.empty-state h3{font-size:18px;color:var(--text,#1a1a1a);margin-bottom:8px}
.empty-state p{font-size:14px;margin-bottom:16px}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img id="sidebar-logo" src="/uploads/logo-light-320.png?v=2" alt="ACIC Conecta" class="sidebar-logo-img"/>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="/" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="nav-item" href="/#empresa" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Minha Empresa
    </a>
    <a class="nav-item" href="/#catalogo" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      Catálogo
    </a>
    <a class="nav-item active" style="text-decoration:none;display:flex;align-items:center;gap:10px;cursor:default">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Minhas Cobranas
    </a>
    <a class="nav-item" href="#" style="text-decoration:none;display:flex;align-items:center;gap:10px" id="link-carteirinha">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/><line x1="14" y1="15" x2="18" y2="15"/></svg>
      Minha Carteirinha
    </a>
    <a class="nav-item hidden" id="nav-comunicados" href="/#admin-comunicados" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Comunicados
    </a>
    <a class="nav-item hidden" id="nav-admin" href="/#admin-produtos" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Gerenciar Produtos
    </a>
    <a class="nav-item hidden" id="nav-usuarios" href="/#admin-usuarios" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Usuários
    </a>
    <a class="nav-item hidden" id="nav-metricas" href="/#admin-metricas" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Métricas
    </a>
    <a class="nav-item hidden" id="nav-parceiros" href="/#admin-parceiros" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
      Parceiros
    </a>
    <a class="nav-item hidden" id="nav-admins" href="/#admin-admins" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Admins
    </a>
  </nav>
  <div class="sidebar-footer">
    <div style="padding:0 8px 8px;font-size:10px;color:var(--text3);text-align:center;letter-spacing:.5px">
      ACIC Conecta <span style="color:var(--accent)">v1.1.0</span>
    </div>
    <div class="sb-user">
      <div class="sb-avatar" id="sb-avatar">A</div>
      <div class="sb-user-text">
        <span class="sb-company" id="sb-company">Carregando...</span>
        <span class="sb-status" id="sb-status">Associado Ativo</span>
      </div>
    </div>
    <button class="btn-logout" onclick="logout()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sair
    </button>
  </div>
</aside>

<header class="topbar">
  <button class="hamburger" onclick="toggleSidebar()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="topbar-title">Minhas Cobranas</div>
  <div class="topbar-right">
    <button class="theme-toggle" onclick="toggleTheme()" id="theme-btn" title="Alternar tema" aria-label="Alternar tema">
      <div class="theme-toggle-track" id="theme-track">
        <div class="theme-toggle-knob"></div>
      </div>
      <span id="theme-label"></span>
    </button>
  </div>
</header>

<main class="main-content">
  <div style="padding:24px;max-width:900px;margin:0 auto">
    <div id="page-content">
      <div class="taxas-skel"></div>
      <div class="taxas-skel"></div>
      <div class="taxas-skel" style="height:50px"></div>
    </div>
  </div>
</main>

<script>
const AUTH_URL = '/auth.php';

// Login único: restaura sessão de localStorage se não existir em sessionStorage
if (!sessionStorage.getItem('acic_conecta_token') && localStorage.getItem('acic_conecta_token')) {
  sessionStorage.setItem('acic_conecta_token', localStorage.getItem('acic_conecta_token'));
  var _ls = localStorage.getItem('acic_session');
  if (_ls) sessionStorage.setItem('acic_session', _ls);
}

const session  = JSON.parse(sessionStorage.getItem('acic_session') || '{}');

// GRU-2 fix: sync token from URL to sessionStorage + localStorage
const _urlParams = new URLSearchParams(window.location.search);
const _urlToken = _urlParams.get('token');
if (_urlToken) {
  sessionStorage.setItem('acic_conecta_token', _urlToken);
  localStorage.setItem('acic_conecta_token', _urlToken);
}
const token = _urlToken || sessionStorage.getItem('acic_conecta_token') || '';

// GRU-2 fix: PHP-side admin detection
const _phpIsAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const _phpAdminNome = "<?php echo addslashes($admin_nome); ?>";

// ── Theme system (matching portal principal) ──
function applyTheme(theme, save) {
  if (save === undefined) save = true;
  document.documentElement.setAttribute('data-theme', theme);
  document.body.setAttribute('data-theme', theme);
  document.body.style.background = theme === 'light' ? '#EEF1F8' : '#080E1A';
  document.body.style.color      = theme === 'light' ? '#0F2137' : '#EDF2FF';
  if (save) localStorage.setItem('acic_theme', theme);

  var logoLight = '/uploads/logo-light-320.png?v=2';
  var logoDark  = '/uploads/logo-dark-320.png?v=2';
  var sidebarLogo = document.getElementById('sidebar-logo');
  if (sidebarLogo) sidebarLogo.src = theme === 'light' ? logoLight : logoDark;

  var track = document.getElementById('theme-track');
  var label = document.getElementById('theme-label');
  var isLight = theme === 'light';
  if (track) track.classList.toggle('active', isLight);
  if (label) label.innerHTML = isLight
    ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg> Light'
    : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Dark';
}

function toggleTheme() {
  var current = document.documentElement.getAttribute('data-theme') || 'dark';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

function initTheme() {
  var saved = localStorage.getItem('acic_theme') ||
    (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  applyTheme(saved, false);
}

// ── Sidebar ──
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('active');
}

// ── Logout ──
async function logout() {
  try {
    await fetch(AUTH_URL + '?action=logout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout', token: token })
    });
  } catch (e) {}
  sessionStorage.clear();
  location.href = '/';
}

// ── User info in sidebar ──
var nome = session.nome || session.razaoSocial || 'Associado';
document.getElementById('sb-company').textContent = nome;
document.getElementById('sb-avatar').textContent = (nome[0] || '?').toUpperCase();
document.getElementById('sb-status').textContent = (_phpIsAdmin || session.is_admin) ? 'Administrador' : 'Associado Ativo';

// Show admin nav items if admin
if (_phpIsAdmin || session.is_admin) {
  ['nav-comunicados','nav-admin','nav-usuarios','nav-metricas','nav-parceiros','nav-admins'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
  });
}

// GRU-2 fix: pass token in sidebar links
if (token) {
  document.querySelectorAll('.sidebar-nav a.nav-item').forEach(function(a) {
    var h = a.getAttribute('href');
    if (h && h.indexOf('.php') !== -1) {
      a.setAttribute('href', h + (h.indexOf('?') !== -1 ? '&' : '?') + 'token=' + encodeURIComponent(token));
    }
  });
  var cartLink = document.getElementById('link-carteirinha');
  if (cartLink) cartLink.href = '/portal-carteirinha.php?token=' + encodeURIComponent(token);
}

// ── Helpers ──
function money(v) {
  return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function fmtDate(d) {
  if (!d) return '—';
  var p = d.split('-');
  if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
  return d;
}

function classifyCob(c) {
  if (c.status === 'pago' || c.status === 'paid') return 'paid';
  if (!c.data_vencimento) return 'pending';
  var today = new Date(); today.setHours(0,0,0,0);
  var due = new Date(c.data_vencimento + 'T00:00:00');
  return due < today ? 'overdue' : 'pending';
}

function badgeText(cls) {
  if (cls === 'paid') return 'Pago';
  if (cls === 'overdue') return 'Vencido';
  return 'Pendente';
}

var ICONS = {
  boleto: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="4" x2="5" y2="20"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="13" y1="4" x2="13" y2="20"/><line x1="17" y1="4" x2="17" y2="20"/><line x1="21" y1="4" x2="21" y2="20"/></svg> Boleto',
  cartao: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Cartão',
  pix: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><polyline points="9 11 12 14 22 4"/></svg> Pix'
};

// ── Load cobranças ──
(async function() {
  if (!token) {
    document.getElementById('page-content').innerHTML =
      '<div class="empty-state"><h3>Sessão expirada</h3><p>Faça login novamente para ver suas taxas.</p><a href="/" class="cob-btn-pay" style="text-decoration:none">Ir para o login</a></div>';
    return;
  }

  try {
    var res = await fetch(AUTH_URL + '?action=cobrancas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    });
    var json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Erro ao carregar');
    renderTaxas(json.cobrancas || []);
  } catch (e) {
    document.getElementById('page-content').innerHTML =
      '<div class="empty-state">' +
      '<h3>Não foi possível carregar suas taxas</h3>' +
      '<p>' + (e.message || 'Erro de conexão') + '</p>' +
      '<a href="https://wa.me/5561991234567" target="_blank" class="cob-btn-pay" style="width:auto;text-decoration:none">Falar no WhatsApp</a> ' +
      '<button onclick="location.reload()" class="cob-btn-view">Tentar novamente</button>' +
      '</div>';
  }
})();

function renderTaxas(list) {
  var html = '';

  // Admin card
  if (_phpIsAdmin) {
    html += '<div class="admin-card">' +
      '<h3>Painel Administrativo</h3>' +
      '<div class="taxas-summary-value" style="font-size:22px">' + (_phpAdminNome || 'Administrador ACIC') + '</div>' +
      '<div class="taxas-summary-hint">Você está logado como administrador. As cobranças abaixo são do sistema.</div>' +
      '</div>';
  }

  if (!list.length) {
    html += '<div class="empty-state"><h3>Nenhuma cobrança encontrada</h3><p>Suas taxas e cobranças aparecerão aqui quando disponíveis.</p></div>';
    document.getElementById('page-content').innerHTML = html;
    return;
  }

  // Summary
  var totalVal = list.reduce(function(s, c) { return s + Number(c.valor || 0); }, 0);
  var totalPago = list.reduce(function(s, c) { return s + Number(c.valor_pago || c.valor || 0); }, 0);
  var vencidas = list.filter(function(c) { return classifyCob(c) === 'overdue'; }).length;
  var proxima = list.filter(function(c) { return classifyCob(c) === 'pending'; }).sort(function(a, b) {
    return (a.data_vencimento || '').localeCompare(b.data_vencimento || '');
  })[0];

  html += '<div class="taxas-summary">' +
    '<div class="taxas-summary-title">Resumo Financeiro</div>' +
    '<div class="taxas-summary-value">' + money(totalVal) + '</div>' +
    '<div class="taxas-summary-hint">Total em cobranças</div>' +
    '<div class="taxas-summary-stats">' +
    '<div class="taxas-summary-stat"><div class="taxas-summary-stat-num">' + list.filter(function(c){return c.status==='pago'}).length + '</div><div class="taxas-summary-stat-lbl">Pagas</div></div>' +
    '<div class="taxas-summary-stat"><div class="taxas-summary-stat-num">' + vencidas + '</div><div class="taxas-summary-stat-lbl">Vencidas</div></div>' +
    '<div class="taxas-summary-stat"><div class="taxas-summary-stat-num">' + (proxima ? fmtDate(proxima.data_vencimento) : 'Em dia') + '</div><div class="taxas-summary-stat-lbl">Próximo venc.</div></div>' +
    '</div></div>';

  // Cards
  list.forEach(function(c) {
    var cls = classifyCob(c);
    var canPay = (cls === 'pending' || cls === 'overdue') && c.gateway_url;
    var descricao = c.descricao || c.plano_nome || 'Cobrança ACIC-DF';
    var modalidade = ICONS[c.forma_pagamento] || ICONS[c.modalidade] || '';

    html += '<div class="cob-card ' + cls + '">' +
      '<div class="cob-head">' +
      '<div class="cob-info">' +
      '<div class="cob-title">' + descricao + '</div>' +
      '<div class="cob-meta">' +
      '<span>Venc: ' + fmtDate(c.data_vencimento) + '</span>' +
      '<span class="cob-badge ' + cls + '">' + badgeText(cls) + '</span>' +
      '</div></div>' +
      '<div class="cob-valor">' + money(c.valor) + '</div>' +
      '</div>' +
      '<div class="cob-actions">' +
      (canPay ? '<a href="' + c.gateway_url + '" target="_blank" rel="noopener" class="cob-btn-pay ' + (cls === 'overdue' ? 'overdue' : '') + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><polyline points="9 11 12 14 22 4"/></svg> Pagar</a>' : '') +
      (c.comprovante_url ? '<a href="' + c.comprovante_url + '" target="_blank" class="cob-btn-view">Ver comprovante</a>' : '') +
      (modalidade ? '<span class="cob-modalidade">' + modalidade + '</span>' : '') +
      '</div></div>';
  });

  document.getElementById('page-content').innerHTML = html;
}

// ── Init ──
document.addEventListener('DOMContentLoaded', function() {
  initTheme();
  window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', function(e) {
    if (!localStorage.getItem('acic_theme')) applyTheme(e.matches ? 'light' : 'dark', false);
  });
});
</script>
</body>
</html>
