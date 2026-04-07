<?php
/**
 * ACIC-DF — Associe-se (Onboarding público)
 * Standalone page — no login required
 * Design System: Conecta 2.0
 */
header('Content-Type: text/html; charset=UTF-8');
$API = 'https://api-crm.acicdf.org.br';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Associe-se — ACIC-DF</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#080E1A;--surface:#0F1825;--text:#EDF2FF;--text-muted:#8A9BBF;--border:#1E2D45;--primary:#1B2B6B;--accent:#E8640A;--accent-hover:#E8701A;--danger:#E24B4A;--success:#1D9E75;--radius:12px;--font-display:'Montserrat',sans-serif;--font-body:'Inter',sans-serif}
[data-theme="light"]{--bg:#EEF1F8;--surface:#FFFFFF;--text:#0F2137;--text-muted:#5A6A85;--border:#D0D7E3}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s}
h1,h2,h3,h4{font-family:var(--font-display)}
a{color:var(--accent);text-decoration:none}

/* Theme toggle */
.theme-toggle{position:fixed;top:16px;right:16px;z-index:100;background:var(--surface);border:1px solid var(--border);border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--text);transition:background .3s}
.theme-toggle:hover{background:var(--border)}

/* Layout */
.page{display:flex;min-height:100vh}
.hero{flex:1;background:linear-gradient(135deg,#0B1526 0%,#1B2B6B 100%);padding:60px 48px;display:flex;flex-direction:column;justify-content:center;position:sticky;top:0;height:100vh}
.hero h1{font-size:2.4rem;font-weight:800;margin-bottom:12px;color:#fff}
.hero .subtitle{font-size:1.1rem;color:#A5B4D4;margin-bottom:40px;line-height:1.6}
.benefit{display:flex;align-items:flex-start;gap:12px;margin-bottom:24px}
.benefit .icon{width:36px;height:36px;background:rgba(232,100,10,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px}
.benefit h4{font-size:.95rem;color:#fff;margin-bottom:2px}
.benefit p{font-size:.82rem;color:#8A9BBF;line-height:1.5}

.form-col{flex:1;max-width:600px;padding:40px 48px;overflow-y:auto}

/* Progress */
.progress{display:flex;align-items:center;gap:8px;margin-bottom:36px}
.progress .step-dot{width:10px;height:10px;border-radius:50%;background:var(--border);transition:background .3s}
.progress .step-dot.active{background:var(--accent)}
.progress .step-dot.done{background:var(--success)}
.progress .step-bar{flex:1;height:3px;background:var(--border);border-radius:2px;position:relative;overflow:hidden}
.progress .step-bar .fill{height:100%;background:var(--accent);border-radius:2px;transition:width .4s}
.step-labels{display:flex;justify-content:space-between;margin-bottom:32px;font-size:.78rem;color:var(--text-muted)}
.step-labels span.active{color:var(--accent);font-weight:600}

/* Form elements */
.form-step{display:none}
.form-step.visible{display:block;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.form-title{font-size:1.3rem;font-weight:700;margin-bottom:24px}
.field{margin-bottom:18px}
.field label{display:block;font-size:.82rem;font-weight:500;margin-bottom:6px;color:var(--text-muted)}
.field label .req{color:var(--danger)}
.field input,.field select,.field textarea{width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border .2s}
.field input:focus,.field select:focus{border-color:var(--accent)}
.field input.error,.field select.error{border-color:var(--danger)}
.field .hint{font-size:.75rem;color:var(--text-muted);margin-top:4px}
.field .error-msg{font-size:.75rem;color:var(--danger);margin-top:4px;display:none}
.field input.error~.error-msg,.field select.error~.error-msg{display:block}
.row{display:flex;gap:14px}
.row .field{flex:1}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;border-radius:var(--radius);font-family:var(--font-body);font-size:.9rem;font-weight:600;border:none;cursor:pointer;transition:background .2s,transform .1s}
.btn:active{transform:scale(.98)}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-hover)}
.btn-secondary{background:transparent;color:var(--text-muted);border:1px solid var(--border)}
.btn-secondary:hover{border-color:var(--text-muted)}
.form-nav{display:flex;justify-content:space-between;margin-top:28px}

/* Plan cards */
.plans-grid{display:grid;gap:16px;margin-bottom:24px}
.plan-card{background:var(--bg);border:2px solid var(--border);border-radius:var(--radius);padding:20px;cursor:pointer;transition:border .2s,box-shadow .2s;position:relative}
.plan-card:hover{border-color:var(--text-muted)}
.plan-card.selected{border-color:var(--primary);box-shadow:0 0 0 2px rgba(27,43,107,.3)}
.plan-card.selected::after{content:"\2713";position:absolute;top:12px;right:12px;background:var(--primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px}
.plan-card h4{font-size:1rem;margin-bottom:4px}
.plan-card .price{font-size:1.5rem;font-weight:700;color:var(--accent);margin-bottom:8px}
.plan-card .price small{font-size:.75rem;font-weight:400;color:var(--text-muted)}
.plan-card p{font-size:.82rem;color:var(--text-muted);margin-bottom:8px;line-height:1.5}
.plan-card ul{list-style:none;padding:0}
.plan-card ul li{font-size:.8rem;color:var(--text-muted);padding:3px 0}
.plan-card ul li::before{content:"\2713";color:var(--success);margin-right:6px;font-weight:700}

/* Password strength */
.strength-bar{height:4px;background:var(--border);border-radius:2px;margin-top:6px;overflow:hidden}
.strength-bar .fill{height:100%;border-radius:2px;transition:width .3s,background .3s}
.strength-text{font-size:.72rem;margin-top:3px}

/* Checkbox */
.check-row{display:flex;align-items:center;gap:10px;margin-bottom:18px;font-size:.85rem}
.check-row input[type="checkbox"]{width:18px;height:18px;accent-color:var(--accent)}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);max-width:560px;width:90%;max-height:80vh;overflow-y:auto;padding:32px}
.modal h3{margin-bottom:16px}
.modal p{font-size:.85rem;line-height:1.7;color:var(--text-muted);margin-bottom:12px}
.modal .btn{margin-top:16px}

/* Loading overlay */
.loading-overlay{position:fixed;inset:0;background:rgba(8,14,26,.85);z-index:300;display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;color:#fff;font-size:1.1rem}
.loading-overlay.open{display:flex}
.spinner{width:48px;height:48px;border:4px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* Payment screen */
.pix-screen{text-align:center;animation:fadeIn .4s}
.pix-screen .qr{margin:20px auto;border-radius:var(--radius);max-width:220px}
.pix-screen .qr img{width:100%;border-radius:var(--radius)}
.pix-code{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:12px;font-size:.8rem;word-break:break-all;margin:12px 0;position:relative;cursor:pointer}
.pix-code .copy-hint{position:absolute;top:-8px;right:8px;background:var(--accent);color:#fff;padding:2px 8px;border-radius:4px;font-size:.7rem}
.pix-info{display:flex;justify-content:center;gap:24px;margin:16px 0;font-size:.85rem;color:var(--text-muted)}
.poll-status{margin-top:16px;font-size:.85rem;color:var(--text-muted)}

/* Responsive */
@media(max-width:900px){
  .page{flex-direction:column}
  .hero{position:static;height:auto;padding:40px 24px}
  .form-col{max-width:100%;padding:24px}
  .row{flex-direction:column;gap:0}
}
</style>
</head>
<body>

<button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema" aria-label="Alternar tema">&#9788;</button>

<div class="page">
  <!-- Left Hero -->
  <div class="hero">
    <h1>Associe-se a ACIC-DF</h1>
    <p class="subtitle">Junte-se a mais de 3.000 empresas e tenha acesso a uma rede de benefícios exclusivos para o crescimento do seu negócio.</p>
    <div class="benefit"><div class="icon">&#128640;</div><div><h4>Networking Estratégico</h4><p>Conecte-se com empresários e tomadores de decisão do Distrito Federal.</p></div></div>
    <div class="benefit"><div class="icon">&#128176;</div><div><h4>Economia Real</h4><p>Descontos exclusivos em serviços, saúde, educação e muito mais.</p></div></div>
    <div class="benefit"><div class="icon">&#128218;</div><div><h4>Capacitação Gratuita</h4><p>Workshops, palestras e cursos para você e sua equipe.</p></div></div>
    <div class="benefit"><div class="icon">&#9878;</div><div><h4>Assessoria Jurídica</h4><p>Orientação jurídica e tributária para sua empresa.</p></div></div>
  </div>

  <!-- Right Form -->
  <div class="form-col">
    <div class="progress">
      <span class="step-dot active" id="dot1"></span>
      <div class="step-bar"><div class="fill" id="bar" style="width:33%"></div></div>
      <span class="step-dot" id="dot2"></span>
      <div class="step-bar"><div class="fill" id="bar2" style="width:0%"></div></div>
      <span class="step-dot" id="dot3"></span>
    </div>
    <div class="step-labels">
      <span class="active" id="lbl1">Empresa</span>
      <span id="lbl2">Responsável</span>
      <span id="lbl3">Plano</span>
    </div>

    <form id="onboardForm" novalidate autocomplete="off">

      <!-- STEP 1 -->
      <div class="form-step visible" id="step1">
        <h3 class="form-title">Dados da Empresa</h3>
        <div class="field"><label>Razão Social <span class="req">*</span></label><input id="razao_social" required></div>
        <div class="field"><label>Nome Fantasia</label><input id="nome_fantasia"></div>
        <div class="field"><label>CNPJ <span class="req">*</span></label><input id="cnpj" placeholder="00.000.000/0000-00" maxlength="18" required><span class="error-msg">CNPJ inválido</span></div>
        <div class="field"><label>Capital Social <span class="req">*</span></label><input id="capital_social" placeholder="R$ 0,00" required></div>
        <div class="field"><label>Data de Abertura <span class="req">*</span></label><input type="date" id="data_abertura" required></div>
        <div class="field">
          <label>Faturamento Mensal <span class="req">*</span></label>
          <select id="faturamento" required>
            <option value="">Selecione...</option>
            <option value="ate_10k">Ate R$ 10.000</option>
            <option value="10k_50k">R$ 10.001 a R$ 50.000</option>
            <option value="50k_100k">R$ 50.001 a R$ 100.000</option>
            <option value="100k_500k">R$ 100.001 a R$ 500.000</option>
            <option value="acima_500k">Acima de R$ 500.000</option>
          </select>
        </div>
        <div class="field"><label>Número de Funcionários <span class="req">*</span></label><input type="number" id="num_funcionarios" min="0" required></div>
        <div class="form-nav"><span></span><button type="button" class="btn btn-primary" onclick="goStep(2)">Próximo &rarr;</button></div>
      </div>

      <!-- STEP 2 -->
      <div class="form-step" id="step2">
        <h3 class="form-title">Responsável Legal &amp; Endereço</h3>
        <div class="field"><label>Nome completo <span class="req">*</span></label><input id="nome_completo" required></div>
        <div class="row">
          <div class="field"><label>CPF <span class="req">*</span></label><input id="cpf" placeholder="000.000.000-00" maxlength="14" required><span class="error-msg">CPF inválido</span></div>
          <div class="field"><label>WhatsApp <span class="req">*</span></label><input id="whatsapp" placeholder="(00) 00000-0000" maxlength="15" required></div>
        </div>
        <div class="field"><label>Email <span class="req">*</span></label><input type="email" id="email" required></div>
        <div class="field"><label>CEP <span class="req">*</span></label><input id="cep" placeholder="00000-000" maxlength="9" required><span class="hint">Preencha o CEP para buscar o endereço automaticamente</span></div>
        <div class="row">
          <div class="field" style="flex:3"><label>Logradouro <span class="req">*</span></label><input id="logradouro" required></div>
          <div class="field" style="flex:1"><label>Número <span class="req">*</span></label><input id="numero" required></div>
        </div>
        <div class="field"><label>Complemento</label><input id="complemento"></div>
        <div class="row">
          <div class="field"><label>Bairro <span class="req">*</span></label><input id="bairro" required></div>
          <div class="field"><label>Cidade <span class="req">*</span></label><input id="cidade" readonly required></div>
          <div class="field" style="flex:.5"><label>UF <span class="req">*</span></label><input id="estado" readonly maxlength="2" required></div>
        </div>
        <div class="form-nav"><button type="button" class="btn btn-secondary" onclick="goStep(1)">&larr; Voltar</button><button type="button" class="btn btn-primary" onclick="goStep(3)">Próximo &rarr;</button></div>
      </div>

      <!-- STEP 3 -->
      <div class="form-step" id="step3">
        <h3 class="form-title">Plano &amp; Acesso</h3>
        <div id="plansGrid" class="plans-grid"><p style="color:var(--text-muted)">Carregando planos...</p></div>
        <div class="field"><label>Senha <span class="req">*</span> <small>(mín. 8 caracteres, 1 maiúscula, 1 número)</small></label><input type="password" id="senha" minlength="8" required><div class="strength-bar"><div class="fill" id="strengthFill"></div></div><div class="strength-text" id="strengthText"></div></div>
        <div class="field"><label>Confirmar Senha <span class="req">*</span></label><input type="password" id="senha_confirm" required><span class="error-msg">Senhas não coincidem</span></div>
        <div class="check-row"><input type="checkbox" id="termos" required><label for="termos">Li e aceito os <a href="#" onclick="openModal();return false">termos de associacao</a></label></div>
        <div class="form-nav"><button type="button" class="btn btn-secondary" onclick="goStep(2)">&larr; Voltar</button><button type="submit" class="btn btn-primary">Finalizar Associação &rarr;</button></div>
      </div>

    </form>

    <!-- PIX Payment screen (hidden) -->
    <div id="pixScreen" class="pix-screen" style="display:none">
      <h3 class="form-title">Pagamento via PIX</h3>
      <p style="color:var(--text-muted);margin-bottom:16px">Escaneie o QR Code ou copie o codigo para pagar.</p>
      <div class="qr"><img id="pixQr" alt="QR Code PIX"></div>
      <div class="pix-code" onclick="copyPix()" title="Clique para copiar"><span class="copy-hint">Copiar</span><span id="pixCode"></span></div>
      <div class="pix-info"><span>Valor: <strong id="pixValor"></strong></span><span>Vencimento: <strong id="pixVenc"></strong></span></div>
      <button class="btn btn-primary" onclick="pollStatus()" id="btnPoll">Ja paguei &mdash; verificar status</button>
      <div class="poll-status" id="pollMsg"></div>
    </div>
  </div>
</div>

<!-- Terms modal -->
<div class="modal-overlay" id="termsModal">
  <div class="modal">
    <h3>Termos de Associação</h3>
    <p>Ao se associar a ACIC-DF, o associado declara estar ciente e de acordo com o Estatuto Social e o Regimento Interno da Associação Comercial e Industrial de Ceilândia e Distrito Federal.</p>
    <p>O associado compromete-se a manter seus dados cadastrais atualizados e a cumprir com as obrigacoes financeiras referentes ao plano escolhido.</p>
    <p>A ACIC-DF reserva-se o direito de suspender ou cancelar a associacao em caso de inadimplência superior a 90 dias, mediante notificacao previa.</p>
    <p>Os benefícios serão disponibilizados após a confirmação do pagamento e estarão sujeitos às condições de cada parceiro conveniado.</p>
    <button class="btn btn-primary" onclick="closeModal()">Entendi</button>
  </div>
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div><span>Processando sua associação...</span></div>

<script>
const API = '<?= $API ?>';
let currentStep = 1;
let selectedPlan = null;
let onboardingId = null;
let pollTimer = null;

/* ── Theme ── */
function toggleTheme(){
  const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = t;
  localStorage.setItem('theme', t);
}
(function(){const s=localStorage.getItem('theme');if(s)document.documentElement.dataset.theme=s;})();

/* ── Masks ── */
function maskCNPJ(v){return v.replace(/\D/g,'').replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2}\.\d{3})(\d)/,'$1.$2').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2').slice(0,18);}
function maskCPF(v){return v.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14);}
function maskPhone(v){return v.replace(/\D/g,'').replace(/^(\d{2})(\d)/g,'($1) $2').replace(/(\d{5})(\d)/,'$1-$2').slice(0,15);}
function maskCEP(v){return v.replace(/\D/g,'').replace(/(\d{5})(\d)/,'$1-$2').slice(0,9);}
function maskMoney(v){let n=v.replace(/\D/g,'');if(!n)return '';n=n.replace(/^0+/,'')||'0';while(n.length<3)n='0'+n;let int=n.slice(0,-2),dec=n.slice(-2);int=int.replace(/\B(?=(\d{3})+(?!\d))/g,'.');return 'R$ '+int+','+dec;}

document.getElementById('cnpj').addEventListener('input',function(){this.value=maskCNPJ(this.value)});
document.getElementById('cpf').addEventListener('input',function(){this.value=maskCPF(this.value)});
document.getElementById('whatsapp').addEventListener('input',function(){this.value=maskPhone(this.value)});
document.getElementById('cep').addEventListener('input',function(){this.value=maskCEP(this.value)});
document.getElementById('capital_social').addEventListener('input',function(){this.value=maskMoney(this.value)});
document.getElementById('data_abertura').setAttribute('max',new Date().toISOString().split('T')[0]);

/* ── CNPJ Validation ── */
function validateCNPJ(c){
  c=c.replace(/\D/g,'');if(c.length!==14||/^(\d)\1+$/.test(c))return false;
  let t=c.length-2,d=c.substring(0,t),digs=c.substring(t),s=0,p=t-7;
  for(let i=t;i>=1;i--){s+=d.charAt(t-i)*p--;if(p<2)p=9;}
  let r=s%11<2?0:11-s%11;if(r!=parseInt(digs.charAt(0)))return false;
  t+=1;d=c.substring(0,t);s=0;p=t-7;
  for(let i=t;i>=1;i--){s+=d.charAt(t-i)*p--;if(p<2)p=9;}
  r=s%11<2?0:11-s%11;return r==parseInt(digs.charAt(1));
}

/* ── CPF Validation ── */
function validateCPF(c){
  c=c.replace(/\D/g,'');if(c.length!==11||/^(\d)\1+$/.test(c))return false;
  let s=0;for(let i=0;i<9;i++)s+=parseInt(c.charAt(i))*(10-i);
  let r=(s*10)%11;if(r===10)r=0;if(r!==parseInt(c.charAt(9)))return false;
  s=0;for(let i=0;i<10;i++)s+=parseInt(c.charAt(i))*(11-i);
  r=(s*10)%11;if(r===10)r=0;return r===parseInt(c.charAt(10));
}

/* ── CEP auto-fill ── */
let cepTimeout;
document.getElementById('cep').addEventListener('input',function(){
  clearTimeout(cepTimeout);
  const raw=this.value.replace(/\D/g,'');
  if(raw.length===8){
    cepTimeout=setTimeout(()=>{
      const ctrl=new AbortController();
      setTimeout(()=>ctrl.abort(),5000);
      fetch('https://viacep.com.br/ws/'+raw+'/json/',{signal:ctrl.signal})
        .then(r=>r.json()).then(d=>{
          if(!d.erro){
            document.getElementById('logradouro').value=d.logradouro||'';
            document.getElementById('bairro').value=d.bairro||'';
            document.getElementById('cidade').value=d.localidade||'';
            document.getElementById('estado').value=d.uf||'';
          }
        }).catch(()=>{});
    },300);
  }
});

/* ── Password strength ── */
document.getElementById('senha').addEventListener('input',function(){
  const v=this.value,fill=document.getElementById('strengthFill'),txt=document.getElementById('strengthText');
  let score=0;
  if(v.length>=8)score++;if(/[A-Z]/.test(v))score++;if(/\d/.test(v))score++;if(/[^A-Za-z0-9]/.test(v))score++;
  const w=['0%','25%','50%','75%','100%'],c=['#E24B4A','#E24B4A','#E8640A','#E8701A','#1D9E75'],l=['','Fraca','Razoável','Boa','Forte'];
  fill.style.width=w[score];fill.style.background=c[score]||'';
  txt.textContent=l[score]||'';txt.style.color=c[score]||'';
});

/* ── Plans ── */
async function loadPlans(){
  try{
    const r=await fetch(API+'/public/planos');
    const plans=await r.json();
    const grid=document.getElementById('plansGrid');
    grid.innerHTML='';
    (Array.isArray(plans)?plans:plans.data||[]).forEach(p=>{
      const card=document.createElement('div');
      card.className='plan-card';
      card.dataset.id=p.id;
      const items=(p.items||p.beneficios||[]).map(i=>'<li>'+i+'</li>').join('');
      const price=typeof p.valor==='number'?p.valor.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}):(p.preco||p.valor||'');
      card.innerHTML='<h4>'+p.nome+'</h4><div class="price">'+price+' <small>/mês</small></div><p>'+(p.descricao||'')+'</p>'+(items?'<ul>'+items+'</ul>':'');
      card.addEventListener('click',()=>{
        document.querySelectorAll('.plan-card').forEach(c=>c.classList.remove('selected'));
        card.classList.add('selected');
        selectedPlan=p.id;
      });
      grid.appendChild(card);
    });
  }catch(e){
    document.getElementById('plansGrid').innerHTML='<p style="color:var(--danger)">Erro ao carregar planos. Recarregue a página.</p>';
  }
}
loadPlans();

/* ── Step navigation ── */
function goStep(n){
  if(n>currentStep&&!validateStep(currentStep))return;
  document.querySelectorAll('.form-step').forEach(s=>s.classList.remove('visible'));
  document.getElementById('step'+n).classList.add('visible');
  currentStep=n;
  updateProgress();
}

function updateProgress(){
  for(let i=1;i<=3;i++){
    const dot=document.getElementById('dot'+i);
    dot.classList.remove('active','done');
    if(i<currentStep)dot.classList.add('done');
    else if(i===currentStep)dot.classList.add('active');
    const lbl=document.getElementById('lbl'+i);
    lbl.classList.toggle('active',i===currentStep);
  }
  document.getElementById('bar').style.width=currentStep>=2?'100%':'33%';
  document.getElementById('bar2').style.width=currentStep>=3?'100%':'0%';
}

/* ── Validation ── */
function validateStep(step){
  let ok=true;
  const mark=(el)=>{el.classList.add('error');ok=false;};
  const clear=(el)=>{el.classList.remove('error');};

  if(step===1){
    const f=['razao_social','cnpj','capital_social','data_abertura','faturamento','num_funcionarios'];
    f.forEach(id=>{const el=document.getElementById(id);el.value.trim()?clear(el):mark(el);});
    const cnpjEl=document.getElementById('cnpj');
    if(cnpjEl.value&&!validateCNPJ(cnpjEl.value))mark(cnpjEl);
  }
  if(step===2){
    ['nome_completo','cpf','email','whatsapp','cep','logradouro','numero','bairro','cidade','estado'].forEach(id=>{
      const el=document.getElementById(id);el.value.trim()?clear(el):mark(el);
    });
    const cpfEl=document.getElementById('cpf');
    if(cpfEl.value&&!validateCPF(cpfEl.value))mark(cpfEl);
    const emailEl=document.getElementById('email');
    if(emailEl.value&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value))mark(emailEl);
  }
  if(step===3){
    if(!selectedPlan){alert('Selecione um plano.');ok=false;}
    const s=document.getElementById('senha'),sc=document.getElementById('senha_confirm');
    if(s.value.length<8||!/[A-Z]/.test(s.value)||!/\d/.test(s.value)){mark(s);ok=false;}else clear(s);
    if(sc.value!==s.value){mark(sc);ok=false;}else clear(sc);
    if(!document.getElementById('termos').checked){alert('Aceite os termos de associação.');ok=false;}
  }
  return ok;
}

/* ── Submit ── */
document.getElementById('onboardForm').addEventListener('submit',async function(e){
  e.preventDefault();
  if(!validateStep(3))return;
  const overlay=document.getElementById('loadingOverlay');
  overlay.classList.add('open');
  const body={
    razao_social:document.getElementById('razao_social').value,
    nome_fantasia:document.getElementById('nome_fantasia').value,
    cnpj:document.getElementById('cnpj').value.replace(/\D/g,''),
    capital_social:document.getElementById('capital_social').value.replace(/[^\d,]/g,'').replace(',','.'),
    data_abertura:document.getElementById('data_abertura').value,
    faturamento:document.getElementById('faturamento').value,
    num_funcionarios:parseInt(document.getElementById('num_funcionarios').value)||0,
    nome_completo:document.getElementById('nome_completo').value,
    cpf:document.getElementById('cpf').value.replace(/\D/g,''),
    email:document.getElementById('email').value,
    whatsapp:document.getElementById('whatsapp').value.replace(/\D/g,''),
    cep:document.getElementById('cep').value.replace(/\D/g,''),
    logradouro:document.getElementById('logradouro').value,
    numero:document.getElementById('numero').value,
    complemento:document.getElementById('complemento').value,
    bairro:document.getElementById('bairro').value,
    cidade:document.getElementById('cidade').value,
    estado:document.getElementById('estado').value,
    plano_id:selectedPlan,
    senha:document.getElementById('senha').value
  };
  try{
    const r=await fetch(API+'/public/onboarding',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const data=await r.json();
    overlay.classList.remove('open');
    if(!r.ok)throw new Error(data.message||'Erro ao processar');
    onboardingId=data.id||data.data?.id;
    showPix(data);
  }catch(err){
    overlay.classList.remove('open');
    alert('Erro: '+(err.message||'Tente novamente.'));
  }
});

/* ── PIX Screen ── */
function showPix(data){
  document.getElementById('onboardForm').style.display='none';
  document.querySelector('.progress').style.display='none';
  document.querySelector('.step-labels').style.display='none';
  const pix=data.pix||data.data?.pix||data;
  document.getElementById('pixQr').src=pix.qr_code_base64||pix.qr_code||'';
  document.getElementById('pixCode').textContent=pix.codigo||pix.pix_code||pix.copiaecola||'';
  document.getElementById('pixValor').textContent=pix.valor?parseFloat(pix.valor).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}):'';
  document.getElementById('pixVenc').textContent=pix.vencimento||pix.due_date||'';
  document.getElementById('pixScreen').style.display='block';
}

