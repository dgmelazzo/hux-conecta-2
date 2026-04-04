<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minha Carteirinha — Conecta ACIC</title>
<link rel="stylesheet" href="/conecta/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('acic_theme')||'light';document.documentElement.setAttribute('data-theme',t)})()</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
.carteirinha{background:linear-gradient(135deg,#1B2B6B 0%,#2D3F8F 50%,#1B2B6B 100%);border-radius:16px;padding:28px 24px;color:#fff;position:relative;overflow:hidden;box-shadow:0 8px 32px rgba(27,43,107,.3);font-family:var(--font-display);max-width:420px}
.carteirinha::before{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,255,255,.04);border-radius:50%}
.carteirinha::after{content:'';position:absolute;bottom:-60px;left:-30px;width:200px;height:200px;background:rgba(255,255,255,.03);border-radius:50%}
.cart-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;position:relative;z-index:1}
.cart-logo{font-size:11px;font-weight:700;letter-spacing:1px;opacity:.7;text-transform:uppercase}
.cart-org{font-size:10px;opacity:.5;margin-top:2px}
.cart-badge{padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;text-transform:uppercase}
.cart-badge-green{background:rgba(22,163,74,.2);color:#86EFAC;border:1px solid rgba(22,163,74,.3)}
.cart-badge-red{background:rgba(220,38,38,.2);color:#FCA5A5;border:1px solid rgba(220,38,38,.3)}
.cart-nome{font-size:20px;font-weight:700;margin-bottom:4px;position:relative;z-index:1;line-height:1.2}
.cart-doc{font-size:13px;opacity:.7;margin-bottom:16px;position:relative;z-index:1;letter-spacing:.5px}
.cart-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;position:relative;z-index:1}
.cart-field-label{font-size:9px;text-transform:uppercase;opacity:.5;letter-spacing:.5px;margin-bottom:2px}
.cart-field-value{font-size:13px;font-weight:600}
.cart-divider{height:1px;background:rgba(255,255,255,.1);margin:16px 0;position:relative;z-index:1}
.cart-footer-row{display:flex;justify-content:space-between;align-items:center;position:relative;z-index:1}
.cart-footer-label{font-size:9px;text-transform:uppercase;opacity:.5;letter-spacing:.5px}
.cart-footer-value{font-size:12px;font-weight:600;opacity:.9}
.qr-section{text-align:center;margin-top:24px}
.qr-section h3{font-family:var(--font-display);font-size:14px;font-weight:600;color:var(--text2);margin-bottom:12px}
#qrcode{display:inline-block;padding:12px;background:var(--surface);border-radius:12px;border:1px solid var(--border)}
#qrcode canvas,#qrcode img{border-radius:4px}
.card-actions{display:flex;gap:10px;justify-content:center;margin-top:20px}
.card-actions .btn-primary{width:auto;padding:10px 20px;font-size:13px}
.card-actions .btn-outline{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-family:var(--font-display);transition:var(--trans,all .2s)}
.card-actions .btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.offline-badge{text-align:center;padding:6px;font-size:11px;color:var(--accent);background:var(--accent-soft);border:1px solid var(--accent-border);border-radius:6px;margin-bottom:12px;display:none}
.empty-state{text-align:center;padding:32px;color:var(--text3);font-size:13px}
.empty-state a{color:var(--accent);font-weight:600;text-decoration:none}
.sidebar-overlay{position:fixed;inset:0;z-index:99;background:rgba(0,0,0,.4);display:none}
.sidebar-overlay.active{display:block}
@media(max-width:480px){.carteirinha{padding:20px 16px}.cart-nome{font-size:17px}}
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
    <a class="nav-item" href="/conecta/portal-taxas.php" style="text-decoration:none;display:flex;align-items:center;gap:10px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Minhas Taxas
    </a>
    <a class="nav-item active" href="/conecta/portal-carteirinha.php" style="text-decoration:none;display:flex;align-items:center;gap:10px">
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
  <div class="topbar-title">Minha Carteirinha</div>
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
        <h2>Minha Carteirinha</h2>
        <p class="section-sub">Carteirinha digital de associado ACIC-DF</p>
      </div>
    </div>
    <div id="offline-msg" class="offline-badge">Exibindo dados salvos (offline)</div>
    <div id="page-content">
      <div class="skeleton-row"></div><div class="skeleton-row short"></div><div class="skeleton-row"></div>
    </div>
  </div>
</main>

<script>
const CRM_API='https://api.acicdf.org.br';
let token=sessionStorage.getItem('conecta_crm_token')||'';
const CACHE_KEY='conecta_carteirinha';

function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('active')}
function toggleTheme(){const c=document.documentElement.getAttribute('data-theme');const n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('acic_theme',n);updateThemeUI()}
function updateThemeUI(){const t=document.documentElement.getAttribute('data-theme');const tr=document.getElementById('themeTrack');const lb=document.getElementById('theme-label');if(t==='dark'){tr.classList.add('active');lb.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Dark';}else{tr.classList.remove('active');lb.innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg> Light';}}
updateThemeUI();

