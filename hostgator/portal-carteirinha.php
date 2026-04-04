<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minha Carteirinha — Conecta ACIC</title>
<link rel="stylesheet" href="/conecta/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('acic_theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;background:var(--bg,#F8FAFC);color:var(--text1,#1E293B);min-height:100vh}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;height:60px;background:var(--surface,#FFF);border-bottom:1px solid var(--border,#E2E8F0)}
.topbar img{height:36px}
.btn-back{display:flex;align-items:center;gap:6px;color:var(--text2,#475569);font-size:13px;text-decoration:none;font-family:'Montserrat',sans-serif;font-weight:500}
.btn-back:hover{color:var(--text1,#1E293B)}
.topnav{display:flex;gap:4px}
.topnav a{font-size:12px;font-weight:500;color:var(--text3,#94A3B8);text-decoration:none;padding:6px 10px;border-radius:6px;font-family:'Montserrat',sans-serif;transition:all .15s}
.topnav a:hover{color:var(--text1,#1E293B);background:var(--bg,#F8FAFC)}
.topnav a.active{color:#E8701A;font-weight:600}
@media(max-width:640px){.topnav{display:none}}
.page-header{max-width:480px;margin:24px auto 0;padding:0 20px}
.page-title{font-size:22px;font-weight:700;color:var(--text1,#1E293B)}
.page-sub{font-size:13px;color:var(--text3,#94A3B8);margin-top:4px;font-weight:400}
.container{max-width:480px;margin:0 auto;padding:16px 20px 40px}
/* Carteirinha card — manter visual original */
.carteirinha{background:linear-gradient(135deg,#1B2B6B 0%,#2D3F8F 50%,#1B2B6B 100%);border-radius:16px;padding:28px 24px;color:#fff;position:relative;overflow:hidden;box-shadow:0 8px 32px rgba(27,43,107,.3);font-family:'Montserrat',sans-serif}
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
.cart-footer{display:flex;justify-content:space-between;align-items:center;position:relative;z-index:1}
.cart-footer-label{font-size:9px;text-transform:uppercase;opacity:.5;letter-spacing:.5px}
.cart-footer-value{font-size:12px;font-weight:600;opacity:.9}
.qr-section{text-align:center;margin-top:24px}
.qr-section h3{font-size:14px;font-weight:600;color:var(--text2,#475569);margin-bottom:12px;font-family:'Montserrat',sans-serif}
#qrcode{display:inline-block;padding:12px;background:var(--surface,#FFF);border-radius:12px;border:1px solid var(--border,#E2E8F0)}
#qrcode canvas,#qrcode img{border-radius:4px}
.actions{display:flex;gap:10px;justify-content:center;margin-top:20px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none;font-family:'Montserrat',sans-serif;transition:opacity .15s}
.btn-blue{background:#1B2B6B;color:#fff}
.btn-blue:hover{opacity:.9}
.btn-outline{background:transparent;border:1px solid var(--border,#E2E8F0);color:var(--text1,#1E293B)}
.btn-outline:hover{background:var(--bg,#F8FAFC)}
.offline-badge{display:none;text-align:center;padding:6px;font-size:11px;color:#E8701A;background:#FFFBEB;border-radius:6px;margin-bottom:12px;font-family:'Montserrat',sans-serif}
.loading{text-align:center;padding:40px;color:var(--text3,#94A3B8);font-size:14px}
.empty{text-align:center;padding:32px;color:var(--text3,#94A3B8);font-size:13px}
.empty a{color:#E8701A;font-weight:600}
@media(max-width:480px){.container{padding:12px}.carteirinha{padding:20px 16px}.cart-nome{font-size:17px}.page-header{padding:0 12px;margin-top:16px}.page-title{font-size:18px}}
</style>
</head>
<body>
<div class="topbar">
  <img src="https://acicdf.org.br/conecta/uploads/logo-light-320.png" alt="ACIC Conecta">
  <nav class="topnav">
    <a href="/conecta/#dashboard">Dashboard</a>
    <a href="/conecta/#empresa">Minha Empresa</a>
    <a href="/conecta/portal-taxas.php">Minhas Taxas</a>
    <a href="/conecta/portal-carteirinha.php" class="active">Carteirinha</a>
  </nav>
  <a href="/conecta/" class="btn-back">&larr; Voltar</a>
</div>
<div class="page-header">
  <div class="page-title">Minha Carteirinha</div>
  <div class="page-sub">Carteirinha digital de associado ACIC-DF</div>
</div>
<div class="container">
  <div id="offline-msg" class="offline-badge">Exibindo dados salvos (offline)</div>
  <div id="content"><div class="loading">Carregando...</div></div>
</div>

<script>
const CRM_API='https://api.acicdf.org.br';
let token=sessionStorage.getItem('conecta_crm_token')||'';
const CACHE_KEY='conecta_carteirinha';

async function ensureCrmToken(){
  if(token)return true;
  const conectaToken=sessionStorage.getItem('acic_conecta_token')||localStorage.getItem('acic_conecta_token')||sessionStorage.getItem('conecta_token')||'';
  if(!conectaToken)return false;
  try{
    const r=await fetch(CRM_API+'/auth/sso-conecta',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({conecta_token:conectaToken})});
    const d=await r.json();
    if(d.ok!==false&&d.data?.token){token=d.data.token;sessionStorage.setItem('conecta_crm_token',token);return true;}
    if(d.token){token=d.token;sessionStorage.setItem('conecta_crm_token',token);return true;}
  }catch(e){console.warn('SSO CRM falhou:',e);}
  return false;
}

(async()=>{
  if(!token){
    const ok=await ensureCrmToken();
    if(!ok){
      const cached=localStorage.getItem(CACHE_KEY);
      if(cached){renderFromCache(JSON.parse(cached));}
      else{document.getElementById('content').innerHTML='<div class="empty">Sessao expirada. <a href="/conecta/">Faca login novamente</a>.</div>';}
      return;
    }
  }
  init();
})();

async function apiFetch(endpoint){
  try{
    const r=await fetch(CRM_API+endpoint,{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});
    if(r.status===401)return null;
    const d=await r.json();
    return d.data!==undefined?d.data:d;
  }catch(e){return null;}
}

function fmtDoc(d){
  if(!d)return'-';
  d=d.replace(/\D/g,'');
  if(d.length===14)return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,'$1.$2.$3/$4-$5');
  if(d.length===11)return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4');
  return d;
}

function fmtDate(d){if(!d)return'-';return new Date(d+'T00:00:00').toLocaleDateString('pt-BR');}

async function init(){
  const[me,cart]=await Promise.all([apiFetch('/associado/me'),apiFetch('/associado/carteirinha')]);
  if(!me&&!cart){
    const cached=localStorage.getItem(CACHE_KEY);
    if(cached){renderFromCache(JSON.parse(cached));return;}
    document.getElementById('content').innerHTML='<div class="empty">Erro ao carregar dados.</div>';
    return;
  }
  const dados={
    nome:me?.razao_social||me?.nome_fantasia||me?.nome_responsavel||'-',
    doc:me?.cnpj||me?.cpf||'',
    status:me?.status||'ativo',
    plano:me?.plano_nome||'',
    valido_ate:cart?.valido_ate||'',
    qr_data:cart?.qr_data||'',
    associado_desde:me?.data_associacao||''
  };
  localStorage.setItem(CACHE_KEY,JSON.stringify(dados));
  render(dados,false);
}

function renderFromCache(dados){document.getElementById('offline-msg').style.display='block';render(dados,true);}

function render(d,offline){
  const isAtivo=d.status==='ativo';
  const badgeCls=isAtivo?'cart-badge-green':'cart-badge-red';
  const badgeTxt=isAtivo?'ASSOCIADO ATIVO':d.status?.toUpperCase()||'';

  let html=`<div id="carteirinha-card" class="carteirinha">
    <div class="cart-header">
      <div><div class="cart-logo">CONECTA ACIC</div><div class="cart-org">Associacao Comercial e Industrial de Ceilandia-DF</div></div>
      <span class="cart-badge ${badgeCls}">${badgeTxt}</span>
    </div>
    <div class="cart-nome">${d.nome}</div>
    <div class="cart-doc">${fmtDoc(d.doc)}</div>
    <div class="cart-grid">
      <div><div class="cart-field-label">Plano</div><div class="cart-field-value">${d.plano||'—'}</div></div>
      <div><div class="cart-field-label">Associado desde</div><div class="cart-field-value">${fmtDate(d.associado_desde)}</div></div>
    </div>
    <div class="cart-divider"></div>
    <div class="cart-footer">
      <div><div class="cart-footer-label">Validade</div><div class="cart-footer-value">${fmtDate(d.valido_ate)}</div></div>
      <div style="text-align:right"><div class="cart-footer-label">Codigo</div><div class="cart-footer-value" style="font-family:monospace;font-size:10px;opacity:.6">${(d.doc||'').replace(/\D/g,'').slice(-6)}</div></div>
    </div>
  </div>`;

  html+='<div class="qr-section"><h3>QR Code de Validacao</h3><div id="qrcode"></div></div>';
  html+=`<div class="actions">
    <button class="btn btn-blue" onclick="downloadCard()">Baixar Carteirinha</button>
    <button class="btn btn-outline" onclick="shareCard()">Compartilhar</button>
  </div>`;

  document.getElementById('content').innerHTML=html;
  const qrContent=d.qr_data||JSON.stringify({nome:d.nome,doc:d.doc,status:d.status,plano:d.plano,valido_ate:d.valido_ate});
  new QRCode(document.getElementById('qrcode'),{text:qrContent,width:180,height:180,colorDark:'#1B2B6B',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
}

function downloadCard(){
  const el=document.getElementById('carteirinha-card');if(!el)return;
  html2canvas(el,{scale:2,useCORS:true,backgroundColor:null}).then(canvas=>{
    const link=document.createElement('a');link.download='carteirinha-acic.png';link.href=canvas.toDataURL('image/png');link.click();
  });
}

function shareCard(){
  const el=document.getElementById('carteirinha-card');if(!el)return;
  if(navigator.share){
    html2canvas(el,{scale:2,useCORS:true,backgroundColor:null}).then(canvas=>{
      canvas.toBlob(blob=>{
        const file=new File([blob],'carteirinha-acic.png',{type:'image/png'});
        navigator.share({title:'Minha Carteirinha ACIC',files:[file]}).catch(()=>{});
      });
    });
  }else{downloadCard();}
}
</script>
</body>
</html>
