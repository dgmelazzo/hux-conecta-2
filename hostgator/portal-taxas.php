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

// Token do Conecta 2.0 (autenticacao propria, sem SSO com CRM)
token=sessionStorage.getItem('acic_conecta_token')||localStorage.getItem('acic_conecta_token')||'';

async function apiFetch(ep){
  if(!token)return{_auth:false};
  try{
    const r=await fetch(CRM_API+ep,{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});
    if(r.status===401)return{_auth:false};
    const d=await r.json();return d.data!==undefined?d.data:d;
  }catch(e){return null;}
}

function money(v){return'R$ '+parseFloat(v||0).toFixed(2).replace('.',',')}
function fmtDate(d){if(!d)return'-';return new Date(d+'T00:00:00').toLocaleDateString('pt-BR')}
function badgeHtml(s){
  const m={ativo:'active',inadimplente:'inactive',suspenso:'pending',cancelado:'inactive',prospecto:'pending',pendente:'pending',pago:'active',vencido:'inactive',expirado:'inactive'};
  const l={ativo:'Ativo',inadimplente:'Inadimplente',suspenso:'Suspenso',cancelado:'Cancelado',pendente:'Pendente',pago:'Pago',vencido:'Vencido',expirado:'Vencido'};
  return'<span class="badge '+(m[s]||'pending')+'">'+(l[s]||s)+'</span>';
}
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('on');setTimeout(()=>t.classList.remove('on'),2500)}

// Sidebar user info
function updateSidebarUser(me){
  const nm=me?.nome_fantasia||me?.razao_social||me?.nome_responsavel||'Associado';
  document.getElementById('sb-company').textContent=nm;
  document.getElementById('sb-avatar').textContent=(nm[0]||'?').toUpperCase();
  document.getElementById('sb-status').textContent=me?.status==='ativo'?'Associado Ativo':me?.status||'Associado';
}

(async()=>{
  if(!token){document.getElementById('page-content').innerHTML='<div class="empty-state">Sessao expirada. <a href="/conecta/">Faca login novamente</a></div>';return;}
  const[me,cobs]=await Promise.all([apiFetch('/associado/me'),apiFetch('/associado/cobrancas')]);
  // Fallback gracioso: dados financeiros ainda nao integrados com token Conecta
  if(!me||me._auth===false){
    document.getElementById('page-content').innerHTML='<div class="dash-card" style="text-align:center;padding:48px 24px"><div style="font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px">Dados financeiros em breve</div><p style="color:var(--text2);font-size:13px;line-height:1.6;max-width:400px;margin:0 auto">A integracao financeira estara disponivel em breve. Para duvidas sobre cobrancas e mensalidades, entre em contato com a ACIC-DF.</p></div>';
    return;
  }
  updateSidebarUser(me);

  let html='';
  const hoje=new Date();hoje.setHours(0,0,0,0);
  const venc=me.data_vencimento?new Date(me.data_vencimento+'T00:00:00'):null;
  const dias=venc?Math.ceil((venc-hoje)/(1000*60*60*24)):null;

  if(dias!==null&&dias<0)html+='<div class="alert-danger"><strong>Vencido</strong> — Sua associacao venceu em '+fmtDate(me.data_vencimento)+'. Regularize para manter seus beneficios.</div>';
  else if(dias!==null&&dias<=7)html+='<div class="alert-warn"><strong>Atencao</strong> — Vence em '+dias+' dia'+(dias!==1?'s':'')+' ('+fmtDate(me.data_vencimento)+').</div>';

  html+='<div class="dash-card"><div class="dash-card-title">Situacao da Associacao</div><div class="info-grid">';
  html+='<div class="info-field"><label>Status</label><span>'+badgeHtml(me.status)+'</span></div>';
  html+='<div class="info-field"><label>Plano</label><span>'+(me.plano_nome||'Nenhum')+'</span></div>';
  if(me.plano_valor)html+='<div class="info-field"><label>Valor</label><span>'+money(me.plano_valor)+'/mes</span></div>';
  html+='<div class="info-field"><label>Proximo vencimento</label><span>'+fmtDate(me.data_vencimento)+'</span></div>';
  html+='</div></div>';

  const cobList=cobs?.data||cobs||[];
  const pend=cobList.filter(c=>c.status==='pendente'||c.status==='vencido'||c.status==='expirado');
  if(pend.length>0){
    html+='<div class="dash-card"><div class="dash-card-title">Cobrancas Pendentes</div>';
    pend.forEach(c=>{
      html+='<div class="cob-card"><div style="display:flex;justify-content:space-between;align-items:flex-start">';
      html+='<div><div class="cob-valor">'+money(c.valor)+'</div><div class="cob-desc">'+(c.descricao||'Taxa associativa')+'</div>';
      html+='<div class="cob-venc">Vencimento: '+fmtDate(c.data_vencimento)+'</div></div>'+badgeHtml(c.status)+'</div>';
      html+='<div class="cob-actions">';
      if(c.link_pagamento)html+='<a href="'+c.link_pagamento+'" target="_blank" class="btn-primary" style="width:auto;padding:8px 16px;font-size:13px">Pagar agora</a>';
      if(c.pix_copia_cola)html+='<button class="btn-table-action" onclick="copyPix(this,\''+c.pix_copia_cola.replace(/'/g,"\\'")+'\')">Copiar PIX</button>';
      html+='</div></div>';
    });
    html+='</div>';
  }

  html+='<div class="dash-card"><div class="dash-card-title">Historico de Cobrancas</div>';
  if(cobList.length===0){html+='<div class="empty-state">Nenhuma cobranca registrada.</div>';}
  else{
    html+='<div style="overflow-x:auto"><table><thead><tr><th>Vencimento</th><th>Descricao</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
    cobList.slice(0,10).forEach(c=>{html+='<tr><td>'+fmtDate(c.data_vencimento)+'</td><td>'+(c.descricao||'-')+'</td><td style="font-weight:600">'+money(c.valor)+'</td><td>'+badgeHtml(c.status)+'</td></tr>';});
    html+='</tbody></table></div>';
  }
  html+='</div>';
  document.getElementById('page-content').innerHTML=html;
})();

function copyPix(btn,pix){
  navigator.clipboard.writeText(pix).then(()=>{showToast('PIX copiado!');btn.textContent='Copiado!';setTimeout(()=>btn.textContent='Copiar PIX',2000)}).catch(()=>{
    const ta=document.createElement('textarea');ta.value=pix;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);showToast('PIX copiado!');
  });
}
</script>
</body>
</html>