async function ensureCrmToken(){
  if(token)return true;
  const ct=sessionStorage.getItem('acic_conecta_token')||localStorage.getItem('acic_conecta_token')||'';
  if(!ct)return false;
  try{const r=await fetch(CRM_API+'/auth/sso-conecta',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({conecta_token:ct})});const d=await r.json();const tk=d.data?.token||d.token;if(tk){token=tk;sessionStorage.setItem('conecta_crm_token',token);return true;}}catch(e){}
  return false;
}
async function apiFetch(ep){try{const r=await fetch(CRM_API+ep,{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});if(r.status===401)return null;const d=await r.json();return d.data!==undefined?d.data:d;}catch(e){return null;}}
function fmtDoc(d){if(!d)return'-';d=d.replace(/\D/g,'');if(d.length===14)return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,'$1.$2.$3/$4-$5');if(d.length===11)return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4');return d}
function fmtDate(d){if(!d)return'-';return new Date(d+'T00:00:00').toLocaleDateString('pt-BR')}
function updateSidebarUser(me){const nm=me?.nome_fantasia||me?.razao_social||me?.nome_responsavel||'Associado';document.getElementById('sb-company').textContent=nm;document.getElementById('sb-avatar').textContent=(nm[0]||'?').toUpperCase();document.getElementById('sb-status').textContent=me?.status==='ativo'?'Associado Ativo':me?.status||'Associado'}

(async()=>{
  if(!token){const ok=await ensureCrmToken();if(!ok){const c=localStorage.getItem(CACHE_KEY);if(c){renderFromCache(JSON.parse(c));}else{document.getElementById('page-content').innerHTML='<div class="empty-state">Sessao expirada. <a href="/conecta/">Faca login novamente</a></div>';}return;}}
  const[me,cart]=await Promise.all([apiFetch('/associado/me'),apiFetch('/associado/carteirinha')]);
  if(!me&&!cart){const c=localStorage.getItem(CACHE_KEY);if(c){renderFromCache(JSON.parse(c));}else{document.getElementById('page-content').innerHTML='<div class="empty-state">Erro ao carregar dados.</div>';}return;}
  if(me)updateSidebarUser(me);
  const dados={nome:me?.razao_social||me?.nome_fantasia||me?.nome_responsavel||'-',doc:me?.cnpj||me?.cpf||'',status:me?.status||'ativo',plano:me?.plano_nome||'',valido_ate:cart?.valido_ate||'',qr_data:cart?.qr_data||'',associado_desde:me?.data_associacao||''};
  localStorage.setItem(CACHE_KEY,JSON.stringify(dados));
  render(dados);
})();

function renderFromCache(dados){document.getElementById('offline-msg').style.display='block';render(dados)}
function render(d){
  const isAtivo=d.status==='ativo';
  const badgeCls=isAtivo?'cart-badge-green':'cart-badge-red';
  const badgeTxt=isAtivo?'ASSOCIADO ATIVO':(d.status||'').toUpperCase();
  let html=`<div id="carteirinha-card" class="carteirinha">
    <div class="cart-header"><div><div class="cart-logo">CONECTA ACIC</div><div class="cart-org">Associacao Comercial e Industrial de Ceilandia-DF</div></div><span class="cart-badge ${badgeCls}">${badgeTxt}</span></div>
    <div class="cart-nome">${d.nome}</div>
    <div class="cart-doc">${fmtDoc(d.doc)}</div>
    <div class="cart-grid"><div><div class="cart-field-label">Plano</div><div class="cart-field-value">${d.plano||'—'}</div></div><div><div class="cart-field-label">Associado desde</div><div class="cart-field-value">${fmtDate(d.associado_desde)}</div></div></div>
    <div class="cart-divider"></div>
    <div class="cart-footer-row"><div><div class="cart-footer-label">Validade</div><div class="cart-footer-value">${fmtDate(d.valido_ate)}</div></div><div style="text-align:right"><div class="cart-footer-label">Codigo</div><div class="cart-footer-value" style="font-family:monospace;font-size:10px;opacity:.6">${(d.doc||'').replace(/\D/g,'').slice(-6)}</div></div></div>
  </div>`;
  html+='<div class="qr-section"><h3>QR Code de Validacao</h3><div id="qrcode"></div></div>';
  html+='<div class="card-actions"><button class="btn-primary" onclick="downloadCard()">Baixar Carteirinha</button><button class="btn-outline" onclick="shareCard()">Compartilhar</button></div>';
  document.getElementById('page-content').innerHTML=html;
  const qr=d.qr_data||JSON.stringify({nome:d.nome,doc:d.doc,status:d.status,plano:d.plano,valido_ate:d.valido_ate});
  new QRCode(document.getElementById('qrcode'),{text:qr,width:180,height:180,colorDark:'#1B2B6B',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
}
function downloadCard(){const el=document.getElementById('carteirinha-card');if(!el)return;html2canvas(el,{scale:2,useCORS:true,backgroundColor:null}).then(c=>{const a=document.createElement('a');a.download='carteirinha-acic.png';a.href=c.toDataURL('image/png');a.click()})}
function shareCard(){const el=document.getElementById('carteirinha-card');if(!el)return;if(navigator.share){html2canvas(el,{scale:2,useCORS:true,backgroundColor:null}).then(c=>{c.toBlob(b=>{const f=new File([b],'carteirinha-acic.png',{type:'image/png'});navigator.share({title:'Minha Carteirinha ACIC',files:[f]}).catch(()=>{})})})}else{downloadCard()}}
</script>
</body>
</html>