function copyPix(){
  const code=document.getElementById('pixCode').textContent;
  navigator.clipboard.writeText(code).then(()=>{
    const hint=document.querySelector('.pix-code .copy-hint');
    hint.textContent='Copiado!';setTimeout(()=>hint.textContent='Copiar',2000);
  });
}

/* ── Poll status ── */
async function pollStatus(){
  if(!onboardingId)return;
  const msg=document.getElementById('pollMsg');
  const btn=document.getElementById('btnPoll');
  btn.disabled=true;
  msg.textContent='Verificando pagamento...';
  let attempts=0;
  if(pollTimer)clearInterval(pollTimer);
  pollTimer=setInterval(async()=>{
    attempts++;
    try{
      const r=await fetch(API+'/public/onboarding/'+onboardingId+'/status');
      const d=await r.json();
      const st=d.status||d.data?.status;
      if(st==='ativo'||st==='active'||st==='pago'){
        clearInterval(pollTimer);
        msg.textContent='Pagamento confirmado! Redirecionando...';
        msg.style.color='var(--success)';
        setTimeout(()=>window.location.href='/conecta/',1500);
      }else if(attempts>=18){
        clearInterval(pollTimer);
        msg.textContent='Pagamento ainda não identificado. Tente novamente em alguns minutos.';
        btn.disabled=false;
      }else{
        msg.textContent='Aguardando confirmação... ('+attempts+'/18)';
      }
    }catch(e){
      msg.textContent='Erro ao verificar. Tentando novamente...';
    }
  },10000);
}

/* ── Modal ── */
function openModal(){document.getElementById('termsModal').classList.add('open');}
function closeModal(){document.getElementById('termsModal').classList.remove('open');}
document.getElementById('termsModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
</script>
</body>
</html>
