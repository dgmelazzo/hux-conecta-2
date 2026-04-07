#!/usr/bin/env python3
"""Replace CRM item selector with visual card grid for Conecta products."""
import sys

path = sys.argv[1] if len(sys.argv) > 1 else '/var/www/hux-crm-association/web/dashboard.html'
with open(path, 'r') as f:
    c = f.read()

# 1. Replace the simple Conecta select with a visual card grid
old_sel = '<div class="fg" style="margin-bottom:10px"><label class="fl3">Importar do Conecta 2.0</label><select class="fc" id="item-conecta-sel" style="background:#fff" onchange="preencherDoConecta(this.value)"><option value="">Digitar manualmente</option></select></div>'
new_sel = '''<div style="margin-bottom:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <label class="fl3" style="margin:0">Produtos do Catálogo Conecta</label>
          <span id="conecta-count" style="font-size:11px;color:var(--t3)"></span>
        </div>
        <input type="text" class="fc" id="conecta-busca" placeholder="Buscar produto..." style="background:#fff;margin-bottom:8px;font-size:12px" oninput="filtrarConectaProdutos()">
        <div id="conecta-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-height:320px;overflow-y:auto;padding:4px 2px">
          <div style="grid-column:1/-1;text-align:center;padding:24px;color:var(--t3);font-size:12px">Carregando produtos...</div>
        </div>
      </div>'''

if old_sel in c:
    c = c.replace(old_sel, new_sel)
    print("1. Visual grid selector replaced")
else:
    print("1. SKIP - old selector not found")

# 2. Replace JS functions
old_js = """// Conecta 2.0 product integration
let _conectaProdutos=[];
async function carregarProdutosConecta(){
  if(_conectaProdutos.length)return;
  try{
    const r=await fetch('https://acicdf.org.br/conecta/api.php?action=produtos',{headers:{'X-Bridge-Secret':'conecta_crm_bridge_2026'}});
    const d=await r.json();
    _conectaProdutos=d.data||[];
    const sel=document.getElementById('item-conecta-sel');
    if(sel){
      sel.innerHTML="<option value=''>Digitar manualmente</option>"+_conectaProdutos.map(p=>
        "<option value='"+p.id+"'>"+p.nome+(p.parceiro_nome?" ("+p.parceiro_nome+")":"")+"</option>"
      ).join('');
    }
  }catch(e){console.error('Conecta API:',e);}
}
function preencherDoConecta(id){
  if(!id){document.getElementById('item-conecta-id').value='';return;}
  const p=_conectaProdutos.find(x=>String(x.id)===String(id));
  if(!p)return;
  document.getElementById('item-conecta-id').value=p.id;
  document.getElementById('item-nome').value=p.nome;
}"""

new_js = """// Conecta 2.0 product integration (visual grid)
let _conectaProdutos=[];
let _conectaSelecionado=null;
async function carregarProdutosConecta(){
  const grid=document.getElementById('conecta-grid');if(!grid)return;
  try{
    const r=await fetch('https://acicdf.org.br/conecta/api.php?action=produtos',{headers:{'X-Bridge-Secret':'conecta_crm_bridge_2026'}});
    const d=await r.json();_conectaProdutos=d.data||[];renderConectaGrid(_conectaProdutos);
  }catch(e){if(grid)grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:20px;color:#EF4444;font-size:12px">Erro ao carregar <a href="#" onclick="carregarProdutosConecta();return false" style="color:var(--or)">Tentar novamente</a></div>';}
}
function renderConectaGrid(lista){
  const grid=document.getElementById('conecta-grid'),cnt=document.getElementById('conecta-count');
  if(!grid)return;
  if(cnt)cnt.textContent=lista.length+' produto'+(lista.length!==1?'s':'');
  if(!lista.length){grid.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:20px;color:var(--t3);font-size:12px">Nenhum produto</div>';return;}
  grid.innerHTML=lista.map(function(p){
    var sel=_conectaSelecionado===p.id,img=p.imagem||'';
    return '<div onclick="selecionarConecta('+p.id+')" style="cursor:pointer;border-radius:8px;border:'+(sel?'2px solid #E8700A':'1px solid var(--bd)')+';overflow:hidden;background:var(--bg);transition:all .15s ease;position:relative">'
      +(sel?'<div style="position:absolute;top:6px;right:6px;width:20px;height:20px;border-radius:50%;background:#E8700A;display:flex;align-items:center;justify-content:center;z-index:1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>':'')
      +(img?'<div style="aspect-ratio:4/3;overflow:hidden;background:var(--bd)"><img src="'+img+'" style="width:100%;height:100%;object-fit:cover'+(sel?';opacity:.85':'')+'" onerror="this.parentNode.style.display=\\'none\\'"></div>':'')
      +'<div style="padding:8px 10px">'
      +'<div style="font-size:11.5px;font-weight:600;color:'+(sel?'#E8700A':'var(--tx)')+';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+p.nome+'</div>'
      +(p.parceiro_nome?'<div style="font-size:10px;color:var(--t3);margin-top:2px">por '+p.parceiro_nome+'</div>':'')
      +'</div></div>';
  }).join('');
}
function selecionarConecta(id){
  if(_conectaSelecionado===id){_conectaSelecionado=null;document.getElementById('item-conecta-id').value='';document.getElementById('item-nome').value='';}
  else{_conectaSelecionado=id;var p=_conectaProdutos.find(function(x){return x.id===id;});if(p){document.getElementById('item-conecta-id').value=p.id;document.getElementById('item-nome').value=p.nome;}}
  var q=(document.getElementById('conecta-busca')||{}).value||'';
  renderConectaGrid(q?_conectaProdutos.filter(function(p){return p.nome.toLowerCase().indexOf(q.toLowerCase())>=0||(p.parceiro_nome||'').toLowerCase().indexOf(q.toLowerCase())>=0;}):_conectaProdutos);
}
function filtrarConectaProdutos(){
  var q=(document.getElementById('conecta-busca')||{}).value||'';
  renderConectaGrid(q?_conectaProdutos.filter(function(p){return p.nome.toLowerCase().indexOf(q.toLowerCase())>=0||(p.parceiro_nome||'').toLowerCase().indexOf(q.toLowerCase())>=0;}):_conectaProdutos);
}"""

if old_js in c:
    c = c.replace(old_js, new_js)
    print("2. JS functions replaced")
else:
    print("2. SKIP - old JS not found")

# 3. Update cleanup
old_clean = "if(document.getElementById('item-conecta-id'))document.getElementById('item-conecta-id').value='';if(document.getElementById('item-conecta-sel'))document.getElementById('item-conecta-sel').value='';"
new_clean = "if(document.getElementById('item-conecta-id'))document.getElementById('item-conecta-id').value='';_conectaSelecionado=null;if(document.getElementById('conecta-busca'))document.getElementById('conecta-busca').value='';if(typeof renderConectaGrid==='function')renderConectaGrid(_conectaProdutos||[]);"
if old_clean in c:
    c = c.replace(old_clean, new_clean)
    print("3. Cleanup updated")

with open(path, 'w') as f:
    f.write(c)
print("Done")
