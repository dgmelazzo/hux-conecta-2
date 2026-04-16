<?php
// Admin detection via sessionStorage (client-side).
// A API api.acicdf.org.br nao eh acessivel deste servidor,
// entao a deteccao PHP foi removida. O JS usa session.is_admin do localStorage.
$is_admin = false;
$admin_nome = "";
$token = isset($_GET["token"]) ? trim($_GET["token"]) : "";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minha Carteirinha — Conecta ACIC</title>
<link rel="stylesheet" href="/conecta/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="/conecta/uploads/favicon-32x32.png"/>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(function(){var t=localStorage.getItem('acic_theme')||(window.matchMedia('(prefers-color-scheme:light)').matches?'light':'dark');document.documentElement.setAttribute('data-theme',t);document.documentElement.style.background=t==='light'?'#EEF1F8':'#080E1A'})()
</script>
<style>
html[data-theme="dark"],html[data-theme="dark"] body{background:#080E1A;color:#EDF2FF}
html[data-theme="light"],html[data-theme="light"] body{background:#EEF1F8;color:#0F2137}
</style>
<style>
:root{--blue:#1B2B6B;--orange:#E8701A}
/* ── Carteirinha ── */
.carteirinha{background:linear-gradient(135deg,var(--blue) 0%,#1a3a7a 50%,#2d4a9a 100%);border-radius:20px;padding:28px 24px 24px;color:#fff;max-width:440px;margin:0 auto 24px;position:relative;overflow:hidden;box-shadow:0 8px 32px rgba(26,43,74,.3)}
.carteirinha::before{content:'';position:absolute;top:-60px;right:-60px;width:180px;height:180px;background:rgba(232,112,26,.15);border-radius:50%}
.carteirinha::after{content:'';position:absolute;bottom:-40px;left:-40px;width:120px;height:120px;background:rgba(255,255,255,.05);border-radius:50%}
.cart-header{display:flex;align-items:center;gap:12px;margin-bottom:18px;position:relative;z-index:1}
.cart-header img{height:36px}
.cart-header-text{font-family:var(--font-display,Montserrat,sans-serif);font-size:11px;text-transform:uppercase;letter-spacing:1.5px;opacity:.8}
.cart-nome{font-family:var(--font-display,Montserrat,sans-serif);font-size:22px;font-weight:800;margin-bottom:4px;position:relative;z-index:1}
.cart-doc{font-size:13px;opacity:.75;margin-bottom:16px;position:relative;z-index:1}
.cart-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;position:relative;z-index:1}
.cart-badge-green{background:rgba(34,197,94,.2);color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.cart-badge-red{background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
.cart-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;position:relative;z-index:1}
.cart-field-label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px}
.cart-field-value{font-size:14px;font-weight:600}
.cart-qr{text-align:center;padding-top:16px;border-top:1px solid rgba(255,255,255,.12);position:relative;z-index:1}
.cart-qr h3{font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:10px;font-weight:600}
.cart-qr img,.cart-qr canvas{display:block;margin:0 auto;border-radius:8px;background:#fff;padding:8px}
.cart-actions{display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.cart-actions button{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;border:none}
.cart-actions .btn-download{background:var(--orange);color:#fff}
.cart-actions .btn-share{background:var(--surface2,#f5f5f5);color:var(--text,#1a1a1a);border:1px solid var(--border,#ddd)}
.cart-actions button:hover{opacity:.85}

/* ── Offline badge ── */
.offline-badge{display:none;text-align:center;padding:8px 16px;margin-bottom:12px;background:#fef3c7;color:#92400e;border-radius:8px;font-size:12px;font-weight:500}

/* ── Empty state ── */
.empty-state{text-align:center;padding:48px 24px;color:var(--text3,#888)}
.empty-state a{color:var(--orange)}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img id="sidebar-logo" src="/conecta/uploads/logo-light-320.png?v=2" alt="ACIC Conecta" class="sidebar-logo-img"/>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="/conecta/" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="nav-item" href="/conecta/#empresa" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Minha Empresa
    </a>
    <a class="nav-item" href="/conecta/#catalogo" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      Catálogo
    </a>
    <a class="nav-item" href="#" style="text-decoration:none;display:flex;align-items:center;gap:10px" id="link-taxas">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Minhas Cobranças
    </a>
    <a class="nav-item active" style="text-decoration:none;display:flex;align-items:center;gap:10px;cursor:default">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/><line x1="14" y1="15" x2="18" y2="15"/></svg>
      Minha Carteirinha
    </a>
    <a class="nav-item hidden" id="nav-comunicados" href="/conecta/#admin-comunicados" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Comunicados
    </a>
    <a class="nav-item hidden" id="nav-admin" href="/conecta/#admin-produtos" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Gerenciar Produtos
    </a>
    <a class="nav-item hidden" id="nav-metricas" href="/conecta/#admin-metricas" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Métricas
    </a>
    <a class="nav-item hidden" id="nav-parceiros" href="/conecta/#admin-parceiros" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
      Parceiros
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
    <button class="btn-logout" onclick="doLogout()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sair
    </button>
  </div>
</aside>

<header class="topbar">
  <button class="hamburger" onclick="toggleSidebar()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="topbar-title">Minha Carteirinha</div>
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
  <div style="padding:24px;max-width:540px;margin:0 auto">
    <div id="offline-msg" class="offline-badge">Exibindo dados em cache (offline)</div>
    <div id="page-content">
      <div style="height:320px;background:var(--surface2,#f0f0f0);border-radius:20px;animation:pulse 1.5s infinite"></div>
    </div>
  </div>
</main>

<script>
var CACHE_KEY = 'acic_carteirinha_cache';
var AUTH_URL = '/auth.php';

// Login único: restaura sessão de localStorage
if (!sessionStorage.getItem('acic_conecta_token') && localStorage.getItem('acic_conecta_token')) {
  sessionStorage.setItem('acic_conecta_token', localStorage.getItem('acic_conecta_token'));
  var _ls = localStorage.getItem('acic_session');
  if (_ls) sessionStorage.setItem('acic_session', _ls);
}

var session = JSON.parse(sessionStorage.getItem('acic_session') || '{}');

// GRU-2 fix: sync token from URL to sessionStorage + localStorage
var _urlParams = new URLSearchParams(window.location.search);
var _urlToken = _urlParams.get('token');
if (_urlToken) {
  sessionStorage.setItem('acic_conecta_token', _urlToken);
  localStorage.setItem('acic_conecta_token', _urlToken);
}
var token = _urlToken || sessionStorage.getItem('acic_conecta_token') || '';

// ── Theme system (matching portal principal) ──
function applyTheme(theme, save) {
  if (save === undefined) save = true;
  document.documentElement.setAttribute('data-theme', theme);
  document.body.setAttribute('data-theme', theme);
  document.body.style.background = theme === 'light' ? '#EEF1F8' : '#080E1A';
  document.body.style.color      = theme === 'light' ? '#0F2137' : '#EDF2FF';
  if (save) localStorage.setItem('acic_theme', theme);

  var logoLight = '/conecta/uploads/logo-light-320.png?v=2';
  var logoDark  = '/conecta/uploads/logo-dark-320.png?v=2';
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
async function doLogout() {
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

// ── Sidebar user ──
function updateSidebarUser(nm, isAdmin) {
  document.getElementById('sb-company').textContent = nm || 'Associado';
  document.getElementById('sb-avatar').textContent = ((nm || '?')[0] || '?').toUpperCase();
  document.getElementById('sb-status').textContent = isAdmin ? 'Administrador' : 'Associado Ativo';
  // Show admin nav items if admin
  if (isAdmin) {
    ['nav-comunicados','nav-admin','nav-metricas','nav-parceiros'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.classList.remove('hidden');
    });
  }
}

// GRU-2 fix: pass token in sidebar links
if (token) {
  var taxasLink = document.getElementById('link-taxas');
  if (taxasLink) taxasLink.href = '/conecta/portal-taxas.php?token=' + encodeURIComponent(token);
}

// ── Load carteirinha ──
(async function() {
  // Sem token: tentar cache, senão redirecionar
  if (!token || !session.nome) {
    var c = localStorage.getItem(CACHE_KEY);
    if (c) { renderFromCache(JSON.parse(c)); return; }
    document.getElementById('page-content').innerHTML = '<div class="empty-state">Sessão expirada. <a href="/conecta/">Faça login novamente</a></div>';
    return;
  }

  // Server-side admin detection (PHP) injeta flag confiável
  var _phpIsAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

  // Usar dados do acic_session (preenchido pelo auth.php no login)
  var nome = session.nome || 'Associado';
  updateSidebarUser(nome, _phpIsAdmin || session.is_admin);
  var _phpAdminNome = "<?php echo addslashes($admin_nome); ?>";

  var dados = {
    nome: _phpIsAdmin && _phpAdminNome ? _phpAdminNome : nome,
    doc: session.cpf_cnpj || session.cpf || '',
    status: _phpIsAdmin ? 'ativo' : (session.status || 'ativo'),
    plano: _phpIsAdmin ? 'Administrador' : (session.plano || 'Associado'),
    valido_ate: _phpIsAdmin ? null : (session.data_vencimento || null),
    qr_data: JSON.stringify({
      id: session.crm_associado_id || null,
      doc: session.cpf_cnpj || session.cpf || '',
      nome: nome,
      plano: _phpIsAdmin ? 'Administrador' : (session.plano || ''),
      validade: session.data_vencimento || null,
      src: 'acic-conecta'
    }),
    associado_desde: _phpIsAdmin ? 'ACIC-DF' : (session.data_associacao || '')
  };

  localStorage.setItem(CACHE_KEY, JSON.stringify(dados));
  render(dados);
})();

function renderFromCache(dados) {
  document.getElementById('offline-msg').style.display = 'block';
  render(dados);
}

function render(d) {
  var isAtivo = d.status === 'ativo';
  var badgeCls = isAtivo ? 'cart-badge-green' : 'cart-badge-red';
  var badgeTxt = isAtivo ? 'ASSOCIADO ATIVO' : (d.status || '').toUpperCase();

  // Generate QR code as data URL
  var qrDiv = document.createElement('div');
  if (typeof QRCode !== 'undefined') {
    new QRCode(qrDiv, { text: d.qr_data, width: 200, height: 200, colorDark: '#1B2B6B', colorLight: '#ffffff' });
  }
  var qrImg = qrDiv.querySelector('img');
  var qrSrc = qrImg ? qrImg.src : '';
  // QRCode.js may use canvas
  if (!qrSrc) {
    var qrCanvas = qrDiv.querySelector('canvas');
    if (qrCanvas) qrSrc = qrCanvas.toDataURL('image/png');
  }

  var html = '<div id="carteirinha-card" class="carteirinha">' +
    '<div class="cart-header">' +
    '<img src="/conecta/uploads/logo-dark-320.png?v=2" alt="ACIC" style="height:32px">' +
    '<div class="cart-header-text">Carteira Digital do Associado</div>' +
    '</div>' +
    '<div class="cart-nome">' + (d.nome || 'Associado') + '</div>' +
    '<div class="cart-doc">' + (d.doc || '') + '</div>' +
    '<div class="cart-badge ' + badgeCls + '">' + badgeTxt + '</div>' +
    '<div class="cart-fields">' +
    '<div><div class="cart-field-label">Plano</div><div class="cart-field-value">' + (d.plano || 'Associado') + '</div></div>' +
    '<div><div class="cart-field-label">Associado Desde</div><div class="cart-field-value">' + (d.associado_desde || '—') + '</div></div>' +
    '<div><div class="cart-field-label">Validade</div><div class="cart-field-value">' + (d.valido_ate || 'Administrador') + '</div></div>' +
    '<div><div class="cart-field-label">Status</div><div class="cart-field-value">' + badgeTxt + '</div></div>' +
    '</div>' +
    '<div class="cart-qr">' +
    '<h3>QR Code de Validação</h3>' +
    (qrSrc ? '<img src="' + qrSrc + '" alt="QR Code" width="200" height="200" crossorigin="anonymous" style="display:block;border-radius:8px;background:#fff;padding:8px;margin:0 auto">' : '<div id="qrcode"></div>') +
    '</div></div>';

  html += '<div class="cart-actions">' +
    '<button class="btn-download" onclick="downloadCard()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Baixar</button>' +
    '<button class="btn-share" onclick="shareCard()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>' +
    '</div>';

  document.getElementById('page-content').innerHTML = html;

  // If QR code wasn't generated inline, try to render in the placeholder
  if (!qrSrc && typeof QRCode !== 'undefined') {
    var qrEl = document.getElementById('qrcode');
    if (qrEl) new QRCode(qrEl, { text: d.qr_data, width: 200, height: 200, colorDark: '#1B2B6B', colorLight: '#ffffff' });
  }
}

function downloadCard() {
  var el = document.getElementById('carteirinha-card');
  if (!el || typeof html2canvas === 'undefined') return;
  html2canvas(el, { scale: 2, useCORS: true, backgroundColor: null }).then(function(c) {
    var a = document.createElement('a');
    a.download = 'carteirinha-acic.png';
    a.href = c.toDataURL('image/png');
    a.click();
  });
}

function shareCard() {
  var el = document.getElementById('carteirinha-card');
  if (!el) return;
  if (navigator.share && typeof html2canvas !== 'undefined') {
    html2canvas(el, { scale: 2, useCORS: true, backgroundColor: null }).then(function(c) {
      c.toBlob(function(blob) {
        var file = new File([blob], 'carteirinha-acic.png', { type: 'image/png' });
        navigator.share({ title: 'Carteirinha ACIC-DF', files: [file] }).catch(function() {});
      });
    });
  } else {
    downloadCard();
  }
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
