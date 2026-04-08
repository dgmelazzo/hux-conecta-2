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
:root{--blue:#1B2B6B;--orange:#E8701A}
/* ── Summary card (topo) ─────────────────────────────────────── */
.taxas-summary{background:linear-gradient(135deg,var(--blue) 0%,#2d3f8a 100%);border-radius:16px;padding:24px 22px;color:#fff;margin-bottom:22px;position:relative;overflow:hidden}
.taxas-summary::after{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(232,112,26,.18)}
.taxas-summary-label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;opacity:.75;font-weight:600;position:relative;z-index:1}
.taxas-summary-value{font-family:Montserrat,sans-serif;font-size:32px;font-weight:800;letter-spacing:-.5px;margin:6px 0 2px;position:relative;z-index:1}
.taxas-summary-hint{font-size:13px;opacity:.8;position:relative;z-index:1}
.taxas-summary-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:18px;position:relative;z-index:1}
.taxas-summary-stat{border-left:2px solid rgba(255,255,255,.18);padding-left:12px}
.taxas-summary-stat-num{font-family:Montserrat;font-size:22px;font-weight:700}
.taxas-summary-stat-lbl{font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.05em;font-weight:600}

/* ── Cobranca cards ──────────────────────────────────────────── */
.cob-list{display:flex;flex-direction:column;gap:12px}
.cob-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;transition:all .2s;display:flex;flex-direction:column;gap:12px;position:relative;overflow:hidden}
.cob-card:hover{border-color:var(--blue);box-shadow:0 4px 16px rgba(27,43,107,.08);transform:translateY(-1px)}
.cob-card.paid::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:#10B981}
.cob-card.pending::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:#F59E0B}
.cob-card.overdue::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:#E24B4A}
.cob-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.cob-info{flex:1;min-width:0}
.cob-title{font-family:Montserrat;font-size:15px;font-weight:700;color:var(--text);margin-bottom:3px;line-height:1.3}
.cob-sub{font-size:12px;color:var(--text3);display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.cob-sub svg{flex-shrink:0}
.cob-valor{font-family:Montserrat;font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.3px;white-space:nowrap}
.cob-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:99px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.cob-status.paid{background:rgba(16,185,129,.12);color:#047857}
.cob-status.pending{background:rgba(245,158,11,.12);color:#B45309}
.cob-status.overdue{background:rgba(226,75,74,.12);color:#B91C1C}
.cob-status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.cob-actions{display:flex;gap:8px;padding-top:10px;border-top:1px solid var(--border);align-items:center;flex-wrap:wrap}
.cob-btn-pay{flex:1;min-width:140px;background:var(--orange);color:#fff;border:none;border-radius:10px;padding:11px 16px;font-family:Montserrat;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:all .2s}
.cob-btn-pay:hover{background:#D06416;transform:translateY(-1px);box-shadow:0 4px 12px rgba(232,112,26,.25)}
.cob-btn-pay.overdue{background:#E24B4A}
.cob-btn-pay.overdue:hover{background:#C83432;box-shadow:0 4px 12px rgba(226,75,74,.3)}
.cob-btn-view{background:transparent;color:var(--text2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-family:Montserrat;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.cob-btn-view:hover{border-color:var(--blue);color:var(--blue)}
.cob-modalidade{font-size:10px;text-transform:uppercase;font-weight:700;letter-spacing:.08em;color:var(--text3);padding:4px 8px;background:var(--surface2);border-radius:6px}

/* ── Empty state ────────────────────────────────────────────── */
.taxas-empty{text-align:center;padding:56px 24px;background:var(--surface);border:1px solid var(--border);border-radius:14px}
.taxas-empty-icon{width:72px;height:72px;margin:0 auto 20px;background:rgba(27,43,107,.08);border-radius:50%;display:flex;align-items:center;justify-content:center}
.taxas-empty h3{font-family:Montserrat;font-size:18px;font-weight:700;color:var(--text);margin:0 0 8px}
.taxas-empty p{color:var(--text2);font-size:14px;line-height:1.5;max-width:380px;margin:0 auto}

/* ── Loading skeleton ───────────────────────────────────────── */
.taxas-skel{height:120px;background:linear-gradient(90deg,var(--surface2) 0%,var(--border) 50%,var(--surface2) 100%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:14px;margin-bottom:12px}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* ── Error banner ───────────────────────────────────────────── */
.taxas-error{background:rgba(226,75,74,.08);border:1px solid rgba(226,75,74,.2);border-radius:10px;padding:14px 16px;color:#B91C1C;font-size:13px;display:flex;align-items:center;gap:10px;margin-bottom:16px}

/* ── Mobile ─────────────────────────────────────────────────── */
@media(max-width:480px){
  .taxas-summary{padding:20px 18px}
  .taxas-summary-value{font-size:26px}
  .taxas-summary-grid{grid-template-columns:1fr 1fr;gap:10px}
  .taxas-summary-stat-num{font-size:18px}
  .cob-card{padding:16px}
  .cob-valor{font-size:18px}
  .cob-title{font-size:14px}
  .cob-btn-pay{width:100%;flex:initial}
  .cob-actions{flex-direction:column;align-items:stretch}
}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--surface);border:1px solid var(--border);color:var(--text);padding:10px 20px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s;box-shadow:var(--shadow)}
.toast.on{opacity:1}
.sidebar-overlay{position:fixed;inset:0;z-index:99;background:rgba(0,0,0,.4);display:none}
.sidebar-overlay.active{display:block}
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
    <div style="padding:0 8px 8px;font-size:10px;color:var(--text3);text-align:center;letter-spacing:.5px">ACIC Conecta <span style="color:var(--accent)">v1.2.0</span></div>
    <div class="sb-user">
      <div class="sb-avatar" id="sb-avatar">?</div>
      <div class="sb-user-text">
        <span class="sb-company" id="sb-company">Carregando...</span>
        <span class="sb-status" id="sb-status">Associado</span>
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

    <div id="summary-card"></div>
    <div id="page-content">
      <div class="taxas-skel"></div>
      <div class="taxas-skel"></div>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>

<script>
const AUTH_URL = '/conecta/auth.php';
const session  = JSON.parse(sessionStorage.getItem('acic_session') || '{}');
const token    = sessionStorage.getItem('acic_conecta_token') || '';

// Sidebar
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('active');}
function toggleTheme(){const c=document.documentElement.getAttribute('data-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('acic_theme',n);updateThemeUI();}
function updateThemeUI(){const t=document.documentElement.getAttribute('data-theme');const tr=document.getElementById('themeTrack');const lb=document.getElementById('theme-label');if(t==='dark'){tr.classList.add('active');lb.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Dark';}else{tr.classList.remove('active');lb.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/></svg> Light';}}
updateThemeUI();
async function logout(){try{await fetch(AUTH_URL+'?action=logout',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout',token})});}catch(e){}sessionStorage.clear();location.href='/conecta/';}

// User info in sidebar
const nome = session.nome || session.razaoSocial || 'Associado';
document.getElementById('sb-company').textContent = nome;
document.getElementById('sb-avatar').textContent = (nome[0]||'?').toUpperCase();
document.getElementById('sb-status').textContent = session.is_admin ? 'Administrador' : 'Associado Ativo';

// Helpers
function money(v){return 'R$ '+Number(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.').replace('R$ -','-R$ ');}
function fmtDate(s){if(!s)return '-';const d=new Date(s+'T00:00:00');return d.toLocaleDateString('pt-BR');}
function daysUntil(s){if(!s)return null;const d=new Date(s+'T00:00:00');const h=new Date();h.setHours(0,0,0,0);return Math.round((d-h)/(1000*60*60*24));}

// Status classification
function classifyCob(c){
  if(c.status==='pago') return 'paid';
  const days = daysUntil(c.data_vencimento);
  if(c.status==='expirado' || (c.status==='pendente' && days!==null && days<0)) return 'overdue';
  return 'pending';
}
function statusLabel(cls){return cls==='paid'?'Pago':cls==='overdue'?'Vencido':'Pendente';}
function vencLabel(c, cls){
  if(cls==='paid' && c.data_pagamento) return 'Pago em '+new Date(c.data_pagamento).toLocaleDateString('pt-BR');
  const days = daysUntil(c.data_vencimento);
  if(days===null) return 'Sem vencimento';
  if(days<0) return 'Venceu em '+fmtDate(c.data_vencimento)+' ('+Math.abs(days)+' dias atras)';
  if(days===0) return 'Vence hoje';
  if(days===1) return 'Vence amanha';
  if(days<=7) return 'Vence em '+days+' dias ('+fmtDate(c.data_vencimento)+')';
  return 'Vence em '+fmtDate(c.data_vencimento);
}

// Icons
function modIcon(m){
  const ic={pix:'<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M5.283 18.36a3.49 3.49 0 0 0 2.48-1.03l3.65-3.64c.24-.23.67-.23.91 0l3.67 3.66a3.49 3.49 0 0 0 2.48 1.03h.72l-4.64 4.64a3.5 3.5 0 0 1-4.94 0l-4.63-4.64h.28z"/></svg> PIX',boleto:'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="4" x2="5" y2="20"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="13" y1="4" x2="13" y2="20"/><line x1="17" y1="4" x2="17" y2="20"/><line x1="21" y1="4" x2="21" y2="20"/></svg> Boleto',cartao:'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg> Cartao'};
  return ic[m]||m;
}

// Render
function renderCobrancas(list){
  if(!list || !list.length){
    document.getElementById('page-content').innerHTML = `
      <div class="taxas-empty">
        <div class="taxas-empty-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1B2B6B" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <h3>Nenhuma cobranca encontrada</h3>
        <p>Voce esta em dia com a ACIC-DF. Quando houver novas cobrancas, elas aparecerao aqui.</p>
      </div>`;
    return;
  }

  // Summary stats
  const totalPendente = list.filter(c=>c.status==='pendente').reduce((s,c)=>s+Number(c.valor),0);
  const totalPago = list.filter(c=>c.status==='pago').reduce((s,c)=>s+Number(c.valor_pago||c.valor),0);
  const vencidas = list.filter(c=>{const cls=classifyCob(c);return cls==='overdue'}).length;
  const proxima = list.filter(c=>c.status==='pendente').sort((a,b)=>new Date(a.data_vencimento)-new Date(b.data_vencimento))[0];

  // Summary card
  let summaryInner = '';
  if(vencidas>0){
    summaryInner = `<div class="taxas-summary-label">Atencao</div>
      <div class="taxas-summary-value">${money(totalPendente)}</div>
      <div class="taxas-summary-hint">Voce tem ${vencidas} cobranca${vencidas>1?'s':''} em atraso. Regularize para evitar juros.</div>`;
  } else if(proxima){
    const d = daysUntil(proxima.data_vencimento);
    const urgent = d!==null && d<=7;
    summaryInner = `<div class="taxas-summary-label">Proxima cobranca</div>
      <div class="taxas-summary-value">${money(proxima.valor)}</div>
      <div class="taxas-summary-hint">${urgent?'⚠ ':''}${vencLabel(proxima,'pending')}</div>`;
  } else {
    summaryInner = `<div class="taxas-summary-label">Em dia</div>
      <div class="taxas-summary-value">Tudo certo</div>
      <div class="taxas-summary-hint">Voce nao tem cobrancas pendentes no momento.</div>`;
  }
  document.getElementById('summary-card').innerHTML = `
    <div class="taxas-summary">
      ${summaryInner}
      <div class="taxas-summary-grid">
        <div class="taxas-summary-stat"><div class="taxas-summary-stat-num">${list.length}</div><div class="taxas-summary-stat-lbl">Total</div></div>
        <div class="taxas-summary-stat"><div class="taxas-summary-stat-num">${list.filter(c=>c.status==='pago').length}</div><div class="taxas-summary-stat-lbl">Pagas</div></div>
        <div class="taxas-summary-stat"><div class="taxas-summary-stat-num">${list.filter(c=>c.status==='pendente').length}</div><div class="taxas-summary-stat-lbl">Pendentes</div></div>
      </div>
    </div>`;

  // Card list
  const cards = list.map(c=>{
    const cls = classifyCob(c);
    const canPay = (cls==='pending'||cls==='overdue') && c.gateway_url;
    const descricao = c.descricao || c.plano_nome || 'Cobranca ACIC-DF';
    return `
    <div class="cob-card ${cls}">
      <div class="cob-head">
        <div class="cob-info">
          <div class="cob-title">${descricao}</div>
          <div class="cob-sub">
            <span class="cob-status ${cls}"><span class="cob-status-dot"></span>${statusLabel(cls)}</span>
            <span>${vencLabel(c,cls)}</span>
          </div>
        </div>
        <div class="cob-valor">${money(c.valor)}</div>
      </div>
      <div class="cob-actions">
        ${canPay ? `<a href="${c.gateway_url}" target="_blank" rel="noopener" class="cob-btn-pay ${cls==='overdue'?'overdue':''}">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M20 12v4H6a2 2 0 0 0 0 4h14v-4"/><circle cx="16" cy="12" r="1" fill="currentColor"/></svg>
          Pagar agora
        </a>` : ''}
        ${c.gateway_url && cls==='paid' ? `<a href="${c.gateway_url}" target="_blank" rel="noopener" class="cob-btn-view">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Ver recibo
        </a>` : ''}
        <span class="cob-modalidade">${modIcon(c.modalidade)}</span>
      </div>
    </div>`;
  }).join('');

  document.getElementById('page-content').innerHTML = `<div class="cob-list">${cards}</div>`;
}

function showError(msg){
  document.getElementById('page-content').innerHTML = `
    <div class="taxas-error">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>${msg}</span>
    </div>
    <div class="taxas-empty">
      <div class="taxas-empty-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1B2B6B" stroke-width="2"><path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9V3m-9 9a9 9 0 0 0 9 9"/></svg>
      </div>
      <h3>Nao foi possivel carregar</h3>
      <p>Tente novamente em alguns instantes ou entre em contato com a ACIC-DF.</p>
      <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="https://wa.me/5561991234567" target="_blank" class="cob-btn-pay" style="width:auto;flex:initial">Falar no WhatsApp</a>
        <button onclick="location.reload()" class="cob-btn-view">Tentar novamente</button>
      </div>
    </div>`;
}

// Fetch cobrancas via auth.php bridge
async function loadCobrancas(){
  if(!token){location.href='/conecta/';return;}
  try{
    const res = await fetch(AUTH_URL+'?action=cobrancas',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'cobrancas',token})
    });
    const data = await res.json();
    if(res.status===401){sessionStorage.clear();location.href='/conecta/';return;}
    if(!data.success){showError(data.message||'Erro desconhecido');return;}
    renderCobrancas(data.data.cobrancas||[]);
  } catch(e){
    showError('Erro de conexao. Verifique sua internet.');
  }
}
// Admin: mostrar card informativo em vez de buscar cobranças
if (session.is_admin || session.is_superadmin || session.tipo === 'superadmin' || session.tipo === 'admin') {
  document.getElementById('taxas-content').innerHTML = `
    <div style="text-align:center;padding:48px 24px">
      <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#1B2B6B,#2d3f8a);margin:0 auto 16px;display:flex;align-items:center;justify-content:center">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h3 style="font-family:Montserrat,sans-serif;font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px">Painel Administrativo</h3>
      <p style="font-size:13px;color:var(--text3);max-width:400px;margin:0 auto 20px;line-height:1.5">Como administrador, gerencie as cobranças e taxas dos associados diretamente pelo CRM.</p>
      <a href="https://crm.acicdf.org.br" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#E8701A;color:#fff;padding:12px 24px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;transition:all .2s">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Acessar CRM
      </a>
    </div>
  `;
} else {
  loadCobrancas();
}
</script>
</body>
</html>
