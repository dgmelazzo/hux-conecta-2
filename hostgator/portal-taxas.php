<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minhas Taxas — Conecta ACIC</title>
<link rel="stylesheet" href="/conecta/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('acic_theme')||'light';document.documentElement.setAttribute('data-theme',t)})()</script>
<style>
.cob-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius,12px);padding:18px;margin-bottom:12px}
.cob-valor{font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--accent)}
.cob-desc{font-size:12px;color:var(--text2);margin-top:2px}
.cob-venc{font-size:12px;color:var(--text3);margin-top:4px}
.cob-actions{display:flex;gap:8px;margin-top:12px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 20px}
.info-field label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--text3)}
.info-field span{font-size:13px;font-weight:500;color:var(--text);display:block;margin-top:3px}
.alert-warn{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius-sm,8px);font-size:13px;margin-bottom:16px;background:rgba(255,184,0,.1);border:1px solid rgba(255,184,0,.2);color:#B8860B}
.alert-danger{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius-sm,8px);font-size:13px;margin-bottom:16px;background:rgba(226,75,74,.1);border:1px solid rgba(226,75,74,.25);color:#E24B4A}
.dash-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius,12px);padding:20px;margin-bottom:16px}
.dash-card-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--text);margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;padding:8px 10px;background:var(--surface2);color:var(--text2);font-weight:600;border-bottom:1px solid var(--border)}
td{padding:8px 10px;border-bottom:1px solid var(--border);color:var(--text2)}
.empty-state{text-align:center;padding:32px;color:var(--text3);font-size:13px}
.empty-state a{color:var(--accent);font-weight:600;text-decoration:none}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--surface);border:1px solid var(--border);color:var(--text);padding:10px 20px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s;box-shadow:var(--shadow)}
.toast.on{opacity:1}
.sidebar-overlay{position:fixed;inset:0;z-index:99;background:rgba(0,0,0,.4);display:none}
.sidebar-overlay.active{display:block}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}.cob-valor{font-size:20px}}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img class="sidebar-logo-img" src="/conecta/uploads/logo-light-320.png" alt="ACIC Conecta">
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="/conecta/" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a class="nav-item" href="/conecta/" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Minha Empresa
    </a>
    <a class="nav-item" href="/conecta/" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      Catalogo
    </a>
    <a class="nav-item active" href="/conecta/portal-taxas.php" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Minhas Taxas
    </a>
    <a class="nav-item" href="/conecta/portal-carteirinha.php" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/><line x1="14" y1="15" x2="18" y2="15"/></svg>
      Minha Carteirinha
    </a>
  </nav>
  <div class="sidebar-footer">
    <div style="padding:0 8px 8px;font-size:10px;color:var(--text3);text-align:center;letter-spacing:.5px">ACIC Conecta <span style="color:var(--accent)">v1.1.0</span></div>
    <div class="sb-user">
      <div class="sb-avatar" id="sb-avatar">?</div>
      <div class="sb-user-text">
        <span class="sb-company" id="sb-company">Carregando...</span>
        <span class="sb-status" id="sb-status">Associado</span>
      </div>
    </div>
    <button class="btn-logout" onclick="location.href='/conecta/'">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Sair
    </button>
  </div>
</aside>

<header class="topbar">
  <button class="hamburger" onclick="toggleSidebar()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="topbar-title">Minhas Taxas</div>
  <div class="topbar-right">
    <button class="theme-toggle" onclick="toggleTheme()">
      <div class="theme-toggle-track" id="themeTrack"><div class="theme-toggle-knob"></div></div>
      <span id="theme-label"></span>
    </button>
  </div>
</header>

<main class="main-content">
  <div class="portal-section active" style="display:block">
    <div class="section-header">
      <div>
        <h2>Minhas Taxas</h2>
        <p class="section-sub">Sua situacao financeira na ACIC-DF</p>
      </div>
    </div>
    <div id="page-content">
      <div class="skeleton-row"></div><div class="skeleton-row short"></div><div class="skeleton-row"></div>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>

<script>
const CRM_API='https://api.acicdf.org.br';
let token=sessionStorage.getItem('conecta_crm_token')||'';

// Sidebar toggle
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('active');
}

// Theme toggle
function toggleTheme(){
  const cur=document.documentElement.getAttribute('data-theme');
  const nxt=cur==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',nxt);
  localStorage.setItem('acic_theme',nxt);
  updateThemeUI();
}
function updateThemeUI(){
  const t=document.documentElement.getAttribute('data-theme');
  const track=document.getElementById('themeTrack');
  const label=document.getElementById('theme-label');
  if(t==='dark'){track.classList.add('active');label.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Dark';}
  else{track.classList.remove('active');label.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg> Light';}
}
updateThemeUI();

// Sem chamadas a API CRM — pagina statica com mensagem "em breve"
const session=JSON.parse(sessionStorage.getItem('acic_session')||'{}');
const nome=session.razaoSocial||session.nome||'Associado';
document.getElementById('sb-company').textContent=nome;
document.getElementById('sb-avatar').textContent=(nome[0]||'?').toUpperCase();
document.getElementById('sb-status').textContent='Associado Ativo';

document.getElementById('page-content').innerHTML=`
  <div class="dash-card" style="text-align:center;padding:48px 24px">
    <div style="width:64px;height:64px;border-radius:50%;background:rgba(27,43,107,.08);display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1B2B6B" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </div>
    <h3 style="font-family:var(--font-display);font-size:20px;font-weight:700;color:var(--text);margin:0 0 10px">Seu historico financeiro estara disponivel em breve</h3>
    <p style="color:var(--text2);font-size:14px;line-height:1.6;max-width:440px;margin:0 auto 24px">Estamos finalizando a integracao do sistema financeiro. Em caso de duvidas sobre cobrancas, mensalidades ou renovacao, entre em contato com a ACIC-DF.</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <a href="https://wa.me/5561991234567" target="_blank" class="btn-primary" style="width:auto;padding:10px 20px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
        Falar no WhatsApp
      </a>
      <a href="mailto:contato@acicdf.org.br" class="btn-table-action" style="padding:10px 20px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Enviar e-mail
      </a>
    </div>
  </div>`;
</script>
</body>
</html>
