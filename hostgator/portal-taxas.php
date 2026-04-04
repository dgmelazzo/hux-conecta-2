<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minhas Taxas — Conecta ACIC</title>
<link rel="stylesheet" href="/conecta/style.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('acic_theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
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
.page-header{max-width:680px;margin:24px auto 0;padding:0 20px}
.page-title{font-size:22px;font-weight:700;color:var(--text1,#1E293B)}
.page-sub{font-size:13px;color:var(--text3,#94A3B8);margin-top:4px;font-weight:400}
.container{max-width:680px;margin:0 auto;padding:16px 20px 40px}
.card{background:var(--surface,#FFF);border:1px solid var(--border,#E2E8F0);border-radius:12px;padding:20px;margin-bottom:16px}
.card-title{font-size:14px;font-weight:700;color:var(--text1,#1E293B);margin-bottom:14px;letter-spacing:-0.2px}
.badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;font-family:'Montserrat',sans-serif}
.badge-green{background:#ECFDF5;color:#16A34A}
.badge-red{background:#FEF2F2;color:#DC2626}
.badge-yellow{background:#FFFBEB;color:#EAB308}
.badge-gray{background:#F1F5F9;color:#94A3B8}
.badge-blue{background:#EFF6FF;color:#1B2B6B}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border,#E2E8F0);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--text2,#475569);font-weight:500}
.info-value{font-weight:600;color:var(--text1,#1E293B)}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-family:'Montserrat',sans-serif}
.alert-yellow{background:#FFFBEB;border:1px solid #FDE68A;color:#92400E}
.alert-red{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}
.cobranca{border:1px solid var(--border,#E2E8F0);border-radius:10px;padding:16px;margin-bottom:12px}
.cob-valor{font-size:24px;font-weight:800;color:#E8701A;letter-spacing:-0.5px}
.cob-desc{font-size:12px;color:var(--text2,#475569);margin-top:2px}
.cob-venc{font-size:12px;color:var(--text3,#94A3B8);margin-top:4px}
.cob-actions{display:flex;gap:8px;margin-top:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none;font-family:'Montserrat',sans-serif;transition:opacity .15s}
.btn-orange{background:#E8701A;color:#fff}
.btn-orange:hover{opacity:.9}
.btn-outline{background:transparent;border:1px solid var(--border,#E2E8F0);color:var(--text1,#1E293B)}
.btn-outline:hover{background:var(--bg,#F8FAFC)}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;padding:8px 10px;background:var(--bg,#F8FAFC);color:var(--text2,#475569);font-weight:600;border-bottom:1px solid var(--border,#E2E8F0)}
td{padding:8px 10px;border-bottom:1px solid var(--border,#E2E8F0);color:var(--text2,#475569)}
.loading{text-align:center;padding:40px;color:var(--text3,#94A3B8);font-size:14px}
.empty{text-align:center;padding:32px;color:var(--text3,#94A3B8);font-size:13px}
.empty a{color:#E8701A;font-weight:600}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1E293B;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s;font-family:'Montserrat',sans-serif}
.toast.on{opacity:1}
@media(max-width:480px){.cob-valor{font-size:20px}.container{padding:12px}.page-header{padding:0 12px;margin-top:16px}.page-title{font-size:18px}}
</style>
</head>
<body>
<div class="topbar">
  <img src="https://acicdf.org.br/conecta/uploads/logo-light-320.png" alt="ACIC Conecta">
  <nav class="topnav">
    <a href="/conecta/#dashboard">Dashboard</a>
    <a href="/conecta/#empresa">Minha Empresa</a>
    <a href="/conecta/portal-taxas.php" class="active">Minhas Taxas</a>
    <a href="/conecta/portal-carteirinha.php">Carteirinha</a>
  </nav>
  <a href="/conecta/" class="btn-back">&larr; Voltar</a>
</div>
<div class="page-header">
  <div class="page-title">Minhas Taxas</div>
  <div class="page-sub">Sua situacao financeira na ACIC-DF</div>
</div>
<div class="container">
  <div id="content"><div class="loading">Carregando...</div></div>
</div>
<div class="toast" id="toast"></div>

<script>
const CRM_API='https://api.acicdf.org.br';
// JWT CRM (se já tiver do SSO anterior)
let token=sessionStorage.getItem('conecta_crm_token')||'';

async function ensureCrmToken(){
  if(token)return true;
  // Token nativo do Conecta 2.0 para trocar por JWT CRM via SSO
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
  if(!token){const ok=await ensureCrmToken();if(!ok){document.getElementById('content').innerHTML='<div class="card"><div class="empty">Sessao expirada. <a href="/conecta/">Faca login novamente</a>.</div></div>';return;}}
  init();
})();

async function apiFetch(endpoint){
  try{
    const r=await fetch(CRM_API+endpoint,{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});
    if(r.status===401){document.getElementById('content').innerHTML='<div class="card"><div class="empty">Sessao expirada. <a href="/conecta/">Faca login novamente</a>.</div></div>';return null;}
    const d=await r.json();
    return d.data!==undefined?d.data:d;
  }catch(e){return null;}
}

function money(v){return'R$ '+parseFloat(v||0).toFixed(2).replace('.',',');}

function badgeStatus(s){
  const map={ativo:['badge-green','Ativo'],inadimplente:['badge-red','Inadimplente'],suspenso:['badge-yellow','Suspenso'],cancelado:['badge-gray','Cancelado'],prospecto:['badge-blue','Prospecto'],pendente:['badge-yellow','Pendente'],pago:['badge-green','Pago'],vencido:['badge-red','Vencido'],expirado:['badge-red','Vencido']};
  const[cls,lbl]=map[s]||['badge-gray',s||'-'];
  return'<span class="badge '+cls+'">'+lbl+'</span>';
}

function fmtDate(d){if(!d)return'-';return new Date(d+'T00:00:00').toLocaleDateString('pt-BR');}

function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('on');setTimeout(()=>t.classList.remove('on'),2500);}

async function init(){
  const[me,cobs]=await Promise.all([apiFetch('/associado/me'),apiFetch('/associado/cobrancas')]);
  if(!me){document.getElementById('content').innerHTML='<div class="card"><div class="empty">Erro ao carregar dados.</div></div>';return;}

  let html='';
  const hoje=new Date();hoje.setHours(0,0,0,0);
  const venc=me.data_vencimento?new Date(me.data_vencimento+'T00:00:00'):null;
  const diasAteVenc=venc?Math.ceil((venc-hoje)/(1000*60*60*24)):null;

  if(diasAteVenc!==null&&diasAteVenc<0){
    html+='<div class="alert alert-red"><strong>Vencido</strong> &mdash; Sua associacao venceu em '+fmtDate(me.data_vencimento)+'. Regularize para manter seus beneficios.</div>';
  }else if(diasAteVenc!==null&&diasAteVenc<=7){
    html+='<div class="alert alert-yellow"><strong>Atencao</strong> &mdash; Vence em '+diasAteVenc+' dia'+(diasAteVenc!==1?'s':'')+' ('+fmtDate(me.data_vencimento)+').</div>';
  }

  html+='<div class="card"><div class="card-title">Situacao da Associacao</div>';
  html+='<div class="info-row"><span class="info-label">Status</span><span class="info-value">'+badgeStatus(me.status)+'</span></div>';
  html+='<div class="info-row"><span class="info-label">Plano</span><span class="info-value">'+(me.plano_nome||'Nenhum')+'</span></div>';
  if(me.plano_valor)html+='<div class="info-row"><span class="info-label">Valor</span><span class="info-value">'+money(me.plano_valor)+'/mes</span></div>';
  html+='<div class="info-row"><span class="info-label">Proximo vencimento</span><span class="info-value">'+fmtDate(me.data_vencimento)+'</span></div>';
  html+='</div>';

  const cobList=cobs?.data||cobs||[];
  const pendentes=cobList.filter(c=>c.status==='pendente'||c.status==='vencido'||c.status==='expirado');
  if(pendentes.length>0){
    html+='<div class="card"><div class="card-title">Cobrancas Pendentes</div>';
    pendentes.forEach(c=>{
      html+='<div class="cobranca">';
      html+='<div style="display:flex;justify-content:space-between;align-items:flex-start">';
      html+='<div><div class="cob-valor">'+money(c.valor)+'</div>';
      html+='<div class="cob-desc">'+(c.descricao||'Taxa associativa')+'</div>';
      html+='<div class="cob-venc">Vencimento: '+fmtDate(c.data_vencimento)+'</div></div>';
      html+=badgeStatus(c.status);
      html+='</div>';
      html+='<div class="cob-actions">';
      if(c.link_pagamento)html+='<a href="'+c.link_pagamento+'" target="_blank" class="btn btn-orange">Pagar agora</a>';
      if(c.pix_copia_cola)html+='<button class="btn btn-outline" onclick="copyPix(this,\''+c.pix_copia_cola.replace(/'/g,"\\'")+'\')">Copiar PIX</button>';
      html+='</div></div>';
    });
    html+='</div>';
  }

  html+='<div class="card"><div class="card-title">Historico de Cobrancas</div>';
  if(cobList.length===0){
    html+='<div class="empty">Nenhuma cobranca registrada.</div>';
  }else{
    html+='<table><thead><tr><th>Vencimento</th><th>Descricao</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
    cobList.slice(0,10).forEach(c=>{
      html+='<tr><td>'+fmtDate(c.data_vencimento)+'</td><td>'+(c.descricao||'-')+'</td><td style="font-weight:600">'+money(c.valor)+'</td><td>'+badgeStatus(c.status)+'</td></tr>';
    });
    html+='</tbody></table>';
  }
  html+='</div>';
  document.getElementById('content').innerHTML=html;
}

function copyPix(btn,pix){
  navigator.clipboard.writeText(pix).then(()=>{showToast('PIX copiado!');btn.textContent='Copiado!';setTimeout(()=>{btn.textContent='Copiar PIX';},2000);}).catch(()=>{
    const ta=document.createElement('textarea');ta.value=pix;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);showToast('PIX copiado!');
  });
}
</script>
</body>
</html>
