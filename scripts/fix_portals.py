#!/usr/bin/env python3
"""Fix portal-taxas.php and portal-carteirinha.php for admin users."""

# ── Fix 2: portal-taxas.php (already applied if exists) ──
with open('/tmp/ptaxas.php', 'r') as f:
    c = f.read()

if 'loadCobrancas();' in c and 'Painel Administrativo' not in c:
    admin_card = '''// Admin: mostrar card informativo em vez de buscar cobranças
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
}'''
    c = c.replace('loadCobrancas();', admin_card)
    print('Fix 2: portal-taxas.php admin card added')
else:
    print('Fix 2: already applied or not needed')

with open('/tmp/ptaxas.php', 'w') as f:
    f.write(c)

# ── Fix 3: portal-carteirinha.php admin fields ──
with open('/tmp/pcart.php', 'r') as f:
    c = f.read()

# Insert admin override for dados before render(dados) call
old_render = "localStorage.setItem(CACHE_KEY,JSON.stringify(dados));\n  render(dados);"
admin_override = """// Admin: override campos da carteirinha
  if (session.is_admin || session.is_superadmin || session.tipo === 'superadmin' || session.tipo === 'admin') {
    dados.plano = 'Administrador';
    dados.associado_desde = 'ACIC-DF';
    dados.status = 'ativo';
    dados.valido_ate = null;
  }
  localStorage.setItem(CACHE_KEY,JSON.stringify(dados));
  render(dados);"""

if old_render in c and 'Administrador' not in c.split('dados.plano')[0] if 'dados.plano' in c else True:
    c = c.replace(old_render, admin_override)
    print('Fix 3: portal-carteirinha.php admin override added')
else:
    print('Fix 3: checking alternative pattern...')
    # Try without newline
    old2 = "localStorage.setItem(CACHE_KEY,JSON.stringify(dados));render(dados);"
    if old2 in c:
        c = c.replace(old2, admin_override.replace('\n  render(dados);', '\nrender(dados);'))
        print('Fix 3: applied (compact pattern)')

with open('/tmp/pcart.php', 'w') as f:
    f.write(c)

print('Done')
