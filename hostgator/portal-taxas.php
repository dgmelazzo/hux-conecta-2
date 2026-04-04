<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minhas Taxas — Conecta ACIC</title>
<style>
:root{--blue:#1B2B6B;--orange:#E8701A;--gn:#16A34A;--rd:#DC2626;--yw:#EAB308;--bg:#F8FAFC;--sf:#FFF;--tx:#1E293B;--t2:#475569;--t3:#94A3B8;--bd:#E2E8F0}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.header{background:var(--blue);color:#fff;padding:16px 20px;display:flex;align-items:center;gap:12px}
.header h1{font-size:18px;font-weight:700}
.header .back{color:#fff;text-decoration:none;font-size:14px;opacity:.8}
.header .back:hover{opacity:1}
.container{max-width:640px;margin:0 auto;padding:20px}
.card{background:var(--sf);border:1px solid var(--bd);border-radius:12px;padding:20px;margin-bottom:16px}
.card-title{font-size:14px;font-weight:700;color:var(--tx);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.badge{display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700}
.badge-green{background:#ECFDF5;color:var(--gn)}
.badge-red{background:#FEF2F2;color:var(--rd)}
.badge-yellow{background:#FFFBEB;color:var(--yw)}
.badge-gray{background:#F1F5F9;color:var(--t3)}
.badge-blue{background:#EFF6FF;color:var(--blue)}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--bd);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--t2);font-weight:500}
.info-value{font-weight:600;color:var(--tx)}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.alert-yellow{background:#FFFBEB;border:1px solid #FDE68A;color:#92400E}
.alert-red{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}
.cobranca{background:var(--sf);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:12px}
.cob-valor{font-size:24px;font-weight:700;color:var(--orange)}
.cob-desc{font-size:12px;color:var(--t2);margin-top:2px}
.cob-venc{font-size:12px;color:var(--t3);margin-top:4px}
.cob-actions{display:flex;gap:8px;margin-top:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none}
.btn-orange{background:var(--orange);color:#fff}
.btn-orange:hover{background:#CF6316}
.btn-outline{background:transparent;border:1px solid var(--bd);color:var(--tx)}
.btn-outline:hover{background:var(--bg)}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;padding:8px 10px;background:var(--bg);color:var(--t2);font-weight:600;border-bottom:1px solid var(--bd)}
td{padding:8px 10px;border-bottom:1px solid var(--bd);color:var(--t2)}
.loading{text-align:center;padding:40px;color:var(--t3);font-size:14px}
.empty{text-align:center;padding:32px;color:var(--t3);font-size:13px}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1E293B;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;z-index:9999;opacity:0;transition:opacity .3s}
.toast.on{opacity:1}
@media(max-width:480px){.cob-valor{font-size:20px}.container{padding:12px}}
</style>
</head>
<body>
<div class="header">
  <a href="/conecta/" class="back">← Voltar</a>
  <h1>Minhas Taxas</h1>
</div>
<div class="container">
  <div id="content"><div class="loading">Carregando...</div></div>
</div>
<div class="toast" id="toast"></div>

<script>
const CRM_API='https://api.acicdf.org.br';
const token=sessionStorage.getItem('conecta_crm_token')||localStorage.getItem('crm_token')||'';

if(!token){
  document.getElementById('content').innerHTML='<div class="card"><div class="empty">Sessão expirada. <a href="/conecta/">Faça login novamente</a>.</div></div>';
} else {
  init();
}

async function apiFetch(endpoint){
  try{
    const r=await fetch(CRM_API+endpoint,{headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json'}});
    if(r.status===401){document.getElementById('content').innerHTML='<div class="card"><div class="empty">Sessão expirada. <a href="/conecta/">Faça login novamente</a>.</div></div>';return null;}
    const d=await r.json();
    return d.data!==undefined?d.data:d;
  }catch(e){return null;}
}

function money(v){return'R$ '+parseFloat(v||0).toFixed(2).replace('.',',');}

function badgeStatus(s){
  const map={ativo:['badge-green','Ativo'],inadimplente:['badge-red','Inadimplente'],suspenso:['badge-yellow','Suspenso'],cancelado:['badge-gray','Cancelado'],prospecto:['badge-blue','Prospecto'],pendente:['badge-yellow','Pendente'],pago:['badge-green','Pago'],vencido:['badge-red','Vencido']};
  const[cls,lbl]=map[s]||['badge-gray',s||'-'];
  return'<span class="badge '+cls+'">'+lbl+'</span>';
}

function fmtDate(d){
  if(!d)return'-';
  const dt=new Date(d+'T00:00:00');
  return dt.toLocaleDateString('pt-BR');
}

function showToast(msg){
  const t=document.getElementById('toast');
  t.textContent=msg;t.classList.add('on');
  setTimeout(()=>t.classList.remove('on'),2500);
}

async function init(){
  const[me,cobs]=await Promise.all([apiFetch('/associado/me'),apiFetch('/associado/cobrancas')]);
  if(!me){document.getElementById('content').innerHTML='<div class="card"><div class="empty">Erro ao carregar dados.</div></div>';return;}

  let html='';
  const hoje=new Date();hoje.setHours(0,0,0,0);
  const venc=me.data_vencimento?new Date(me.data_vencimento+'T00:00:00'):null;
  const diasAteVenc=venc?Math.ceil((venc-hoje)/(1000*60*60*24)):null;

  // Alertas
  if(diasAteVenc!==null&&diasAteVenc<0){
    html+='<div class="alert alert-red"><strong>⚠ Vencido!</strong> Sua associação venceu em '+fmtDate(me.data_vencimento)+'. Regularize para manter seus benefícios.</div>';
  }else if(diasAteVenc!==null&&diasAteVenc<=7){
    html+='<div class="alert alert-yellow"><strong>⏰ Atenção!</strong> Sua associação vence em '+diasAteVenc+' dia'+(diasAteVenc!==1?'s':'')+' ('+fmtDate(me.data_vencimento)+').</div>';
  }

  // Seção 1 — Situação
  html+='<div class="card"><div class="card-title">📋 Situação da Associação</div>';
  html+='<div class="info-row"><span class="info-label">Status</span><span class="info-value">'+badgeStatus(me.status)+'</span></div>';
  html+='<div class="info-row"><span class="info-label">Plano</span><span class="info-value">'+(me.plano_nome||'Nenhum')+'</span></div>';
  if(me.plano_valor)html+='<div class="info-row"><span class="info-label">Valor</span><span class="info-value">'+money(me.plano_valor)+'/mês</span></div>';
  html+='<div class="info-row"><span class="info-label">Próximo vencimento</span><span class="info-value">'+fmtDate(me.data_vencimento)+'</span></div>';
  html+='</div>';

  // Seção 2 — Cobranças pendentes
  const cobList=cobs?.data||cobs||[];
  const pendentes=cobList.filter(c=>c.status==='pendente'||c.status==='vencido');
  if(pendentes.length>0){
    html+='<div class="card"><div class="card-title">💰 Cobranças Pendentes</div>';
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

  // Seção 3 — Histórico
  html+='<div class="card"><div class="card-title">📄 Histórico de Cobranças</div>';
  if(cobList.length===0){
    html+='<div class="empty">Nenhuma cobrança registrada.</div>';
  }else{
    const ultimas=cobList.slice(0,10);
    html+='<table><thead><tr><th>Vencimento</th><th>Descrição</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
    ultimas.forEach(c=>{
      html+='<tr><td>'+fmtDate(c.data_vencimento)+'</td><td>'+(c.descricao||'-')+'</td><td style="font-weight:600">'+money(c.valor)+'</td><td>'+badgeStatus(c.status)+'</td></tr>';
    });
    html+='</tbody></table>';
  }
  html+='</div>';

  document.getElementById('content').innerHTML=html;
}

function copyPix(btn,pix){
  navigator.clipboard.writeText(pix).then(()=>{
    showToast('PIX copiado!');
    btn.textContent='Copiado ✓';
    setTimeout(()=>{btn.textContent='Copiar PIX';},2000);
  }).catch(()=>{
    // Fallback
    const ta=document.createElement('textarea');
    ta.value=pix;document.body.appendChild(ta);ta.select();
    document.execCommand('copy');document.body.removeChild(ta);
    showToast('PIX copiado!');
  });
}
</script>
</body>
</html>
