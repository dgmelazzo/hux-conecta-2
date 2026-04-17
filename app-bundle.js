/**
 * ACIC CONECTA — Camada de API
 * ==============================
 * Toda comunicação passa pelo auth.php (backend PHP na Hostgator).
 * O frontend nunca fala diretamente com o CRM - sempre via auth.php bridge.
 *
 * ★ ÚNICA CONFIGURAÇÃO NECESSÁRIA: defina AUTH_URL abaixo ★
 */

// URL do auth.php no seu servidor (sem barra no final)
const _isHml = window.location.hostname.includes('hml.');
const _baseUrl = _isHml ? 'https://hml.conecta.acicdf.org.br' : 'https://conecta.acicdf.org.br';
const AUTH_URL = _baseUrl + '/auth.php'; // ← ajuste se necessário

const SESSION_KEY = 'acic_conecta_token';

// ============================================================
// HELPERS
// ============================================================
function getToken() {
  return sessionStorage.getItem(SESSION_KEY) || localStorage.getItem(SESSION_KEY);
}

function setToken(token) {
  sessionStorage.setItem(SESSION_KEY, token);
  localStorage.setItem(SESSION_KEY, token);
}

function clearToken() {
  sessionStorage.removeItem(SESSION_KEY);
  sessionStorage.removeItem('acic_session');
  localStorage.removeItem(SESSION_KEY);
  localStorage.removeItem('acic_session');
}

// Login único: restaura sessão de localStorage se sessionStorage estiver vazia
if (!sessionStorage.getItem(SESSION_KEY) && localStorage.getItem(SESSION_KEY)) {
  sessionStorage.setItem(SESSION_KEY, localStorage.getItem(SESSION_KEY));
  const _lsSession = localStorage.getItem('acic_session');
  if (_lsSession) sessionStorage.setItem('acic_session', _lsSession);
}

async function authPost(action, body = {}) {
  const token = getToken() || '';
  const res = await fetch(AUTH_URL + '?action=' + action, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
    },
    body: JSON.stringify({ action, ...body }),
  });
  const data = await res.json();
  if (!data.success) throw { status: res.status, message: data.message };
  return data.data;
}

// ============================================================
// FLUXO DE LOGIN
// ============================================================

/**
 * Etapa 1 — Verifica se o CPF/CNPJ existe e qual o estado:
 *   { exists: bool, primeiro_acesso: bool, nome?: string }
 */
async function apiCheck(cpfCnpj) {
  return authPost('check', { cpf_cnpj: cpfCnpj });
}

/**
 * Etapa 2a — Primeiro acesso: define a senha e já loga
 */
async function apiPrimeiroAcesso(cpfCnpj, senha, confirmarSenha) {
  const data = await authPost('first', {
    cpf_cnpj: cpfCnpj,
    password: senha,
  });
  setToken(data.token);
  setSession({
    tipo:       data.tipo,
    crm_associado_id: data.crm_associado_id,
    cpf:        cpfCnpj,
    cpf_cnpj:   data.cpf_cnpj || cpfCnpj,
    nome:       data.nome,
    status:     data.status || 'ativo',
    plano:      data.plano || '',
    plano_valor: data.plano_valor || 0,
    data_associacao: data.data_associacao || null,
    data_vencimento: data.data_vencimento || null,
    is_admin:      data.is_admin || false,
    is_superadmin: data.is_superadmin || false,
  });
  return data;
}

/**
 * Etapa 2b — Login normal: CPF/CNPJ + senha
 */
async function apiLogin(cpfCnpj, senha) {
  const data = await authPost('login', { cpf_cnpj: cpfCnpj, password: senha });
  setToken(data.token);
  // Buscar permissoes do CRM
  try {
    const permRes = await fetch(_baseUrl + '/auth.php?action=permissoes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: data.token })
    });
    const permJson = await permRes.json();
    if (permJson.success) data._modulos = permJson.data.modulos || [];
  } catch(e) {}
  setSession({
    tipo:        data.tipo || data.role || 'associado_empresa',
    role:        data.role || data.tipo || 'associado_empresa',
    modulos:     data._modulos || [],
    crm_associado_id: data.crm_associado_id,
    cpf:         cpfCnpj,
    cpf_cnpj:    data.cpf_cnpj || data.documento || cpfCnpj,
    nome:        data.nome,
    status:      data.status || 'ativo',
    plano:       data.plano || '',
    plano_valor: data.plano_valor || 0,
    data_associacao: data.data_associacao || null,
    data_vencimento: data.data_vencimento || null,
    empresa_id:  data.empresa_id || null,
    is_admin:      data.is_admin || false,
    is_superadmin: data.is_superadmin || false,
  });
  return data;
}

// Login unificado via email
async function apiCheckTipo(email) {
  return authPost('check-tipo', { email });
}

async function apiLoginEmail(email, senha) {
  const data = await authPost('login-email', { email, senha });
  setToken(data.token);
  setSession({
    tipo:        data.tipo,
    nome:        data.nome,
    email:       data.email,
    empresa_cnpj: data.empresa_cnpj,
    is_admin:      data.is_admin || false,
    is_superadmin: data.is_superadmin || false,
    permissoes:  data.permissoes || null,
    destino:     data.destino || 'conecta',
    sso_token:   data.sso_token || null,
    redirect_crm: data.redirect_crm || null,
    redirect_conecta: data.redirect_conecta || null,
  });
  return data;
}

async function apiValidateSso(ssoToken) {
  const data = await authPost('validate-sso', { sso_token: ssoToken });
  setToken(data.token);
  setSession({
    tipo:        data.tipo,
    nome:        data.nome,
    email:       data.email,
    empresa_cnpj: data.empresa_cnpj,
    is_admin:      data.is_admin || false,
    is_superadmin: data.is_superadmin || false,
    permissoes:  data.permissoes || null,
    crm_associado_id: data.crm_associado_id || null,
    primeiro_acesso: data.primeiro_acesso || false,
  });
  return data;
}

async function apiAceitarConvite(conviteToken, senha, confirmarSenha) {
  const data = await authPost('aceitar-convite', {
    convite_token: conviteToken,
    senha,
    confirmar_senha: confirmarSenha,
  });
  setToken(data.token);
  setSession({
    tipo:        data.tipo,
    nome:        data.nome,
    permissoes:  data.permissoes || null,
    is_admin:    false,
    is_superadmin: false,
  });
  return data;
}

async function apiLogout() {
  const token = getToken();
  if (token) {
    await authPost('logout', { token }).catch(() => {});
  }
  clearToken();
}

function setSession(data) {
  const json = JSON.stringify(data);
  sessionStorage.setItem('acic_session', json);
  localStorage.setItem('acic_session', json);
}

function getSession() {
  const raw = sessionStorage.getItem('acic_session') || localStorage.getItem('acic_session');
  return raw ? JSON.parse(raw) : null;
}

// Permissões por perfil
function aplicarPermissoes(sessao) {
  if (!sessao) return;
  const p = sessao.permissoes || {};
  const tipo = sessao.tipo || 'associado_empresa';

  // Default: superadmin/admin vê tudo (exceto gestor)
  const isAdmin = sessao.is_admin || tipo === 'superadmin';
  if (isAdmin && !sessao.permissoes) {
    if (tipo === 'gestor') {
      ['nav-taxas','nav-parceiros'].forEach(id => {
        document.getElementById(id)?.classList.add('hidden');
      });
    }
    return;
  }

  const regras = {
    'nav-taxas':           p.ver_taxas !== false,
    'nav-empresa':         p.ver_empresa !== false,
    'nav-admin-produtos':  p.gerenciar_produtos === true,
    'nav-parceiros':       p.gerenciar_produtos === true,
    'nav-metricas':        p.ver_metricas === true,
    'nav-admin':           p.gerenciar_produtos === true || p.ver_metricas === true,
    'nav-configuracoes':   p.cadastrar_superadmin === true,
  };

  Object.entries(regras).forEach(([id, visivel]) => {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden', !visivel);
  });

  // Badge de perfil
  const badge = document.getElementById('perfil-badge');
  if (badge) {
    const labels = { superadmin:'Super Admin', gestor:'Gestor', associado_empresa:'Associado', colaborador:'Colaborador', dependente:'Dependente' };
    badge.textContent = labels[tipo] || tipo;
  }

  window._permissoes = p;
}

// ============================================================
// DADOS DO ASSOCIADO (via proxy auth.php -> Conecta CRM ACIC)
// ============================================================
async function apiGetAssociado() {
  const token   = getToken();
  const session = getSession();

  // Busca dados do CRM via auth.php
  const data = await authPost('dados', { token });

  // Admin retorna dados simples sem consulta ao CRM
  if (session?.is_admin === true) {
    return {
      tipo:           'administrador',
      razaoSocial:    'Administrador',
      nomeFantasia:   data.nome || session?.nome || 'Administrador ACIC',
      cnpj:           data.cpf_cnpj || session?.cpf_cnpj || '',
      email:          data.email || 'admin@acicdf.org.br',
      telefone:       '(61) 3344-3300',
      site:           'acicdf.org.br',
      status:         'admin',
      statusTexto:    'Administrador',
      dataAssociacao: 'ACIC-DF',
      categoria:      'Administrador',
      porte:          'Administração',
      logradouro:     'ACIC-DF',
      complemento:    '',
      bairro:         'Brasília',
      cidade:         'Brasília',
      uf:             'DF',
      cep:            '',
      numEmpregados:  '',
      registro:       '',
      ramo:           'Administração',
    };
  }

  if (session?.tipo === 'empresa') {
    const raw   = data.data || data;
    const attrs = raw.attributes || raw;
    return mapEmpresa(attrs, raw.id);
  } else {
    const raw   = data.data?.[0] || data.data || data;
    const attrs = raw.attributes || raw;
    return mapContribuinte(attrs, raw.id);
  }
}

function mapEmpresa(attrs, id) {
  return {
    id,
    tipo:           'empresa',
    razaoSocial:    attrs.razao_social          || attrs.nome || '—',
    nomeFantasia:   attrs.nome                  || '—',
    cnpj:           attrs.cnpj                 || attrs.cpf_cnpj || '—',
    email:          attrs.email                || '—',
    telefone:       attrs.telefone             || attrs.celular || '—',
    site:           attrs.site                 || '—',
    status:         attrs.associado ? 'ativo' : 'inativo',
    statusTexto:    attrs.status               || (attrs.associado ? 'Ativo' : 'Inativo'),
    dataAssociacao: attrs.associado_data_registro || null,
    registro:       attrs.associado_registro   || '—',
    categoria:      attrs.categoria            || attrs.porte || '—',
    porte:          attrs.porte                || '—',
    numEmpregados:  attrs.num_empregados       || '—',
    logradouro:     attrs.endereco             || '—',
    complemento:    attrs.complemento          || '',
    bairro:         attrs.bairro               || '—',
    cidade:         attrs.cidade               || '—',
    uf:             attrs.uf                   || '—',
    cep:            attrs.cep                  || '—',
  };
}

function mapContribuinte(attrs, id) {
  return {
    id,
    tipo:           'contribuinte',
    razaoSocial:    attrs.razao_social          || attrs.nome || '—',
    nomeFantasia:   attrs.nome                  || '—',
    cnpj:           attrs.cpf_cnpj             || '—',
    email:          attrs.email                || '—',
    telefone:       attrs.telefone             || attrs.celular || '—',
    site:           '—',
    status:         attrs.ativo ? 'ativo' : 'inativo',
    statusTexto:    attrs.ativo ? 'Ativo' : 'Inativo',
    dataAssociacao: attrs.associado_data_registro || null,
    registro:       attrs.associado_registro   || '—',
    categoria:      attrs.categoria_profissional || '—',
    porte:          '—',
    numEmpregados:  '—',
    logradouro:     attrs.endereco             || '—',
    complemento:    attrs.complemento          || '',
    bairro:         attrs.bairro               || '—',
    cidade:         attrs.cidade               || '—',
    uf:             attrs.uf                   || '—',
    cep:            attrs.cep                  || '—',
  };
}

// ============================================================
// BENEFICIOS - gerenciados localmente (sem endpoint no CRM)
// ============================================================
async function apiGetBeneficios() {
  // Benefícios = produtos cadastrados no banco (destaque primeiro)
  const result = await prodApi('listar', { limit: 100 }, 'GET');
  return (result.produtos || []).map(p => ({
    id:        p.id,
    titulo:    p.nome,
    descricao: p.descricao_curta || '',
    categoria: p.categoria_nome || 'Geral',
    tags:      [],
    link:      p.link_venda_url || '#',
    ctaTexto:  p.link_venda_tipo === 'whatsapp' ? 'Falar no WhatsApp' : 'Saiba mais',
    destaque:  !!p.destaque,
    imagem:    p.imagem || null,
    slug:      p.slug,
    tipo:      p.link_venda_tipo,
  }));
}

// ★ EDITE O CATÁLOGO ABAIXO ★
const CATALOGO_BENEFICIOS = [
  {
    id: '1',
    titulo: 'Plano Odontológico Empresarial',
    descricao: 'Mensalidades acessíveis e carências reduzidas para associados ACIC-DF. Cobertura completa para colaboradores e dependentes, com ampla rede credenciada no DF.',
    categoria: 'Saúde',
    tags: ['saúde', 'colaboradores', 'odonto'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Solicitar proposta',
    destaque: true,
  },
  {
    id: '2',
    titulo: 'Programa Educação Empreendedora',
    descricao: 'Capacitações mensais focadas em vendas, finanças e marketing digital para empresários e equipes. Certificado incluso.',
    categoria: 'Educação',
    tags: ['capacitação', 'cursos', 'marketing'],
    link: 'https://acicdf.org.br/agenda',
    ctaTexto: 'Ver agenda',
    destaque: true,
  },
  {
    id: '3',
    titulo: 'Consultoria Jurídica Empresarial',
    descricao: 'Atendimento jurídico com desconto para associados: trabalhista, tributário, contratos e recuperação de crédito.',
    categoria: 'Jurídico',
    tags: ['jurídico', 'consultoria', 'contratos'],
    link: 'mailto:secretaria@acicdf.org.br',
    ctaTexto: 'Solicitar atendimento',
    destaque: false,
  },
  {
    id: '4',
    titulo: 'Seguro Empresarial',
    descricao: 'Proteção completa para o patrimônio da sua empresa com condições especiais negociadas pela ACIC-DF.',
    categoria: 'Seguros',
    tags: ['seguro', 'proteção', 'patrimônio'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Solicitar cotação',
    destaque: false,
  },
  {
    id: '5',
    titulo: 'Plataforma de Contabilidade Online',
    descricao: 'Gestão financeira e contábil com desconto de até 30% para associados. Notas fiscais, folha de pagamento e DRE.',
    categoria: 'Financeiro',
    tags: ['contabilidade', 'finanças', 'notas fiscais'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Conhecer planos',
    destaque: false,
  },
  {
    id: '6',
    titulo: 'Gestão de Marketing Digital',
    descricao: 'Redes sociais, tráfego pago e criação de conteúdo com valores diferenciados para empresas associadas.',
    categoria: 'Marketing',
    tags: ['marketing', 'redes sociais', 'digital'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Solicitar proposta',
    destaque: true,
  },
  {
    id: '7',
    titulo: 'Plano de Saúde Empresarial',
    descricao: 'Cobertura médica completa com rede credenciada no DF e entorno. Carência e mensalidade especiais para associados.',
    categoria: 'Saúde',
    tags: ['saúde', 'médico', 'colaboradores'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Solicitar proposta',
    destaque: false,
  },
  {
    id: '8',
    titulo: 'Sistema de Gestão de RH e Folha',
    descricao: 'Controle de ponto, folha de pagamento e eSocial com preço reduzido e suporte especializado para associados.',
    categoria: 'Gestão',
    tags: ['RH', 'folha', 'eSocial'],
    link: 'https://wa.me/5561988888888',
    ctaTexto: 'Conhecer solução',
    destaque: false,
  },
];
/**
 * ACIC CONECTA 2.0 — Módulo de Produtos
 * Integra ao portal existente (app.js + api.js)
 */

const PRODUTOS_URL = _baseUrl + '/produtos.php';

// ============================================================
// STATE
// ============================================================
let produtosData      = [];
let categoriasData    = [];
let produtoEditando   = null;
let subprodutoEditando = null;
let sessionId         = null;

// Gera session ID único para tracking
function getSessionId() {
  if (!sessionId) {
    sessionId = sessionStorage.getItem('acic_sid');
    if (!sessionId) {
      sessionId = Math.random().toString(36).slice(2) + Date.now().toString(36);
      sessionStorage.setItem('acic_sid', sessionId);
    }
  }
  return sessionId;
}

// ============================================================
// API PRODUTOS
// ============================================================
async function prodApi(action, body = {}, method = 'POST') {
  const token = getToken();
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (token || '') },
  };
  if (method === 'POST') opts.body = JSON.stringify({ action, ...body });

  const url = method === 'GET'
    ? PRODUTOS_URL + '?action=' + action + '&' + new URLSearchParams(body)
    : PRODUTOS_URL + '?action=' + action;

  const res  = await fetch(url, opts);
  const data = await res.json();
  if (!data.success) throw { status: res.status, message: data.message };
  return data.data;
}

async function trackEvento(produtoId, tipoEvento, subprodutoId = null) {
  try {
    await fetch(PRODUTOS_URL + '?action=tracking', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        produto_id:    produtoId,
        subproduto_id: subprodutoId,
        tipo_evento:   tipoEvento,
        session_id:    getSessionId(),
        referrer:      document.referrer,
      }),
    });
  } catch (_) {} // silencioso
}

// ============================================================
// CATÁLOGO PÚBLICO — seção do portal
// ============================================================
async function loadCatalogoProdutos(filtros = {}) {
  const grid    = document.getElementById('produtos-grid');
  const loading = document.getElementById('produtos-loading');
  if (!grid) return;

  // Mostrar botão "Meu Plano" se usuário logado tem plano
  const _sess = getSession();
  const btnPlano = document.getElementById('btn-filtro-meu-plano');
  if (btnPlano) btnPlano.classList.toggle('hidden', !_sess?.plano);

  loading?.classList.remove('hidden');
  grid.innerHTML = '';

  try {
    const params = { limit: 50, ...filtros };
    const result = await prodApi('listar', params, 'GET');
    produtosData  = result.produtos || [];

    loading?.classList.add('hidden');

    if (!produtosData.length) {
      grid.innerHTML = '<div class="empty-state"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg><p>Nenhum produto encontrado.</p></div>';
      return;
    }

    grid.innerHTML = produtosData.map(p => renderCardProduto(p)).join('');
  } catch (e) {
    loading?.classList.add('hidden');
    grid.innerHTML = '<div class="empty-state"><p>Erro ao carregar produtos.</p></div>';
  }
}

function renderCardProduto(p) {
  // Armazena no cache para lookup seguro por ID
  _prodCache.set(p.id, p);
  const tipoIcon = p.link_venda_tipo === 'whatsapp'
    ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.117.553 4.103 1.523 5.828L0 24l6.338-1.499A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.885 0-3.651-.502-5.178-1.381l-.371-.22-3.765.89.938-3.667-.242-.386A9.944 9.944 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>`
    : `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>`;

  const badgeTipo = { produto:'Produto', servico:'Serviço', beneficio:'Benefício' };

  return `
    <div class="produto-card" onclick="abrirProduto('${p.slug}')">
      <div class="produto-img ${!p.imagem ? 'placeholder' : ''}">
        ${p.imagem
          ? `<img src="${p.imagem}" alt="${p.nome}" loading="lazy"/>`
          : `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`
        }
        <span class="produto-badge-destaque">${p.categoria_nome || 'Geral'}</span>
        ${p.no_meu_plano ? `<span class="produto-badge-combo">${p.combo_incluido ? '✓ Incluído no seu plano' : p.combo_desconto + '% OFF no seu plano'}</span>` : ''}
      </div>
      <div class="produto-body">
        <h3 class="produto-nome">${p.nome}</h3>
        ${p.descricao_curta ? `<p class="produto-desc">${p.descricao_curta}</p>` : ''}
        <div class="produto-footer">
          <span class="produto-empresa">${p.parceiro_logo ? `<img src="${p.parceiro_logo}" alt="" style="width:16px;height:16px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px">` : ''}${p.parceiro_fantasia || p.parceiro_nome || p.associado_nome || ''}</span>
          <button class="produto-cta ${p.link_venda_tipo === 'whatsapp' ? 'cta-whats' : 'cta-ext'}"
                  data-pid="${p.id}" onclick="event.stopPropagation(); ctaProduto(event, ${p.id})">
            ${tipoIcon} ${p.link_venda_tipo === 'whatsapp' ? 'WhatsApp' : 'Saiba mais'}
          </button>
        </div>
      </div>
    </div>
  `;
}

// ============================================================
// MODAL DE PRODUTO
// ============================================================
async function abrirProduto(slug) {
  const modal = document.getElementById('produto-modal');
  if (!modal) return;

  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  document.getElementById('produto-modal-body').innerHTML = '<div class="loading-state"><div class="spinner"></div><span>Carregando...</span></div>';

  try {
    const p = await prodApi('detalhe', { slug }, 'GET');
    trackEvento(p.id, 'view');
    document.getElementById('produto-modal-body').innerHTML = renderModalProduto(p);
  } catch (e) {
    document.getElementById('produto-modal-body').innerHTML = '<div class="empty-state"><p>Produto não encontrado.</p></div>';
  }
}

function renderModalProduto(p) {
  // Armazena produto e subprodutos no cache para ações seguras
  _prodCache.set(p.id, p);
  if (p.subprodutos) p.subprodutos.forEach(s => _prodCache.set('sub_' + s.id, { ...s, _produto: p }));

  const temSub = p.subprodutos && p.subprodutos.length > 0;
  const temGaleria = p.galeria && p.galeria.length > 0;

  const formatPreco = (sub) => {
    if (!sub.preco) return '';
    const tipos = { unico:'único', mensal:'/mês', anual:'/ano', recorrente:'/recorrente' };
    return `<span class="sub-preco">R$ ${parseFloat(sub.preco).toFixed(2).replace('.',',')} <small>${tipos[sub.tipo_cobranca]||''}</small></span>`;
  };

  // Helper: resolve link final (sub herda do produto se não tiver próprio)
  const resolveLink = (s) => {
    const tipo   = s.link_venda_tipo   || p.link_venda_tipo;
    const url    = s.link_venda_url    || p.link_venda_url;
    const numero = s.whatsapp_numero   || p.whatsapp_numero;
    if (tipo === 'whatsapp') return montarLinkWhatsApp(numero, s.nome || p.nome, s.nome ? p.nome : null, p.associado_nome);
    return url || '#';
  };

  const isWhats = (s) => (s.link_venda_tipo || p.link_venda_tipo) === 'whatsapp';

  // Ícone WhatsApp
  const icWhats = `<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.117.553 4.103 1.523 5.828L0 24l6.338-1.499A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.885 0-3.651-.502-5.178-1.381l-.371-.22-3.765.89.938-3.667-.242-.386A9.944 9.944 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>`;
  const icExt  = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>`;

  // Galeria
  const galeriaHtml = temGaleria ? `
    <div class="modal-galeria">
      <div class="galeria-principal">
        <img id="galeria-main-img" src="${p.galeria[0].url}" alt="${p.nome}" class="galeria-main-img"/>
      </div>
      ${p.galeria.length > 1 ? `
      <div class="galeria-thumbs">
        ${p.galeria.map((img, i) => `
          <img src="${img.url}" alt="${p.nome} ${i+1}"
               class="galeria-thumb ${i===0?'active':''}"
               onclick="trocarImagemGaleria(this, '${img.url}')"/>
        `).join('')}
      </div>` : ''}
    </div>
  ` : (p.imagem ? `<div class="modal-galeria"><img src="${p.imagem}" alt="${p.nome}" class="galeria-main-img"/></div>` : '');

  // Link único do produto (sem subprodutos)
  const linkProduto = resolveLink(p);
  const whatsApp = isWhats(p);

  return `
    ${galeriaHtml}
    <div class="modal-produto-header" style="margin-top:${temGaleria||p.imagem?'16px':'0'}">
      <div class="modal-produto-info">
        <div class="modal-tag">${p.categoria_nome || 'Geral'}</div>
        <h2 class="modal-title">${p.nome}</h2>
        ${p.marca ? `<p class="modal-marca">${p.marca}</p>` : ''}
        ${(p.parceiro_nome || p.associado_nome) ? `<p class="modal-empresa">${p.parceiro_logo ? `<img src="${p.parceiro_logo}" alt="" style="width:18px;height:18px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px">` : `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>`} ${p.parceiro_fantasia || p.parceiro_nome || p.associado_nome}</p>` : ''}
      </div>
    </div>

    ${p.descricao ? `<div class="modal-desc">${p.descricao}</div>` : ''}

    ${temSub ? `
      <div class="modal-subprodutos">
        <h4>Planos disponíveis</h4>
        <div class="sub-grid">
          ${p.subprodutos.map(s => {
            const lk = resolveLink(s);
            const isW = isWhats(s);
            return `<a class="sub-card" href="${lk}" target="_blank" rel="noopener"
                       onclick="trackEvento(${p.id}, '${isW ? 'click_whatsapp' : 'click_externo'}', ${s.id})">
              <div class="sub-nome">${s.nome}</div>
              ${s.descricao ? `<div class="sub-desc">${s.descricao}</div>` : ''}
              ${formatPreco(s)}
              <div class="sub-cta ${isW ? 'sub-cta-whats' : 'sub-cta-ext'}">
                ${isW ? icWhats : icExt}
                ${isW ? 'Falar no WhatsApp' : 'Saiba mais'}
              </div>
            </a>`;
          }).join('')}
        </div>
      </div>
    ` : `
      <div class="modal-cta-wrap">
        <a href="${linkProduto}" target="_blank" rel="noopener"
           class="btn-primary modal-btn ${whatsApp ? 'cta-whats' : ''}"
           onclick="trackEvento(${p.id}, '${whatsApp ? 'click_whatsapp' : 'click_externo'}')">
          ${whatsApp ? icWhats : icExt}
          ${whatsApp ? 'Falar no WhatsApp' : 'Acessar oferta'}
        </a>
      </div>
    `}
  `;
}

function ctaProduto(e, prodId) {
  e.preventDefault();
  e.stopPropagation();
  const p = _prodCache.get(prodId);
  if (!p) return;
  const link = p.link_venda_tipo === 'whatsapp'
    ? montarLinkWhatsApp(p.whatsapp_numero, p.nome, null, p.associado_nome)
    : p.link_venda_url;
  if (link && link !== 'null' && link !== '') window.open(link, '_blank');
  trackEvento(prodId, p.link_venda_tipo === 'whatsapp' ? 'click_whatsapp' : 'click_externo', null);
}

function abrirLinkProduto(e, slug, produtoId, subprodutoId, tipo, url, numero, nomeProduto, nomeEmpresa) {
  e.preventDefault();
  e.stopPropagation();
  const link = tipo === 'whatsapp'
    ? montarLinkWhatsApp(numero, nomeProduto, null, nomeEmpresa)
    : url;
  if (link && link !== 'null' && link !== '') window.open(link, '_blank');
  trackEvento(produtoId, tipo === 'whatsapp' ? 'click_whatsapp' : 'click_externo', subprodutoId);
}

function abrirLinkSubproduto(e, produtoId, subId, tipo, url, numero, nomeSubproduto, nomeProduto, nomeEmpresa) {
  e.stopPropagation();
  const eventoTipo = tipo === 'whatsapp' ? 'click_whatsapp' : 'click_externo';
  trackEvento(produtoId, eventoTipo, subId);

  const link = tipo === 'whatsapp'
    ? montarLinkWhatsApp(numero, nomeProduto, nomeSubproduto, nomeEmpresa)
    : url;

  if (link) window.open(link, '_blank');
}

function montarLinkWhatsApp(numero, nomeProduto, nomeSubproduto, nomeEmpresa) {
  if (!numero) return null;
  const num = numero.replace(/\D/g, '');
  let msg = `Olá! Tenho interesse em: *${nomeProduto}*`;
  if (nomeSubproduto) msg += ` — ${nomeSubproduto}`;
  if (nomeEmpresa)    msg += `\nEmpresa: ${nomeEmpresa}`;
  msg += '\n\nVi no portal ACIC Conecta.';
  return `https://wa.me/55${num}?text=${encodeURIComponent(msg)}`;
}

function fecharProdutoModal(e) {
  if (e && e.target !== document.getElementById('produto-modal')) return;
  document.getElementById('produto-modal')?.classList.add('hidden');
  document.body.style.overflow = '';
}

// ============================================================
// FILTROS DO CATÁLOGO
// ============================================================
async function carregarCategoriasFiltro() {
  try {
    categoriasData = await prodApi('categorias', {}, 'GET');
    const wrap = document.getElementById('produtos-filtro-cats');
    if (!wrap) return;
    wrap.innerHTML = `
      <button class="filter-tag active" onclick="filtrarPorCategoria('', this)" style="display:inline-flex;align-items:center;gap:6px">
        <span style="font-size:14px">\u{1F4CB}</span> Todos
      </button>
      ${categoriasData.filter(c => c.total_produtos > 0).map(c =>
        `<button class="filter-tag" onclick="filtrarPorCategoria('${c.slug}', this)" style="display:inline-flex;align-items:center;gap:6px">
          <span style="font-size:14px">${c.icone || '\u{1F4C1}'}</span> ${c.nome} <small style="opacity:.6">(${c.total_produtos})</small>
        </button>`
      ).join('')}
    `;
  } catch (_) {}
}

function filtrarPorCategoria(slug, btn) {
  document.querySelectorAll('#produtos-filtro-cats .filter-tag').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const busca = document.getElementById('produtos-busca')?.value || '';
  loadCatalogoProdutos(slug ? { categoria: slug, busca } : { busca });
}

function buscarProdutos() {
  const busca     = document.getElementById('produtos-busca')?.value || '';
  const catAtiva  = document.querySelector('#produtos-filtro-cats .filter-tag.active');
  const catSlug   = catAtiva?.dataset?.slug || '';
  loadCatalogoProdutos({ busca, ...(catSlug ? { categoria: catSlug } : {}) });
}

let _filtroMeuPlano = false;

function toggleFiltroMeuPlano() {
  _filtroMeuPlano = !_filtroMeuPlano;
  const btn = document.getElementById('btn-filtro-meu-plano');
  if (btn) btn.classList.toggle('active', _filtroMeuPlano);
  const busca = document.getElementById('produtos-busca')?.value || '';
  const catAtiva = document.querySelector('#produtos-filtro-cats .filter-tag.active');
  const catSlug = catAtiva?.dataset?.slug || '';
  const filtros = { busca };
  if (catSlug) filtros.categoria = catSlug;
  if (_filtroMeuPlano) filtros.meu_plano = '1';
  loadCatalogoProdutos(filtros);
}

// ============================================================
// PAINEL ADMIN — Gerenciar Produtos
// ============================================================
async function loadAdminProdutos() {
  const tbody = document.getElementById('admin-produtos-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text3);padding:20px">Carregando...</td></tr>';

  try {
    const lista = await prodApi('admin_listar', {}, 'GET');
    if (!lista.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text3);padding:20px">Nenhum produto cadastrado.</td></tr>';
      return;
    }
    tbody.innerHTML = lista.map(p => `
      <tr>
        <td><strong>${p.nome}</strong><br><small style="color:var(--text3)">${p.slug}</small></td>
        <td>${p.categoria_nome || '—'}</td>
        <td><span class="badge ${p.tipo}">${p.tipo}</span></td>
        <td><span class="badge ${p.status === 'ativo' ? 'active' : p.status === 'pendente' ? 'pending' : 'inactive'}">${p.status}</span></td>
        <td style="text-align:center">${p.views}</td>
        <td style="text-align:center">${p.clicks}</td>
        <td>
          <button class="btn-table-action" onclick="editarProduto(${p.id})">Editar</button>
          <button class="btn-table-action danger" onclick="confirmarExcluir(${p.id}, '${p.nome}')">Excluir</button>
        </td>
      </tr>
    `).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:20px">${e.message}</td></tr>`;
  }
}

async function editarProduto(id) {
  try {
    const p = await prodApi('detalhe', { id }, 'GET');
    produtoEditando = p;
    abrirFormProduto(p);
  } catch (e) { notify.erro(e.message, 'Erro ao carregar produto'); }
}

function abrirFormProduto(p = null) {
  document.getElementById('form-produto-modal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  // Inicializa editor Quill se ainda não foi inicializado
  if (typeof Quill !== 'undefined' && !window._quillEditor) {
    window._quillEditor = new Quill('#fp-descricao-editor', {
      theme: 'snow',
      placeholder: 'Detalhes do produto/serviço...',
      modules: { toolbar: [
        [{'header':[2,3,false]}],
        ['bold','italic','underline','strike'],
        [{'color':[]},{'background':[]}],
        [{'list':'ordered'},{'list':'bullet'}],
        [{'align':[]}],
        ['link','image','blockquote','code-block'],
        ['clean']
      ]}
    });
    window._quillEditor.on('text-change', () => {
      const ta = document.getElementById('fp-descricao');
      if (ta) ta.value = window._quillEditor.root.innerHTML;
    });
  }

  document.getElementById('form-produto-titulo').textContent = p ? 'Editar Produto' : 'Novo Produto';
  document.getElementById('fp-id').value              = p?.id || '';
  // Galeria unificada
  const galeriaWrap = document.getElementById('admin-galeria-wrap');
  if (galeriaWrap) {
    if (p?.id) {
      galeriaWrap.style.display = 'grid';
      // Remove aviso de "salve primeiro" se existir
      const aviso = document.getElementById('galeria-novo-aviso');
      if (aviso) aviso.remove();
      carregarGaleriaAdmin(p.id);
    } else {
      galeriaWrap.style.display = 'grid';
      atualizarPreviewCapa(null);
      document.getElementById('fp-imagem').value = '';
      // Mostrar aviso que imagens são salvas após criar o produto
      const grid = document.getElementById('admin-galeria-grid');
      if (grid) grid.innerHTML = '<div id="galeria-novo-aviso" style="grid-column:1/-1;padding:12px;background:var(--accent-soft);border:1px solid var(--accent-border);border-radius:8px;font-size:12px;color:var(--accent)">💡 Salve o produto primeiro para adicionar imagens.</div>';
    }
  }
  document.getElementById('fp-nome').value            = p?.nome || '';
  document.getElementById('fp-descricao-curta').value = p?.descricao_curta || '';
  document.getElementById('fp-descricao').value       = p?.descricao || '';
  // Popular Quill com conteúdo existente
  if (window._quillEditor) {
    window._quillEditor.root.innerHTML = p?.descricao || '';
  }
  document.getElementById('fp-tipo').value            = p?.tipo || 'produto';
  document.getElementById('fp-status').value          = p?.status || 'pendente';
  document.getElementById('fp-marca').value           = p?.marca || '';
  // Popular select de parceiros
  popularSelectParceiros(p?.parceiro_id || '');
  // categoria é setada dentro de atualizarSelectCategorias após carregar as opções
  document.getElementById('fp-imagem').value          = p?.imagem || '';
  document.getElementById('fp-destaque').checked      = !!p?.destaque;
  document.getElementById('fp-link-tipo').value       = p?.link_venda_tipo || '';
  document.getElementById('fp-link-url').value        = p?.link_venda_url || '';
  document.getElementById('fp-whatsapp').value        = p?.whatsapp_numero || '';
  document.getElementById('fp-associado-id').value    = p?.associado_id || '';
  document.getElementById('fp-associado-nome').value  = p?.associado_nome || '';

  toggleLinkFields();
  atualizarSelectCategorias(p?.categoria_id || '');

  // Subprodutos — mostra seção apenas ao editar produto existente
  const subWrap = document.getElementById('sub-admin-wrap');
  if (subWrap) {
    if (p?.id) {
      subWrap.style.display = 'block';
      renderSubprodutosAdmin(p.subprodutos || [], p.id);
    } else {
      subWrap.style.display = 'none';
    }
  }
}

function fecharFormProduto() {
  document.getElementById('form-produto-modal').classList.add('hidden');
  document.body.style.overflow = '';
  produtoEditando = null;
}

function toggleLinkFields() {
  const tipo = document.getElementById('fp-link-tipo').value;
  document.getElementById('fp-wrap-url').classList.toggle('hidden', tipo !== 'externo');
  document.getElementById('fp-wrap-whats').classList.toggle('hidden', tipo !== 'whatsapp');
}

async function atualizarSelectCategorias(categoriaId) {
  const sel = document.getElementById('fp-categoria');
  if (!sel) return;
  if (!categoriasData.length) {
    try { categoriasData = await prodApi('categorias', {}, 'GET'); } catch (_) {}
  }
  // Usa o valor passado como parâmetro OU o atual do select
  const atual = categoriaId !== undefined ? categoriaId : sel.value;
  sel.innerHTML = '<option value="">Selecione a categoria</option>' +
    categoriasData.map(c => `<option value="${c.id}" ${c.id == atual ? 'selected' : ''}>${c.nome}</option>`).join('');
  // Garante que o valor está selecionado após popular
  if (atual) sel.value = atual;
}

async function buscarAssociado() {
  const q = document.getElementById('fp-associado-busca').value.trim();
  if (q.length < 2) { notify.aviso('Digite ao menos 2 caracteres.'); return; }
  const res = document.getElementById('fp-associado-result');
  res.innerHTML = '<option value="">Buscando...</option>';

  try {
    const lista = await prodApi('buscar_associado', { q }, 'GET');
    res.innerHTML = '<option value="">Selecione</option>' +
      lista.map(a => `<option value="${a.id}|${a.razao_social}">${a.razao_social} — ${a.cnpj}</option>`).join('');
    res.classList.remove('hidden');
    res.onchange = () => {
      const [id, nome] = res.value.split('|');
      document.getElementById('fp-associado-id').value   = id;
      document.getElementById('fp-associado-nome').value = nome;
      res.classList.add('hidden');
    };
  } catch (e) {
    res.innerHTML = '<option value="">Erro na busca</option>';
  }
}

async function salvarProduto(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-salvar-produto');

  // Validação frontend
  const nome     = document.getElementById('fp-nome').value.trim();
  const linkTipo = document.getElementById('fp-link-tipo').value;
  const linkUrl  = document.getElementById('fp-link-url')?.value.trim();
  const whats    = document.getElementById('fp-whatsapp')?.value.trim();

  if (!nome) {
    mostrarToast('⚠️ Campo obrigatório', 'Informe o nome do produto.', 'aviso');
    document.getElementById('fp-nome').focus();
    return;
  }
  if (!linkTipo) {
    mostrarToast('⚠️ Campo obrigatório', 'Selecione o tipo de link de venda.', 'aviso');
    document.getElementById('fp-link-tipo').focus();
    return;
  }
  if (linkTipo === 'externo' && !linkUrl) {
    mostrarToast('⚠️ Campo obrigatório', 'Informe a URL de venda.', 'aviso');
    document.getElementById('fp-link-url').focus();
    return;
  }
  if (linkTipo === 'whatsapp' && !whats) {
    mostrarToast('⚠️ Campo obrigatório', 'Informe o número do WhatsApp.', 'aviso');
    document.getElementById('fp-whatsapp').focus();
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Salvando...';

  // Sincroniza Quill → textarea se editor estiver ativo
  if (window._quillEditor) {
    const ta = document.getElementById('fp-descricao');
    if (ta) ta.value = window._quillEditor.root.innerHTML;
  }

  const body = {
    id:              document.getElementById('fp-id').value || undefined,
    nome,
    descricao_curta: document.getElementById('fp-descricao-curta').value,
    descricao:       document.getElementById('fp-descricao').value,
    tipo:            document.getElementById('fp-tipo').value || 'produto',
    status:          document.getElementById('fp-status').value || 'ativo',
    categoria_id:    document.getElementById('fp-categoria').value || null,
    marca:           document.getElementById('fp-marca').value,
    parceiro_id:     document.getElementById('fp-parceiro').value || null,
    imagem:          document.getElementById('fp-imagem').value,
    destaque:        document.getElementById('fp-destaque').checked ? 1 : 0,
    link_venda_tipo: linkTipo,
    link_venda_url:  linkUrl || null,
    whatsapp_numero: whats || null,
    associado_id:    document.getElementById('fp-associado-id').value,
    associado_nome:  document.getElementById('fp-associado-nome').value,
    token:           getToken(),
  };

  try {
    const action = body.id ? 'editar' : 'criar';
    const result = await prodApi(action, body);
    mostrarToast('✅ Salvo', `Produto "${nome}" salvo com sucesso.`, 'sucesso');
    loadAdminProdutos();
    // Se criou novo produto, reabre em modo edição para adicionar imagens/subprodutos
    if (!body.id && result?.id) {
      const produtoCompleto = await prodApi('detalhe', { slug: result.slug || result.id }, 'GET');
      produtoEditando = produtoCompleto;
      abrirFormProduto(produtoCompleto);
      mostrarToast('💡 Dica', 'Agora você pode adicionar imagens e planos ao produto.', 'info');
    } else {
      fecharFormProduto();
    }
  } catch (err) {
    mostrarToast('❌ Erro ao salvar', err.message, 'alerta');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Salvar produto';
  }
}

async function confirmarExcluir(id, nome) {
  if (!confirm(`Excluir "${nome}"?\nEsta ação não pode ser desfeita.`)) return;
  try {
    await prodApi('excluir', { id, token: getToken() });
    mostrarToast('✅ Produto excluído', `"${nome}" foi removido do catálogo.`, 'sucesso');
    loadAdminProdutos();
  } catch (e) { mostrarToast('❌ Erro', e.message, 'alerta'); }
}

// SUBPRODUTOS no admin
function renderSubprodutosAdmin(subs, produtoId) {
  const wrap = document.getElementById('sub-lista');
  if (!wrap) return;

  wrap.innerHTML = subs.length
    ? subs.map(s => `
        <div class="sub-item">
          <div>
            <strong>${s.nome}</strong>
            ${s.preco ? ` — R$ ${parseFloat(s.preco).toFixed(2).replace('.',',')}` : ''}
            <span class="badge ${s.status === 'ativo' ? 'active' : 'inactive'}">${s.status}</span>
          </div>
          <div>
            <button class="btn-table-action" onclick="abrirFormSub(${JSON.stringify(s).replace(/"/g,'&quot;')})">Editar</button>
            <button class="btn-table-action danger" onclick="excluirSub(${s.id}, ${produtoId})">Remover</button>
          </div>
        </div>
      `).join('')
    : '<p style="color:var(--text3);font-size:13px">Nenhum subproduto cadastrado.</p>';

  document.getElementById('btn-novo-sub').onclick = () => abrirFormSub(null, produtoId);
}

function abrirFormSub(sub, produtoId) {
  if (typeof sub === 'string') sub = JSON.parse(sub);
  subprodutoEditando = sub;
  document.getElementById('fsub-modal').classList.remove('hidden');
  document.getElementById('fsub-id').value          = sub?.id || '';
  document.getElementById('fsub-produto-id').value  = sub?.produto_id || produtoId || produtoEditando?.id || '';
  document.getElementById('fsub-nome').value         = sub?.nome || '';
  document.getElementById('fsub-descricao').value    = sub?.descricao || '';
  document.getElementById('fsub-preco').value        = sub?.preco || '';
  document.getElementById('fsub-cobranca').value     = sub?.tipo_cobranca || 'unico';
  document.getElementById('fsub-status').value       = sub?.status || 'ativo';
  document.getElementById('fsub-link-tipo').value    = sub?.link_venda_tipo || '';
  document.getElementById('fsub-link-url').value     = sub?.link_venda_url || '';
  document.getElementById('fsub-whatsapp').value     = sub?.whatsapp_numero || '';
  document.getElementById('fsub-ordem').value        = sub?.ordem || 0;
}

function fecharFormSub() {
  document.getElementById('fsub-modal').classList.add('hidden');
  subprodutoEditando = null;
}

async function salvarSub(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-salvar-sub');
  btn.disabled = true;

  const body = {
    id:             document.getElementById('fsub-id').value || undefined,
    produto_id:     document.getElementById('fsub-produto-id').value,
    nome:           document.getElementById('fsub-nome').value,
    descricao:      document.getElementById('fsub-descricao').value,
    preco:          document.getElementById('fsub-preco').value || null,
    tipo_cobranca:  document.getElementById('fsub-cobranca').value,
    status:         document.getElementById('fsub-status').value,
    link_venda_tipo:document.getElementById('fsub-link-tipo').value || null,
    link_venda_url: document.getElementById('fsub-link-url').value || null,
    whatsapp_numero:document.getElementById('fsub-whatsapp').value || null,
    ordem:          document.getElementById('fsub-ordem').value || 0,
    token:          getToken(),
  };

  try {
    const action = body.id ? 'sub_editar' : 'sub_criar';
    await prodApi(action, body);
    fecharFormSub();
    // Recarrega subprodutos
    const pidAtual = produtoEditando?.id || body.produto_id;
    if (pidAtual) {
      const p = await prodApi('detalhe', { id: pidAtual }, 'GET');
      renderSubprodutosAdmin(p.subprodutos || [], pidAtual);
      if (produtoEditando) produtoEditando = p;
    }
  } catch (e) {
    notify.erro(e.message);
  } finally {
    btn.disabled = false;
  }
}

async function excluirSub(id, produtoIdFallback) {
  if (!confirm('Remover este subproduto?')) return;
  const pid = produtoEditando?.id || produtoIdFallback;
  if (!pid) { notify.erro('Produto não identificado.'); return; }
  try {
    await prodApi('sub_excluir', { id, token: getToken() });
    const p = await prodApi('detalhe', { id: pid }, 'GET');
    renderSubprodutosAdmin(p.subprodutos || [], pid);
    if (produtoEditando) produtoEditando = p;
  } catch (e) { notify.erro(e.message || 'Erro desconhecido', 'Erro ao remover'); }
}
/**
 * ACIC CONECTA — Lógica do Portal
 */

// ============================================================
// STATE
// ============================================================
let currentSection   = 'dashboard';
let associadoData    = null;
let beneficiosData   = [];
let activeTag        = 'todos';
let docAtual         = '';   // CPF/CNPJ que está sendo logado

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);

  // SSO via URL: ?sso=TOKEN
  const ssoParam = params.get('sso');
  if (ssoParam) {
    try {
      const data = await apiValidateSso(ssoParam);
      history.replaceState({}, '', window.location.pathname);
      aplicarPermissoes(getSession());
      showPortal();
      if (data.primeiro_acesso) {
        mostrarToast('Bem-vindo ao portal!', 'Você foi convidado por ' + (data.nome || 'sua empresa') + '.', 'sucesso');
      }
      return;
    } catch(e) {
      history.replaceState({}, '', window.location.pathname);
      notify.erro('Link de acesso expirado. Faça login novamente.');
      showLogin();
      return;
    }
  }

  // Convite via URL: ?convite=TOKEN
  const conviteParam = params.get('convite');
  if (conviteParam) {
    history.replaceState({}, '', window.location.pathname);
    mostrarTelaConvite(conviteParam);
    return;
  }

  // SSO via cookie (setado pelo CRM ao logar superadmin)
  const ssoCookie = document.cookie.split(';').map(c => c.trim()).find(c => c.startsWith('conecta_sso='));
  if (ssoCookie && !getToken()) {
    const ssoToken = ssoCookie.split('=')[1];
    if (ssoToken) {
      try {
        const ssoData = await apiValidateSso(ssoToken);
        aplicarPermissoes(getSession());
        showPortal();
        return;
      } catch(e) {
        // Cookie invalido, limpar e seguir para login normal
        document.cookie = 'conecta_sso=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.acicdf.org.br';
      }
    }
  }

  // Normal: verifica token existente
  const token = getToken();
  if (token) {
    try {
      const res  = await fetch(AUTH_URL + '?action=validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await res.json();
      if (data.success) {
        // Atualiza sessão com dados do validate (tipo, permissoes)
        if (data.data?.tipo) {
          const sess = getSession() || {};
          sess.tipo = data.data.tipo;
          sess.nome = data.data.nome || sess.nome;
          sess.is_admin = data.data.is_admin || sess.is_admin;
          sess.is_superadmin = data.data.is_superadmin || sess.is_superadmin;
          if (data.data.permissoes) sess.permissoes = data.data.permissoes;
          setSession(sess);
        }
        aplicarPermissoes(getSession());
        showPortal();
      } else {
        clearToken();
        showLogin();
      }
    } catch(e) {
      clearToken();
      showLogin();
    }
  } else {
    showLogin();
  }

  // Força de senha em tempo real
  const novaSenha = document.getElementById('nova-senha');
  if (novaSenha) novaSenha.addEventListener('input', atualizarForcaSenha);
});

// Tela de aceitar convite
function mostrarTelaConvite(conviteToken) {
  document.getElementById('login-screen').classList.add('active');
  document.getElementById('portal-screen').classList.remove('active');

  // Esconder forms de login, mostrar form de convite
  document.getElementById('step-doc')?.classList.add('hidden');
  document.getElementById('step-senha')?.classList.add('hidden');
  document.getElementById('step-novo')?.classList.add('hidden');
  hideError();

  const title = document.getElementById('login-title');
  const desc = document.getElementById('login-desc');
  if (title) title.textContent = 'Criar sua senha';
  if (desc) desc.textContent = 'Você foi convidado para o Portal do Associado ACIC-DF. Defina sua senha de acesso.';

  // Criar form de convite dinamicamente
  let form = document.getElementById('step-convite');
  if (!form) {
    form = document.createElement('form');
    form.id = 'step-convite';
    form.className = 'login-form';
    form.innerHTML = `
      <div class="field">
        <label>Nova senha <small style="color:var(--text3)">(mín. 8 caracteres)</small></label>
        <div class="input-wrap">
          <input type="password" id="convite-senha" placeholder="••••••••" required minlength="8"/>
          <button type="button" class="toggle-pw" onclick="togglePw('convite-senha')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
      <div class="field">
        <label>Confirmar senha</label>
        <div class="input-wrap">
          <input type="password" id="convite-confirmar" placeholder="••••••••" required minlength="8"/>
        </div>
      </div>
      <button type="submit" class="btn-primary" id="btn-convite">
        <span class="btn-text">Criar senha e acessar</span>
        <span class="btn-loader hidden"></span>
      </button>
    `;
    document.querySelector('.login-card')?.appendChild(form);
  }
  form.classList.remove('hidden');

  form.onsubmit = async (e) => {
    e.preventDefault();
    const senha = document.getElementById('convite-senha').value;
    const confirmar = document.getElementById('convite-confirmar').value;
    if (senha !== confirmar) { notify.aviso('As senhas não conferem.'); return; }
    if (senha.length < 8) { notify.aviso('Senha mínima: 8 caracteres.'); return; }

    const btn = document.getElementById('btn-convite');
    btn.querySelector('.btn-text').textContent = 'Criando...';
    btn.querySelector('.btn-loader').classList.remove('hidden');
    btn.disabled = true;

    try {
      await apiAceitarConvite(conviteToken, senha, confirmar);
      aplicarPermissoes(getSession());
      showPortal();
      mostrarToast('Bem-vindo!', 'Sua senha foi criada. Aproveite o portal!', 'sucesso');
    } catch(err) {
      notify.erro(err.message || 'Erro ao aceitar convite.');
    } finally {
      btn.querySelector('.btn-text').textContent = 'Criar senha e acessar';
      btn.querySelector('.btn-loader').classList.add('hidden');
      btn.disabled = false;
    }
  };
}

// ============================================================
// PERSISTÊNCIA DE NAVEGAÇÃO — restaura seção após refresh
// ============================================================
function _restaurarSecao() {
  // Priorizar hash da URL (navegacao vinda de sub-paginas)
  const hash = window.location.hash.replace("#", "");
  const _adminSecs2 = ["admin-produtos","admin-metricas","admin-parceiros","admin-comunicados","admin-categorias"];
  if (hash && _adminSecs2.includes(hash)) {
    const _s = getSession();
    if (_s?.is_admin) mostrarNavAdmin(_s?.is_superadmin);
  }
  if (hash && document.getElementById("section-" + hash)) {
    showSection(hash);
    window.location.hash = "";
    return;
  }
  const ultima = sessionStorage.getItem('acic_last_section');
  if (!ultima || ultima === 'dashboard') return;

  // Verifica se a seção existe no DOM
  const el = document.getElementById('section-' + ultima);
  if (!el) return;

  // Seções admin só restauram se usuário for admin
  const adminSecs = ['admin-produtos','admin-metricas','admin-comunicados'];
  if (adminSecs.includes(ultima)) {
    const sess = getSession();
    if (!sess?.is_admin) return;
  }

  showSection(ultima);
}

// ============================================================
// SCREEN TRANSITIONS
// ============================================================



function showPerfilRestrito(role) {
  // Colaborador: Dashboard, Catalogo, Carteirinha
  // Dependente: Dashboard, Catalogo (SEM carteirinha)
  const allowed = role === 'dependente'
    ? ['nav-catalogo']
    : ['nav-catalogo','nav-carteirinha'];
  // Ocultar sidebar items nao permitidos (exceto Dashboard que e botao separado)
  document.querySelectorAll('.sidebar-nav [id^="nav-"]').forEach(el => {
    if (el.id && !allowed.includes(el.id)) el.style.display = 'none';
    else el.style.display = '';
  });
  // Mostrar Metricas para todos
  const navMet = document.getElementById('nav-metricas');
  // Ocultar cobrancas e empresa para colaborador/dependente
  document.querySelectorAll('.sidebar-nav button, .sidebar-nav a').forEach(el => {
    const onclick = el.getAttribute('onclick') || '';
    if (onclick.includes("'cobrancas'") || onclick.includes("'empresa'")) {
      if (role === 'colaborador' || role === 'dependente') el.style.display = 'none';
    }
  });
}
function showPerfilEmpresa() {
  // Empresa: Dashboard, Catalogo, Minha Empresa, Minhas Cobrancas, Carteirinha, Metricas
  // Ocultar abas admin-only
  ['nav-comunicados','nav-admin','nav-parceiros','nav-categorias'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  // Mostrar Metricas (hidden por default)
  const navMet = document.getElementById('nav-metricas');
}
function showColaboradorMenu() { showPerfilRestrito('colaborador'); }
function showGestorMenu() {
  // Gestor v apenas: Comunicados, Produtos, Parceiros, Mtricas
  const allowed = ['nav-comunicados','nav-produtos','nav-parceiros','nav-metricas'];
  const all_nav = document.querySelectorAll('[id^="nav-"]');
  all_nav.forEach(el => {
    if (!allowed.includes(el.id)) el.style.display = 'none';
    else el.style.display = '';
  });
}
function hideAuthLoading() {
  document.documentElement.classList.remove('has-token');
}
function showLogin() {
  hideAuthLoading();
  document.getElementById('login-screen').classList.add('active');
  document.getElementById('portal-screen').classList.remove('active');
}

function showPortal() {
  hideAuthLoading();
  // Definir role a partir da sessao
  const _sessRole = getSession();
  window._userRole = _sessRole?.role || _sessRole?.tipo || '';
  if (_userRole === 'gestor') showGestorMenu();
  if (_userRole === 'colaborador' || _userRole === 'dependente') showPerfilRestrito(_userRole);
  _adminChecked = false;
  // Admin items: mostrar imediatamente se session tem is_admin
  const _sess = getSession();
  if (_sess?.is_admin) {
    mostrarNavAdmin(_sess?.is_superadmin);
    _adminChecked = true;
  } else {
    const _hideForNonAdmin = _userRole === 'associado_empresa'
      ? ['nav-admin','nav-comunicados','nav-parceiros','nav-categorias']
      : ['nav-admin','nav-metricas','nav-comunicados','nav-parceiros','nav-categorias'];
    _hideForNonAdmin.forEach(id => {
      document.getElementById(id)?.classList.add('hidden');
    });
    // Empresa: garantir que metricas fica visivel
    if (_userRole === 'associado_empresa') {
      const navMet = document.getElementById('nav-metricas');
    }
  }
  // Sidebar dinamico baseado em permissoes
  let _modulos = getSession()?.modulos || [];
  if (_modulos.length > 0) {
    aplicarModulosSidebar(_modulos);
  } else if (getToken()) {
    // Sem modulos na sessao — buscar do CRM
    fetch(_baseUrl + '/auth.php?action=permissoes', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: getToken() })
    }).then(r => r.json()).then(j => {
      if (j.success && j.data?.modulos) {
        const s = getSession() || {};
        s.modulos = j.data.modulos;
        setSession(s);
        aplicarModulosSidebar(j.data.modulos);
      }
    }).catch(() => {});
  }
  if (window._userRole === 'associado_empresa') {
    showPerfilEmpresa();
  }
  const btnLink = document.getElementById('btn-novo-link');
  if (btnLink) btnLink.style.display = 'none';
  const tag = document.getElementById('topbar-tag');
  if (tag) tag.style.display = 'none';

  // Esconder form convite se existir
  document.getElementById('step-convite')?.classList.add('hidden');

  document.getElementById('login-screen').classList.remove('active');
  _restaurarSecao();
  document.getElementById('portal-screen').classList.add('active');

  // Aplicar permissões por perfil
  aplicarPermissoes(getSession());

  loadPortalData();
  iniciarNotificacoes();
  renderVersao();
  // Restaura seção para usuários não-admin (sem aguardar checkAdmin)
  // _restaurarSecao movido para cima (sem flash)
}

// ============================================================
// LOGIN — ETAPA 1: verificar CPF/CNPJ
// ============================================================
// ============================================================
// LOGIN POR EMAIL — Fluxo unificado
// ============================================================
let _loginEmail = '';

function toggleLoginMode(e) {
  e.preventDefault();
  const emailForm = document.getElementById('step-email');
  const docForm = document.getElementById('step-doc');
  const emailSenha = document.getElementById('step-email-senha');
  const destino = document.getElementById('step-destino');
  hideError();

  if (emailForm && !emailForm.classList.contains('hidden')) {
    // Switch to CPF/CNPJ mode
    emailForm.classList.add('hidden');
    emailSenha?.classList.add('hidden');
    destino?.classList.add('hidden');
    docForm?.classList.remove('hidden');
    document.getElementById('login-desc').textContent = 'Acesse com seu CPF ou CNPJ cadastrado na ACIC-DF.';
  } else {
    // Switch to email mode
    docForm?.classList.add('hidden');
    document.getElementById('step-senha')?.classList.add('hidden');
    document.getElementById('step-novo')?.classList.add('hidden');
    emailSenha?.classList.add('hidden');
    destino?.classList.add('hidden');
    emailForm?.classList.remove('hidden');
    document.getElementById('login-desc').textContent = 'Acesse com seu email cadastrado na ACIC-DF.';
  }
  document.getElementById('login-title').textContent = 'Bem-vindo';
}

async function handleCheckEmail(e) {
  e.preventDefault();
  const email = document.getElementById('login-email').value.trim();
  const btn = document.getElementById('btn-check-email');

  setLoading(btn, true);
  hideError();

  try {
    const result = await apiCheckTipo(email);
    _loginEmail = email;

    const pill = document.getElementById('email-display');
    if (pill) pill.textContent = email;

    const context = document.getElementById('email-context');
    const labels = { superadmin:'Super Admin', gestor:'Gestor', associado_empresa:'Associado', colaborador:'Colaborador', dependente:'Dependente' };
    if (context) {
      const tipo = result.tipo || 'associado_empresa';
      const nome = result.nome || '';
      if (tipo === 'colaborador' || tipo === 'dependente') {
        context.textContent = 'Bem-vindo ao Portal do Associado';
      } else if (nome) {
        context.textContent = 'Bem-vindo, ' + nome.split(' ')[0];
      } else {
        context.textContent = '';
      }
    }

    document.getElementById('login-title').textContent = 'Entrar';
    document.getElementById('login-desc').textContent = 'Digite sua senha para continuar.';

    // Esconder email form, mostrar senha
    document.getElementById('step-email').classList.add('hidden');
    document.getElementById('step-email-senha').classList.remove('hidden');

  } catch (err) {
    if (err.status === 404) {
      showError('Email não encontrado. Verifique ou use CPF/CNPJ.');
    } else {
      showError(err.message || 'Erro ao verificar email.');
    }
  } finally {
    setLoading(btn, false);
  }
}

async function handleLoginEmail(e) {
  e.preventDefault();
  const senha = document.getElementById('login-email-pass').value;
  const btn = document.getElementById('btn-login-email');

  setLoading(btn, true);
  hideError();

  try {
    const data = await apiLoginEmail(_loginEmail, senha);

    if (data.destino === 'ambos') {
      // Mostrar escolha de destino
      document.getElementById('step-email-senha').classList.add('hidden');
      document.getElementById('step-destino').classList.remove('hidden');
      document.getElementById('destino-nome').textContent = data.nome || '';
      document.getElementById('login-title').textContent = 'Escolha seu destino';
      document.getElementById('login-desc').textContent = '';
      // Salvar SSO token e redirect URLs na sessão para uso posterior
      window._ssoData = data;
    } else {
      // Direto para o portal
      aplicarPermissoes(getSession());
      showPortal();
    }
  } catch (err) {
    showError(err.message || 'Credenciais inválidas.');
  } finally {
    setLoading(btn, false);
  }
}

function irDestino(destino) {
  const data = window._ssoData || getSession() || {};
  if (destino === 'crm' && data.redirect_crm) {
    window.location.href = data.redirect_crm;
  } else {
    // Conecta: já tem sessão local, apenas entrar
    aplicarPermissoes(getSession());
    showPortal();
  }
}

function voltarEmail() {
  document.getElementById('step-email-senha').classList.add('hidden');
  document.getElementById('step-destino')?.classList.add('hidden');
  document.getElementById('step-email').classList.remove('hidden');
  document.getElementById('login-title').textContent = 'Bem-vindo';
  document.getElementById('login-desc').textContent = 'Acesse com seu email cadastrado na ACIC-DF.';
  hideError();
}

// ============================================================
// LOGIN POR CPF/CNPJ — Fluxo legado (mantido)
// ============================================================
async function handleCheckDoc(e) {
  e.preventDefault();
  const doc = document.getElementById('login-doc').value.trim();
  const btn = document.getElementById('btn-check');

  setLoading(btn, true);
  hideError();

  try {
    const result = await apiCheck(doc);
    docAtual = doc;

    // Preenche o "pill" com o CPF/CNPJ formatado
    const pill = document.getElementById('doc-display');
    const pillNovo = document.getElementById('doc-display-novo');
    if (pill)    pill.textContent = doc;
    if (pillNovo) pillNovo.textContent = doc;

    if (result.primeiro_acesso) {
      // Primeiro acesso — vai para tela de definir senha
      document.getElementById('login-title').textContent = 'Primeiro acesso';
      document.getElementById('login-desc').textContent =
        result.nome
          ? `Olá, ${result.nome.split(' ')[0]}! Defina sua senha para continuar.`
          : 'Defina sua senha para acessar o portal.';
      showStep('step-novo');
    } else {
      // Já tem senha — vai para tela de login
      document.getElementById('login-title').textContent = 'Bem-vindo de volta';
      document.getElementById('login-desc').textContent = 'Digite sua senha para entrar.';
      showStep('step-senha');
    }

  } catch (err) {
    showError(err.message || 'CPF/CNPJ não encontrado na ACIC-DF.');
  } finally {
    setLoading(btn, false);
  }
}

// ============================================================
// LOGIN — ETAPA 2a: login normal
// ============================================================
async function handleLogin(e) {
  e.preventDefault();
  const senha = document.getElementById('login-pass').value;
  const btn   = document.getElementById('btn-login');

  setLoading(btn, true);
  hideError();

  try {
    await apiLogin(docAtual, senha);
    showPortal();
  } catch (err) {
    // Flag especial: volta para primeiro acesso
    if (err.message === 'primeiro_acesso') {
      showStep('step-novo');
      return;
    }
    showError(err.message || 'Senha incorreta.');
  } finally {
    setLoading(btn, false);
  }
}

// ============================================================
// LOGIN — ETAPA 2b: primeiro acesso
// ============================================================
async function handlePrimeiroAcesso(e) {
  e.preventDefault();
  const senha = document.getElementById('nova-senha').value;
  const conf  = document.getElementById('conf-senha').value;
  const btn   = document.getElementById('btn-novo');

  if (senha !== conf) {
    showError('As senhas não conferem.');
    return;
  }
  if (senha.length < 8) {
    showError('A senha deve ter no mínimo 8 caracteres.');
    return;
  }

  setLoading(btn, true);
  hideError();

  try {
    await apiPrimeiroAcesso(docAtual, senha, conf);
    showPortal();
  } catch (err) {
    showError(err.message || 'Erro ao criar senha.');
  } finally {
    setLoading(btn, false);
  }
}

// ============================================================
// LOGOUT
// ============================================================
function handleLogout() {
  pararNotificacoes();
  apiLogout();
  // Reset telas
  showStep('step-doc');
  document.getElementById('login-email').value = '';
  document.getElementById('login-doc').value = '';
  document.getElementById('login-title').textContent = 'Bem-vindo';
  document.getElementById('login-desc').textContent = 'Acesse com seu email ou CPF/CNPJ cadastrado na ACIC-DF.';
  hideError();
  docAtual = '';
  _loginEmail = '';
  window._ssoData = null;
  showLogin();
}

// ============================================================
// HELPERS DE LOGIN
// ============================================================
function showStep(stepId) {
  ['step-doc','step-senha','step-novo','step-email','step-email-senha','step-destino','step-convite'].forEach(id => {
    document.getElementById(id)?.classList.add('hidden');
  });
  document.getElementById(stepId)?.classList.remove('hidden');
}

function voltarEtapa() {
  document.getElementById('login-title').textContent = 'Bem-vindo';
  document.getElementById('login-desc').textContent = 'Acesse com seu CPF ou CNPJ cadastrado na ACIC-DF.';
  showStep('step-doc');
  hideError();
}

function showError(msg) {
  const el = document.getElementById('login-error');
  document.getElementById('login-error-msg').textContent = msg;
  el.classList.remove('hidden');
}

function hideError() {
  document.getElementById('login-error')?.classList.add('hidden');
}

function setLoading(btn, loading) {
  btn.querySelector('.btn-text').classList.toggle('hidden', loading);
  btn.querySelector('.btn-loader').classList.toggle('hidden', !loading);
  btn.disabled = loading;
}

function togglePw(inputId) {
  const inp = document.getElementById(inputId);
  if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Máscara CPF/CNPJ
function maskDoc(input) {
  let v = input.value.replace(/\D/g, '');
  if (v.length <= 11) {
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  } else {
    v = v.replace(/(\d{2})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1/$2');
    v = v.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
  }
  input.value = v;
}

// Indicador de força de senha
function atualizarForcaSenha() {
  const v = document.getElementById('nova-senha').value;
  const container = document.getElementById('senha-strength');
  const fill  = document.getElementById('strength-fill');
  const label = document.getElementById('strength-label');

  if (!v) { container.classList.add('hidden'); return; }
  container.classList.remove('hidden');

  let score = 0;
  if (v.length >= 8)  score++;
  if (v.length >= 12) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;

  const levels = [
    { pct: '20%', color: '#FF5F5F', text: 'Muito fraca' },
    { pct: '40%', color: '#FF9A3C', text: 'Fraca' },
    { pct: '60%', color: '#FFD166', text: 'Razoável' },
    { pct: '80%', color: '#A8F0A0', text: 'Boa' },
    { pct: '100%', color: '#E8FF47', text: 'Forte' },
  ];
  const l = levels[Math.min(score, 4)];
  fill.style.width      = l.pct;
  fill.style.background = l.color;
  label.textContent     = l.text;
  label.style.color     = l.color;
}

// ============================================================
// LOAD PORTAL DATA
// ============================================================
async function loadPortalData() {
  await Promise.all([loadAssociado(), loadBeneficios()]);
  setTimeout(() => {
    carregarCategoriasFiltro();
    loadCatalogoProdutos();
    checkAdminAccess();
  }, 100);
}

async function loadAssociado() {
  try {
    associadoData = await apiGetAssociado();
    renderAssociado(associadoData);
  } catch (err) {
    console.error('Erro ao carregar dados:', err);
    document.getElementById('empresa-loading').classList.add('hidden');
    document.getElementById('empresa-error').classList.remove('hidden');
    // Sessão expirada
    if (err.status === 401) { handleLogout(); }
  }
}

async function loadBeneficios() {
  try {
    beneficiosData = await apiGetBeneficios();
    renderBeneficios(beneficiosData);
    renderFeaturedBenefits(beneficiosData);
    const elBenef = document.getElementById('stat-benefits'); if (elBenef) elBenef.textContent = beneficiosData.length;
    carregarLinksImportantes();
  } catch (err) {
    console.error('Erro ao carregar benefícios:', err);
    document.getElementById('benefits-loading').classList.add('hidden');
  }
}

// ============================================================
// RENDER — ASSOCIADO
// ============================================================
function renderAssociado(d) {
  if (!d) return;

  // Admin: força status ativo e categoria correta
  const session = getSession();
  if (session?.cpf && d.razaoSocial === 'Administrador') {
    d.status = 'ativo';
    d.statusTexto = 'Administrador';
    d.categoria = 'Admin';
  }

  const tipoLabel = d.tipo === 'contribuinte' ? 'Contribuinte' : 'Empresa';
  const initial   = (d.nomeFantasia !== '—' ? d.nomeFantasia : d.razaoSocial || 'A')[0].toUpperCase();

  // Sidebar
  document.getElementById('sb-avatar').textContent = initial;
  document.getElementById('sb-company-name').textContent =
    d.razaoSocial === 'Administrador'
      ? (getSession()?.nome || 'Administrador')
      : (d.nomeFantasia !== '—' ? d.nomeFantasia : d.razaoSocial);
  const statusTxt = d.status === 'ativo' ? 'Associado Ativo'
    : d.status === 'inadimplente' ? 'Inadimplente'
    : d.status === 'suspenso' ? 'Suspenso'
    : d.status === 'cancelado' ? 'Cancelado'
    : d.razaoSocial === 'Administrador' ? 'Administrador'
    : statusLabel(d.status);
  document.getElementById('sb-status-badge').textContent = 
    d.razaoSocial === 'Administrador' ? 'Administrador' : statusTxt;
  document.getElementById('topbar-tag').textContent = 
    d.razaoSocial === 'Administrador' ? 'Administrador' : statusTxt;
  document.getElementById('topbar-tag').style.display =
    getSession()?.is_admin === true ? '' : 'none';

  // Dashboard
  const nomeExibir = d.razaoSocial === 'Administrador' 
    ? (getSession()?.nome || d.razaoSocial)
    : (d.nomeFantasia !== '—' ? d.nomeFantasia : d.razaoSocial);
  // dash-greeting oculto
  // Personalizar stats por perfil
  const _role = getSession()?.role || getSession()?.tipo || '';
  if (_role === 'colaborador') {
    document.getElementById('stat-category').textContent = 'Colaborador';
  } else if (_role === 'dependente') {
    document.getElementById('stat-category').textContent = 'Dependente';
  }
  document.getElementById('stat-status').textContent   = statusLabel(d.status);
  renderDashboardPerfil(d);
  document.getElementById('stat-since').textContent    = d.razaoSocial === 'Administrador' ? 'ACIC-DF' : formatDateOrPending(d.dataAssociacao);
  document.getElementById('stat-category').textContent = tipoLabel;

  // Dashboard mini
  const dashCompany = document.getElementById('dash-company-info');
  if (dashCompany) dashCompany.innerHTML = `
    <div class="company-mini-row">
      <span class="company-mini-label">Razão Social</span>
      <span class="company-mini-value">${d.razaoSocial}</span>
    </div>
    <div class="company-mini-row">
      <span class="company-mini-label">${d.tipo === 'contribuinte' ? 'CPF' : 'CNPJ'}</span>
      <span class="company-mini-value">${d.tipo === 'contribuinte' ? formatCPF(d.cnpj) : formatCNPJ(d.cnpj)}</span>
    </div>
    <div class="company-mini-row">
      <span class="company-mini-label">${d.tipo === 'contribuinte' ? 'Porte' : 'Porte'}</span>
      <span class="company-mini-value">${d.categoria || tipoLabel}</span>
    </div>
  `;

  // Empresa section
  document.getElementById('empresa-loading').classList.add('hidden');
  document.getElementById('empresa-content').classList.remove('hidden');

  // Para admin: CPF do config (nao CNPJ da sessao CRM)
  const isAdminUser = getSession()?.is_admin === true;
  document.getElementById('emp-avatar-big').textContent = initial;
  const _isColab = _role === 'colaborador' || _role === 'dependente';
  document.getElementById('emp-razao').textContent      = isAdminUser ? 'Administrador ACIC-DF' : (_isColab ? (d.razaoSocial || 'Empresa vinculada') : d.razaoSocial);
  document.getElementById('emp-fantasia').textContent   = isAdminUser ? 'Painel de Administração' : (_isColab ? 'Você está vinculado como ' + (_role === 'colaborador' ? 'Colaborador' : 'Dependente') : (d.nomeFantasia !== '—' ? d.nomeFantasia : ''));

  const badge       = document.getElementById('emp-status-badge');
  badge.textContent = isAdminUser ? 'Administrador' : (d.statusTexto || statusLabel(d.status));
  badge.className   = `badge active`;

  const docLabel = isAdminUser ? 'CPF' : (d.tipo === 'contribuinte' ? 'CPF' : 'CNPJ');
  const rawDoc   = isAdminUser ? d.cnpj : d.cnpj;
  const docFmt   = isAdminUser ? formatCPF(d.cnpj) : (d.tipo === 'contribuinte' ? formatCPF(d.cnpj) : formatCNPJ(d.cnpj));

  document.getElementById('empresa-fields').innerHTML = isAdminUser ? `
    <div class="field-group"><label>CPF</label><span>${docFmt}</span></div>
    <div class="field-group"><label>Perfil</label><span>Super Administrador</span></div>
    <div class="field-group"><label>Acesso</label><span>Total — Gerenciar produtos, usuários e métricas</span></div>
    <div class="field-group"><label>Organização</label><span>ACIC-DF</span></div>
  ` : `
    <div class="field-group"><label>${docLabel}</label><span>${docFmt}</span></div>
    <div class="field-group"><label>Porte</label><span>${d.categoria !== '—' ? d.categoria : tipoLabel}</span></div>
    <div class="field-group"><label>E-mail</label><span>${d.email}</span></div>
    <div class="field-group"><label>Telefone</label><span>${d.telefone}</span></div>
    <div class="field-group"><label>Ramo de atividade</label><span>${d.ramo || '—'}</span></div>
    <div class="field-group"><label>CNAE</label><span>${d.registro}</span></div>
    <div class="field-group"><label>Associado desde</label><span>${formatDate(d.dataAssociacao)}</span></div>
    <div class="field-group"><label>Renovação</label><span>${d.dataRenovacao ? formatDate(d.dataRenovacao) : '—'}</span></div>
  `;

  document.getElementById('responsavel-fields').innerHTML = isAdminUser ? `
    <div class="field-group"><label>Função</label><span>Administrador do Portal</span></div>
    <div class="field-group"><label>Contato</label><span>secretaria@acicdf.org.br</span></div>
  ` : `
    <div class="field-group"><label>Tipo</label><span>${tipoLabel}</span></div>
    <div class="field-group"><label>Porte</label><span>${d.categoria !== '—' ? d.categoria : '—'}</span></div>
  `;

  const endCompleto = [d.logradouro, d.complemento].filter(Boolean).join(', ');
  document.getElementById('endereco-fields').innerHTML = `
    <div class="field-group"><label>Endereço</label><span>${endCompleto || '—'}</span></div>
    <div class="field-group"><label>Bairro</label><span>${d.bairro}</span></div>
    <div class="field-group"><label>Cidade / UF</label><span>${d.cidade} / ${d.uf}</span></div>
    <div class="field-group"><label>CEP</label><span>${formatCEP(d.cep)}</span></div>
  `;
}

// ============================================================
// RENDER — BENEFÍCIOS
// ============================================================
function renderBeneficios(list) {
  const tags = ['todos', ...new Set(list.map(b => b.categoria.toLowerCase()))];
  const tagsEl = document.getElementById('filter-tags');
  tagsEl.innerHTML = tags.map(t =>
    `<button class="filter-tag ${t === 'todos' ? 'active' : ''}" onclick="filterByTag('${t}', this)">${capitalize(t)}</button>`
  ).join('');
  renderBenefitsGrid(list);
}

function renderBenefitsGrid(list) {
  const grid    = document.getElementById('benefits-grid');
  const empty   = document.getElementById('benefits-empty');
  const loading = document.getElementById('benefits-loading');

  loading.classList.add('hidden');

  if (!list.length) {
    grid.classList.add('hidden');
    empty.classList.remove('hidden');
    return;
  }

  empty.classList.add('hidden');
  grid.classList.remove('hidden');
  grid.innerHTML = list.map(b => `
    <div class="benefit-card" onclick="openBenefitModal('${b.id}')">
      <div class="benefit-cat">${b.categoria}</div>
      <div class="benefit-title">${b.titulo}</div>
      <div class="benefit-desc">${b.descricao}</div>
      ${b.tags.length ? `<div class="benefit-tags">${b.tags.map(t => `<span class="benefit-tag">${t}</span>`).join('')}</div>` : ''}
      <div class="benefit-cta">
        ${b.ctaTexto || 'Saiba mais'}
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </div>
    </div>
  `).join('');
}

// Carrossel de destaques — estado
let _carouselIndex = 0;
let _carouselItems = [];
let _carouselTimer = null;

function renderFeaturedBenefits(list) {
  const featured = list.filter(b => b.destaque).slice(0, 6);
  const container = document.getElementById('featured-benefits');
  if (!container) return;

  if (!featured.length) {
    container.innerHTML = '<p style="color:var(--text3);font-size:13px;padding:20px 0">Nenhum destaque disponível.</p>';
    const dots = document.getElementById('carousel-dots');
    if (dots) dots.innerHTML = '';
    return;
  }

  _carouselItems = featured;
  _carouselIndex = 0;

  container.innerHTML = featured.map((b, i) => {
    const isFirst = i === 0;
    const slideStyle = isFirst
      ? 'opacity:1;position:relative;pointer-events:auto'
      : 'opacity:0;position:absolute;top:0;left:0;width:100%;pointer-events:none';
    return `
    <div class="carousel-slide" data-index="${i}" style="${slideStyle}" onclick="abrirProduto('${b.slug||b.id}')">
      <div class="carousel-img-wrap">
        ${b.imagem
          ? `<img src="${b.imagem}" alt="${b.titulo}" class="carousel-img" loading="lazy">`
          : `<div class="carousel-img-placeholder"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>`}
        <div class="carousel-overlay">
          <span class="carousel-cat-badge">${b.categoria}</span>
          ${b.parceiro_logo ? '<div style="position:absolute;bottom:12px;left:12px;display:flex;align-items:center;gap:8px;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);padding:6px 12px;border-radius:8px"><img src="' + b.parceiro_logo + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3)"><span style="color:#fff;font-size:11px;font-weight:600">' + (b.parceiro_fantasia || b.parceiro_nome || '') + '</span></div>' : ''}
        </div>
        <div style="position:absolute;top:16px;right:-30px;transform:rotate(45deg);background:#E8701A;color:#fff;font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:4px 40px;box-shadow:0 2px 8px rgba(232,112,26,.3)">DESTAQUE</div>
      </div>
      <div class="carousel-info">
        <div class="carousel-empresa">${b.empresa || 'ACIC-DF'}</div>
        <h4 class="carousel-title">${b.titulo}</h4>
        <p class="carousel-desc">${b.descricao ? (b.descricao.substring(0,120) + (b.descricao.length > 120 ? '...' : '')) : ''}</p>
        <div class="carousel-cta"><span>Saiba mais</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
      </div>
    </div>`;
  }).join('');

  const dots = document.getElementById('carousel-dots');
  if (dots) {
    dots.innerHTML = featured.map((_, i) => `<button class="carousel-dot ${i === 0 ? 'active' : ''}" onclick="carouselGoTo(${i})" aria-label="Slide ${i+1}"></button>`).join('');
  }

  clearInterval(_carouselTimer);
  _carouselTimer = setInterval(() => carouselNav(1), 5000);
}

function carouselNav(dir) {
  const total = _carouselItems.length;
  if (!total) return;
  _carouselIndex = (_carouselIndex + dir + total) % total;
  carouselGoTo(_carouselIndex);
}

function carouselGoTo(idx) {
  _carouselIndex = idx;
  document.querySelectorAll('.carousel-slide').forEach((el, i) => {
    const isActive = i === idx;
    el.style.opacity = isActive ? '1' : '0';
    el.style.pointerEvents = isActive ? 'auto' : 'none';
    el.style.position = isActive ? 'relative' : 'absolute';
    if (!isActive) { el.style.top = '0'; el.style.left = '0'; el.style.width = '100%'; }
  });
  document.querySelectorAll('.carousel-dot').forEach((el, i) => el.classList.toggle('active', i === idx));
  clearInterval(_carouselTimer);
  _carouselTimer = setInterval(() => carouselNav(1), 5000);
}

window.carouselNav = carouselNav;
window.carouselGoTo = carouselGoTo;

// ============================================================
// LINKS IMPORTANTES
// ============================================================
async function carregarLinksImportantes() {
  const container = document.getElementById('links-importantes-lista');
  if (!container) return;
  try {
    const res  = await fetch(`${ADMIN_URL}?action=links_listar`, { headers: { 'Authorization': 'Bearer ' + getToken() } });
    const data = await res.json();
    const links = data.success ? (data.data || []) : [];
    renderLinksImportantes(links);
  } catch(e) {
    if (container) container.innerHTML = '<p style="color:var(--text3);font-size:13px;">Nenhum link disponível.</p>';
  }
}

// Cache de links para ações seguras
const _linksCache = new Map();

function renderLinksImportantes(links) {
  const container = document.getElementById('links-importantes-lista');
  if (!container) return;
  const isAdm = getSession()?.is_admin === true;

  if (!links.length) {
    container.innerHTML = isAdm
      ? '<p style="color:var(--text3);font-size:13px;">Nenhum link cadastrado ainda.</p>'
      : '<p style="color:var(--text3);font-size:13px;">Nenhum link disponível.</p>';
    return;
  }

  links.forEach(l => _linksCache.set(l.id, l));

  container.innerHTML = links.map(l => `
    <div class="featured-item link-item">
      <div class="link-item-main">
        <div class="featured-dot" style="background:var(--accent);flex-shrink:0"></div>
        <a href="${escHtml(l.url)}" target="_blank" rel="noopener"
           class="featured-name link-titulo"
           onclick="registrarCliqueLink(${l.id})">${escHtml(l.titulo)}</a>
      </div>
      ${isAdm ? `
        <div class="link-item-actions">
          <button class="btn-table-action" onclick="editarLinkPorId(${l.id})">Editar</button>
          <button class="btn-table-action" style="color:var(--danger)" onclick="excluirLink(${l.id})">Excluir</button>
        </div>` : ''}
    </div>
  `).join('');
}

function editarLinkPorId(id) {
  const l = _linksCache.get(id);
  if (l) abrirFormLink(l);
}

async function registrarCliqueLink(id) {
  fetch(`${ADMIN_URL}?action=links_clique`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getToken() },
    body: JSON.stringify({ id })
  }).catch(() => {});
}

function abrirFormLink(link = null) {
  const modal = document.getElementById('modal-link-form');
  if (!modal) return;
  document.getElementById('link-form-titulo-modal').textContent = link ? 'Editar Link' : 'Novo Link';
  document.getElementById('link-id').value      = link?.id || '';
  document.getElementById('link-titulo').value  = link?.titulo || '';
  document.getElementById('link-url').value     = link?.url || '';
  document.getElementById('link-icone').value   = link?.icone || '';
  document.getElementById('link-ordem').value   = link?.ordem ?? 0;
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function fecharFormLink() {
  document.getElementById('modal-link-form')?.classList.add('hidden');
  document.body.style.overflow = '';
}

async function salvarLink(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-salvar-link');
  btn.disabled = true; btn.textContent = 'Salvando...';
  try {
    const payload = {
      id:     document.getElementById('link-id').value || null,
      titulo: document.getElementById('link-titulo').value.trim(),
      url:    document.getElementById('link-url').value.trim(),
      icone:  document.getElementById('link-icone').value.trim(),
      ordem:  parseInt(document.getElementById('link-ordem').value) || 0,
    };
    const action = payload.id ? 'links_editar' : 'links_criar';
    await adminApi(action, payload);
    fecharFormLink();
    carregarLinksImportantes();
  } catch(err) {
    notify.erro(err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Salvar';
  }
}

async function excluirLink(id) {
  if (!confirm('Excluir este link?')) return;
  try {
    await adminApi('links_excluir', { id });
    carregarLinksImportantes();
  } catch(err) {
    notify.erro(err.message);
  }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
// ============================================================
function filterBenefits() {
  const q = document.getElementById('search-input').value.toLowerCase();
  let filtered = beneficiosData;
  if (activeTag !== 'todos') filtered = filtered.filter(b => b.categoria.toLowerCase() === activeTag);
  if (q) filtered = filtered.filter(b =>
    b.titulo.toLowerCase().includes(q) ||
    b.descricao.toLowerCase().includes(q) ||
    b.categoria.toLowerCase().includes(q) ||
    b.tags.some(t => t.toLowerCase().includes(q))
  );
  renderBenefitsGrid(filtered);
}

function filterByTag(tag, btn) {
  activeTag = tag;
  document.querySelectorAll('.filter-tag').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  filterBenefits();
}

// ============================================================
// MODAL
// ============================================================
function openBenefitModal(id) {
  const b = beneficiosData.find(x => String(x.id) === String(id));
  if (!b) return;
  // Se tem slug, abre o modal de produto completo com galeria
  if (b.slug) { abrirProduto(b.slug); return; }
  // Fallback: modal simples
  document.getElementById('modal-tag').textContent   = b.categoria;
  document.getElementById('modal-title').textContent = b.titulo;
  document.getElementById('modal-desc').textContent  = b.descricao;
  document.getElementById('modal-meta').innerHTML    =
    b.tags.map(t => `<span class="benefit-tag">${t}</span>`).join('');
  const cta   = document.getElementById('modal-cta');
  cta.textContent = b.ctaTexto || 'Quero este benefício';
  cta.href        = b.link || '#';
  document.getElementById('benefit-modal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeBenefitModal(e) {
  if (e && e.target !== document.getElementById('benefit-modal')) return;
  document.getElementById('benefit-modal').classList.add('hidden');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeBenefitModal();
});

// ============================================================
// NAVIGATION
// ============================================================
const SECTION_TITLES = {
  dashboard:              'Dashboard',
  catalogo:               'Catálogo de Produtos',
  empresa:                'Minha Empresa',
  carteirinha:            'Minha Carteirinha',
  cobrancas:              'Minhas Cobranças',
  'admin-produtos':       'Gerenciar Produtos',
  'admin-metricas':       'Métricas',
  'admin-comunicados':    'Comunicados',
  'admin-parceiros':      'Parceiros',
  'admin-categorias':     'Categorias',
};

const SECTION_SUBTITLES = {
  dashboard:              'Seu painel de benefícios e informações',
  catalogo:               'Produtos e serviços dos parceiros ACIC-DF',
  empresa:                'Dados cadastrais na ACIC-DF',
  carteirinha:            'Carteira digital do associado',
  cobrancas:              'Taxas e cobranças da associação',
  'admin-produtos':       'Gerenciar produtos do catálogo',
  'admin-metricas':       'Indicadores e análise de uso',
  'admin-comunicados':    'Templates e envio de comunicados',
  'admin-parceiros':      'Fornecedores de produtos',
  'admin-categorias':     'Categorias do catálogo',
};

function showSection(id) {
  refreshPermissoes();
  currentSection = id;
  sessionStorage.setItem('acic_last_section', id);
  // Fechar painel de comunicados se estiver aberto
  if (_notifAberto) fecharNotifPanel();
  document.querySelectorAll('.portal-section').forEach(el => el.classList.remove('active'));
  document.getElementById(`section-${id}`).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(el => {
    if (el.getAttribute('onclick')?.includes(`'${id}'`)) el.classList.add('active');
  });
  document.getElementById('topbar-title').textContent = SECTION_TITLES[id] || id;
  const subEl = document.getElementById('topbar-subtitle');
  if (subEl) subEl.textContent = SECTION_SUBTITLES[id] || '';
  if (window.innerWidth <= 900) closeSidebar();
  if (id === 'catalogo') { loadCatalogoProdutos(); carregarCategoriasFiltro(); }
  if (id === 'comunicados') iniciarComunicados();
  if (id === 'carteirinha') loadCarteirinha();
  if (id === 'cobrancas') loadCobrancas();
  // Metricas: acessivel para todos (nao depende do guard admin)
  if (id === 'admin-metricas') { carregarMetricas(); }
  const adminSections = ['admin-produtos','admin-parceiros','admin-comunicados','admin-categorias'];
  if (adminSections.includes(id)) {
    const navAdmin = document.getElementById('nav-admin');
    if (!navAdmin || navAdmin.classList.contains('hidden')) {
      currentSection = 'dashboard';
      document.querySelectorAll('.portal-section').forEach(el => el.classList.remove('active'));
      document.getElementById('section-dashboard')?.classList.add('active');
      document.getElementById('topbar-title').textContent = 'Dashboard';
      return;
    }
    if (id === 'admin-produtos') { loadAdminProdutos(); const w=document.getElementById('sub-admin-wrap'); if(w) w.style.display='none'; }
    if (id === 'admin-metricas') carregarMetricas();
    if (id === 'admin-comunicados') iniciarComunicados();
    if (id === 'admin-parceiros') carregarParceiros();
    if (id === 'admin-categorias') loadAdminCategorias();
    if (id === 'configuracoes') renderizarMatrizPermissoes();
  }
}

// ============================================================
// CONFIGURAÇÕES — MATRIZ DE PERFIS E PERMISSÕES
// ============================================================
function renderizarMatrizPermissoes() {
  const tbody = document.getElementById('permissoes-tbody');
  if (!tbody) return;

  // Matriz alinhada: CRM e fonte da verdade, Conecta e portal do associado
  // Perfis: Superadmin, Gestor, Empresa (associado), Colaborador, Dependente
  // Nota: Atendente removido. Gateway/Planos/Usuarios/Admins/Config sao do CRM.
  const modulos = [
    { nome: 'Dashboard',                superadmin: true,  gestor: true,  empresa: true,  colaborador: true,  dependente: true  },
    { nome: 'Catálogo',            superadmin: true,  gestor: true,  empresa: true,  colaborador: true,  dependente: true  },
    { nome: 'Carteirinha',              superadmin: true,  gestor: true,  empresa: true,  colaborador: true,  dependente: false },
    { nome: 'Cobranças',           superadmin: true,  gestor: true,  empresa: true,  colaborador: false, dependente: false },
    { nome: 'Minha Empresa',            superadmin: true,  gestor: true,  empresa: true,  colaborador: false, dependente: false },
    { nome: 'Métricas',            superadmin: true,  gestor: true,  empresa: true,  colaborador: true,  dependente: true  },
    { nome: 'Comunicados (receber)',     superadmin: true,  gestor: true,  empresa: true,  colaborador: true,  dependente: true  },
    { nome: 'Comunicados (enviar)',      superadmin: true,  gestor: true,  empresa: false, colaborador: false, dependente: false },
    { nome: 'Gerenciar Produtos',        superadmin: true,  gestor: true,  empresa: false, colaborador: false, dependente: false },
    { nome: 'Categorias',               superadmin: true,  gestor: false, empresa: false, colaborador: false, dependente: false },
    { nome: 'Parceiros',                superadmin: true,  gestor: true,  empresa: false, colaborador: false, dependente: false },
  ];

  const check = '<span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#e6f4ea;color:#1e7e34;font-size:14px;font-weight:700">✓</span>';
  const cross = '<span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#fce8e6;color:#c5221f;font-size:14px;font-weight:700">✕</span>';

  tbody.innerHTML = modulos.map((m, i) => {
    const bg = i % 2 === 0 ? 'var(--surface)' : 'var(--surface2, var(--surface))';
    const cell = 'text-align:center;padding:12px 16px;border-bottom:1px solid var(--border)';
    return `<tr style="background:${bg};transition:background .15s" onmouseenter="this.style.background='var(--hover,rgba(27,43,107,.04))'" onmouseleave="this.style.background='${bg}'">
      <td style="padding:12px 16px;font-weight:600;color:var(--text);border-bottom:1px solid var(--border)">${m.nome}</td>
      <td style="${cell}">${m.superadmin ? check : cross}</td>
      <td style="${cell}">${m.gestor ? check : cross}</td>
      <td style="${cell}">${m.empresa ? check : cross}</td>
      <td style="${cell}">${m.colaborador ? check : cross}</td>
      <td style="${cell}">${m.dependente ? check : cross}</td>
    </tr>`;
  }).join('');
}
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebar-overlay');
  const isOpen   = sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('active', isOpen);
  document.body.style.overflow = isOpen && window.innerWidth <= 768 ? 'hidden' : '';
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  sidebar.classList.remove('open');
  if (overlay) overlay.classList.remove('active');
  document.body.style.overflow = '';
}

document.addEventListener('click', e => {
  const sidebar   = document.getElementById('sidebar');
  const hamburger = document.querySelector('.hamburger');
  if (window.innerWidth <= 900 &&
      sidebar.classList.contains('open') &&
      !sidebar.contains(e.target) &&
      !hamburger.contains(e.target)) {
    closeSidebar();
  }
});

// ============================================================
// FORMATTERS
// ============================================================
function formatDate(d) {
  if (!d) return '—';
  try { return new Date(d).toLocaleDateString('pt-BR'); } catch { return d; }
}
function formatDateOrPending(d) {
  if (!d) return 'Em processamento';
  if (typeof d === 'string' && !/^\d{4}-/.test(d)) return d;
  try { return new Date(d).toLocaleDateString('pt-BR'); } catch { return d; }
}

function formatCNPJ(v) {
  if (!v) return '—';
  const n = v.replace(/\D/g,'');
  if (n.length !== 14) return v;
  return n.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
}

function formatCPF(v) {
  if (!v) return '—';
  const n = v.replace(/\D/g,'');
  if (n.length !== 11) return v;
  return n.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function formatCEP(v) {
  if (!v) return '—';
  const n = v.replace(/\D/g,'');
  if (n.length !== 8) return v;
  return n.replace(/(\d{5})(\d{3})/, '$1-$2');
}

function statusLabel(s) {
  const map = {
    ativo:    'Associado Ativo',
    inativo:  'Inativo',
    pendente: 'Pagamento Pendente',
    admin:    'Administrador',
    'Ativo':  'Associado Ativo',
    'ativo':  'Associado Ativo',
  };
  return map[s] || (s ? s.charAt(0).toUpperCase() + s.slice(1).toLowerCase() : 'Não informado');
}

function capitalize(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
}

// ── ADMIN REVEAL ─────────────────────────────────────────────
// Verifica se é admin — menu só aparece após confirmação do backend
let _adminChecked = false;
function mostrarNavAdmin(isSuperadmin) {
  const navAdmin = document.getElementById('nav-admin');
  if (navAdmin) navAdmin.classList.remove('hidden');
  ['nav-metricas','nav-comunicados','nav-parceiros','nav-categorias'].forEach(id => {
    document.getElementById(id)?.classList.remove('hidden');
  });
  document.getElementById('btn-novo-link')?.style && (document.getElementById('btn-novo-link').style.display = '');
  // Gestor ACIC: ocultar abas exclusivas de superadmin
  const sess = getSession();
  if (sess && sess.tipo === 'gestor') {
    ['nav-taxas','nav-parceiros'].forEach(id => {
      document.getElementById(id)?.classList.add('hidden');
    });
  }
}

function ocultarElementosAdmin() {
  ['nav-admin','nav-metricas','nav-comunicados','nav-parceiros','nav-categorias'].forEach(id => {
    document.getElementById(id)?.classList.add('hidden');
  });
  const btn = document.getElementById('btn-novo-link');
  if (btn) btn.style.display = 'none';
  const tag = document.getElementById('topbar-tag');
  if (tag) tag.style.display = 'none';
}

function checkAdminAccess() {
  if (_adminChecked) return;
  _adminChecked = true;

  const token = getToken();
  if (!token) { ocultarElementosAdmin(); return; }

  const sess = getSession();
  if (sess?.is_admin === true) { mostrarNavAdmin(sess?.is_superadmin === true); return; }

  // Apenas oculta se nao for admin (evita flash)
  ocultarElementosAdmin();

  fetch(ADMIN_URL + '?action=admin_check', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
    body: JSON.stringify({ token }),
  })
  .then(r => r.json())
  .then(r => {
    if (r.success === true && r.data?.is_admin === true) {
      const s = getSession() || {};
      s.is_admin = true;
      s.is_superadmin = r.data?.is_superadmin === true;
      setSession(s);
      mostrarNavAdmin(s.is_superadmin);
      _restaurarSecao(); // restaura seção após confirmar permissões admin
    } else {
      ocultarElementosAdmin();
    }
  })
  .catch(() => { ocultarElementosAdmin(); });
}



// ── GALERIA DE IMAGENS ───────────────────────────────────────
function trocarImagemGaleria(thumbEl, url) {
  const mainImg = document.getElementById('galeria-main-img');
  if (mainImg) {
    mainImg.style.opacity = '0';
    setTimeout(() => {
      mainImg.src = url;
      mainImg.style.opacity = '1';
    }, 150);
  }
  document.querySelectorAll('.galeria-thumb').forEach(t => t.classList.remove('active'));
  thumbEl.classList.add('active');
}

// ── GALERIA NO ADMIN ─────────────────────────────────────────
async function carregarGaleriaAdmin(produtoId) {
  const wrap = document.getElementById('admin-galeria-wrap');
  const grid = document.getElementById('admin-galeria-grid');
  if (!wrap || !grid) return;
  wrap.style.display = 'grid'; // uses CSS grid layout
  grid.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:10px 0">Carregando...</div>';
  try {
    const imgs = await prodApi('imagem_listar', { produto_id: produtoId }, 'GET');
    renderGaleriaAdmin(imgs, produtoId);
    // Sincroniza a preview da capa
    const capa = imgs.find(i => i.principal) || imgs[0];
    if (capa) atualizarPreviewCapa(capa.url);
    // Atualiza campo hidden fp-imagem
    if (capa) document.getElementById('fp-imagem').value = capa.url;
  } catch(e) {
    grid.innerHTML = '<div style="color:var(--danger);font-size:12px">Erro ao carregar galeria.</div>';
  }
}

function renderGaleriaAdmin(imgs, produtoId) {
  const grid = document.getElementById('admin-galeria-grid');
  if (!grid) return;

  if (!imgs.length) {
    grid.innerHTML = '<div class="galeria-empty">Nenhuma imagem. Clique em "Adicionar foto" para fazer upload.</div>';
    return;
  }

  grid.innerHTML = imgs.map(img => `
    <div class="galeria-admin-item ${img.principal ? 'is-capa' : ''}" id="gimg-${img.id}">
      <img src="${img.url}" alt="foto" class="galeria-admin-thumb"
           onerror="this.src='';this.parentElement.style.background='var(--surface2)'"/>
      ${img.principal ? '<div class="galeria-capa-badge">Capa</div>' : ''}
      <div class="galeria-admin-actions">
        ${!img.principal
          ? `<button type="button" class="btn-set-capa" onclick="setPrincipal(${img.id},${produtoId})" title="Definir como capa">Capa</button>`
          : `<span style="color:var(--accent);font-size:10px;font-weight:700">✓ Capa</span>`
        }
        <button type="button" class="btn-del-img" onclick="excluirImagem(${img.id},${produtoId})" title="Remover">✕</button>
      </div>
    </div>
  `).join('');
}

async function setPrincipal(imgId, produtoId) {
  try {
    await prodApi('imagem_principal', { id: imgId, produto_id: produtoId, token: getToken() });
    await carregarGaleriaAdmin(produtoId);
  } catch(e) { mostrarToast('Erro', e.message, 'alerta'); }
}

async function excluirImagem(imgId, produtoId) {
  // Marca visualmente e pede confirmação sem confirm() que pode disparar submit
  const el = document.getElementById('gimg-' + imgId);
  if (el) el.style.opacity = '0.4';
  if (!window.confirm('Remover esta imagem?')) {
    if (el) el.style.opacity = '';
    return;
  }
  try {
    await prodApi('imagem_excluir', { id: imgId, token: getToken() });
    if (el) el.remove();
    await carregarGaleriaAdmin(produtoId);
  } catch(e) {
    if (el) el.style.opacity = '';
    mostrarToast('Erro', e.message, 'alerta');
  }
}

// Preview antes de adicionar URL
function previewImagemUrl() {
  const url = document.getElementById('fp-img-url').value.trim();
  const prev = document.getElementById('fp-img-preview');
  if (!url || !prev) return;
  prev.src = url;
  prev.style.display = 'block';
  prev.onerror = () => { prev.style.display = 'none'; };
}

async function adicionarImagemUrl(produtoId) {
  const url = document.getElementById('fp-img-url').value.trim();
  if (!url) { notify.aviso('Informe a URL da imagem.'); return; }
  try {
    await prodApi('imagem_add', { produto_id: produtoId, url, token: getToken() });
    document.getElementById('fp-img-url').value = '';
    document.getElementById('fp-img-preview').style.display = 'none';
    carregarGaleriaAdmin(produtoId);
  } catch(e) { notify.erro(e.message); }
}

// ============================================================
// GESTÃO DE ADMINS
// ============================================================
async function carregarAdmins() {
  const tbody = document.getElementById('admins-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text3)"><div class="spinner" style="width:18px;height:18px;margin:0 auto"></div></td></tr>';
  try {
    const lista = await adminApi('listar_admins', {}, 'GET');
    if (!lista || !lista.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="color:var(--text3);padding:16px;font-size:13px">Nenhum admin cadastrado.</td></tr>';
      return;
    }
    tbody.innerHTML = lista.map(u => {
      const canRevoke = parseInt(u.is_admin) === 1;
      const nivelBadge = canRevoke
        ? '<span style="font-size:11px;font-weight:600;color:var(--text2);background:var(--surface2);padding:2px 8px;border-radius:4px;border:1px solid var(--border)">Admin</span>'
        : '<span style="font-size:11px;font-weight:700;color:var(--accent);background:var(--accent-soft);padding:2px 8px;border-radius:4px;border:1px solid var(--accent-border)">Superadmin</span>';
      return `<tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 0">
          <div style="font-weight:600;font-size:13px;color:var(--text)">${escHtml(u.nome || '—')}</div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px">${escHtml(u.doc_fmt || u.cpf_cnpj)}</div>
        </td>
        <td style="padding:12px 8px">${nivelBadge}</td>
        <td style="padding:12px 8px"><span style="font-size:12px;font-weight:600;color:${u.ativo?'#1D9E75':'#E24B4A'}">${u.ativo?'Ativo':'Inativo'}</span></td>
        <td style="padding:12px 0;text-align:right">
          ${canRevoke
            ? `<button class="btn-table-action" style="color:var(--danger)" onclick="revogarAdmin(${u.id},'${escHtml(u.nome||u.doc_fmt)}')">Revogar</button>`
            : '<span style="font-size:11px;color:var(--text3)">—</span>'}
        </td>
      </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="4" style="color:var(--danger);padding:16px;font-size:13px">${escHtml(e.message)}</td></tr>`;
  }
}

async function promoverAdmin() {
  const input = document.getElementById('novo-admin-doc');
  const doc = input?.value?.trim();
  if (!doc) { mostrarToast('⚠️ Campo vazio','Informe o CPF ou CNPJ.','aviso'); return; }
  const docLimpo = doc.replace(/\D/g,'');
  try {
    const lista = await adminApi('usuarios', {}, 'GET');
    const user = lista.find(u => u.cpf_cnpj === docLimpo);
    if (!user) { mostrarToast('❌ Não encontrado','Usuário não encontrado. Ele precisa ter acessado o portal ao menos uma vez.','alerta'); return; }
    await adminApi('promover_admin', { id: user.id });
    mostrarToast('✅ Admin criado',`${user.doc_fmt || docLimpo} agora é administrador.`,'sucesso');
    if (input) input.value = '';
    carregarAdmins();
  } catch(e) {
    mostrarToast('❌ Erro', e.message, 'alerta');
  }
}

async function revogarAdmin(id, nome) {
  if (!confirm('Revogar acesso de admin para ' + nome + '?')) return;
  try {
    await adminApi('revogar_admin', { id });
    mostrarToast('✅ Revogado', nome + ' não é mais administrador.', 'sucesso');
    carregarAdmins();
  } catch(e) {
    mostrarToast('❌ Erro', e.message, 'alerta');
  }
}

// ── SISTEMA DE TEMAS ─────────────────────────────────────────
function initTheme() {
  const saved = localStorage.getItem('acic_theme') ||
    (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  applyTheme(saved, false);
}

function applyTheme(theme, save = true) {
  // Aplica em html E body para garantir background
  document.documentElement.setAttribute('data-theme', theme);
  document.body.setAttribute('data-theme', theme);
  document.body.style.background = theme === 'light' ? '#EEF1F8' : '#080E1A';
  document.body.style.color      = theme === 'light' ? '#0F2137' : '#EDF2FF';
  if (save) localStorage.setItem('acic_theme', theme);

  // Troca logo conforme tema
  const logoLight = 'uploads/logo-light-320.png?v=2';
  const logoDark  = 'uploads/logo-dark-320.png?v=2';
  const loginLogo   = document.getElementById('login-logo');
  const sidebarLogo = document.getElementById('sidebar-logo');
  if (loginLogo)   loginLogo.src   = theme === 'light' ? logoLight : logoDark;
  if (sidebarLogo) sidebarLogo.src = theme === 'light' ? logoLight : logoDark;

  const track = document.getElementById('theme-track');
  const label = document.getElementById('theme-label');
  const isLight = theme === 'light';

  if (track) track.classList.toggle('active', isLight);
  if (label) label.innerHTML = isLight
    ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg> Light'
    : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Dark';
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'dark';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

// Inicializa quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  // Escuta mudança de preferência do sistema
  window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', e => {
    if (!localStorage.getItem('acic_theme')) {
      applyTheme(e.matches ? 'light' : 'dark', false);
    }
  });
});

// ── UPLOAD DE IMAGEM ─────────────────────────────────────────
const UPLOAD_URL = _baseUrl + '/upload.php';

async function handleImageUpload(input) {
  const file = input.files[0];
  if (!file) return;

  // Preview imediato
  const reader = new FileReader();
  reader.onload = (e) => {
    const prev = document.getElementById('fp-img-preview');
    const placeholder = document.getElementById('upload-placeholder');
    if (prev) { prev.src = e.target.result; prev.style.display = 'block'; }
    if (placeholder) placeholder.style.display = 'none';
  };
  reader.readAsDataURL(file);

  // Upload
  const progress = document.getElementById('upload-progress');
  const fill     = document.getElementById('upload-fill');
  const status   = document.getElementById('upload-status');
  if (progress) progress.classList.remove('hidden');

  const formData = new FormData();
  formData.append('imagem', file);
  formData.append('token', getToken());

  try {
    // Simula progresso
    let pct = 0;
    const timer = setInterval(() => {
      pct = Math.min(pct + 15, 85);
      if (fill)   fill.style.width = pct + '%';
      if (status) status.textContent = 'Enviando... ' + pct + '%';
    }, 200);

    const res  = await fetch(UPLOAD_URL, {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + getToken() },
      body: formData,
    });
    const data = await res.json();
    clearInterval(timer);

    if (data.success) {
      if (fill)   { fill.style.width = '100%'; fill.style.background = '#2ECC71'; }
      if (status) status.textContent = '✓ Upload concluído';
      document.getElementById('fp-imagem').value = data.data.url;
      setTimeout(() => { if (progress) progress.classList.add('hidden'); }, 1500);
    } else {
      if (status) status.textContent = '✗ ' + (data.message || 'Erro no upload');
      if (fill)   fill.style.background = '#E24B4A';
    }
  } catch (e) {
    if (status) status.textContent = '✗ Erro de conexão';
    if (fill)   fill.style.background = '#E24B4A';
  }
}

// ── UPLOAD GALERIA ───────────────────────────────────────────
async function handleGaleriaUpload(input) {
  const files   = Array.from(input.files);
  if (!files.length) return;
  const produtoId = document.getElementById('fp-id').value;
  if (!produtoId) { notify.aviso('Salve o produto primeiro antes de adicionar fotos.'); return; }

  const progress = document.getElementById('galeria-upload-progress');
  const fill     = document.getElementById('galeria-upload-fill');
  const status   = document.getElementById('galeria-upload-status');
  if (progress) progress.classList.remove('hidden');

  let done = 0;
  for (const file of files) {
    if (status) status.textContent = `Enviando ${done+1} de ${files.length}...`;

    const formData = new FormData();
    formData.append('imagem', file);
    formData.append('token', getToken());

    try {
      const res  = await fetch(UPLOAD_URL, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + getToken() },
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        // Registra no banco
        await prodApi('imagem_add', {
          produto_id: produtoId,
          url:        data.data.url,
          nome:       file.name,
          token:      getToken(),
        });
      } else {
        console.error('Upload erro:', data.message);
      }
    } catch(e) {
      console.error('Upload falhou:', e);
    }
    done++;
    if (fill) fill.style.width = (done / files.length * 100) + '%';
  }

  if (status) status.textContent = `✓ ${done} foto(s) adicionada(s)`;
  if (fill)   fill.style.background = '#2ECC71';
  setTimeout(() => {
    if (progress) progress.classList.add('hidden');
    if (fill) { fill.style.width = '0'; fill.style.background = 'var(--accent)'; }
  }, 1800);

  // Recarrega galeria
  carregarGaleriaAdmin(produtoId);
  input.value = ''; // limpa input para permitir reenvio do mesmo arquivo
}

// ── Sincroniza preview da capa ──��────────────────────────────
function atualizarPreviewCapa(url) {
  const prev    = document.getElementById('fp-img-preview');
  const empty   = document.getElementById('capa-preview-empty');
  if (!prev) return;
  if (url) {
    prev.src = url;
    prev.style.display = 'block';
    if (empty) empty.style.display = 'none';
  } else {
    prev.style.display = 'none';
    if (empty) empty.style.display = 'flex';
  }
}

// ════════════════════════════════════════════════════════════
// SUPERADMIN — Usuários & Métricas
// ════════════════════════════════════════════════════════════
let _chartAcessos = null;
const ADMIN_URL = _baseUrl + '/admin.php';

async function adminApi(action, params = {}, method = 'POST') {
  const token = getToken();
  const opts  = method === 'GET'
    ? { headers: { 'Authorization': 'Bearer ' + token } }
    : { method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
        body: JSON.stringify({ ...params, token }) };
  const sep = ADMIN_URL.includes('?') ? '&' : '?';
  const url = method === 'GET'
    ? `${ADMIN_URL}${sep}action=${action}&` + new URLSearchParams(params)
    : `${ADMIN_URL}${sep}action=${action}`;
  const res  = await fetch(url, opts);
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'Erro');
  return data.data;
}

// ── Revela menus admin ────────────────────────────────────
function revelarMenusAdmin() {
  ['nav-metricas'].forEach(id => {
    document.getElementById(id)?.classList.remove('hidden');
  });
}





// ============================================================



// ============================================================
// DASHBOARD PERSONALIZADO POR PERFIL
// ============================================================
function renderDashboardPerfil(d) {
  const session = getSession() || {};
  const role = session.role || session.tipo || 'associado_empresa';
  const isAdmin = session.is_admin || role === 'superadmin' || role === 'gestor';
  const nome = d.nomeFantasia !== '\u2014' ? d.nomeFantasia : (d.razaoSocial || session.nome || 'Associado');
  const heroEl = document.getElementById('dash-hero-perfil');
  if (heroEl) heroEl.innerHTML = '';

  // Topbar: titulo + saudacao
  const topTitle = document.querySelector('.topbar-title');
  if (topTitle) {
    const label = isAdmin ? 'Painel de Gest\u00e3o' : 'Portal do Associado';
    const sub = role === 'colaborador' ? 'Colaborador' : role === 'dependente' ? 'Dependente' : '';
    topTitle.innerHTML = '<div style="line-height:1.2"><div style="font-size:16px;font-weight:700;color:var(--text)">' + label + '</div><div style="font-size:12px;font-weight:500;color:var(--text2)">Bem-vindo, ' + nome + (sub ? ' \u2014 ' + sub : '') + '</div></div>';
  }

  // Mostrar boxes para admin e empresa, ocultar para colab/dep
  document.querySelectorAll('.dash-admin-only').forEach(el => {
    el.style.display = (isAdmin || role === 'associado_empresa') ? '' : 'none';
  });
  const greet = document.getElementById('dash-greeting');
  if (greet && greet.closest('.section-header')) greet.closest('.section-header').style.display = 'none';
}



// ============================================================
// CARTEIRINHA — SPA (sem page reload)
// ============================================================
async function loadCarteirinha() {
  const container = document.getElementById('carteirinha-content');
  if (!container) return;

  const session = getSession() || {};
  const nome = session.nome || 'Associado';
  const doc = session.documento || session.cpf_cnpj || session.cpf || '';
  const isAdmin = session.is_admin || false;
  const status = isAdmin ? 'ativo' : (session.status || 'ativo');
  const plano = isAdmin ? 'Administrador' : (session.plano || session.plano_nome || 'Associado');
  const validade = isAdmin ? null : (session.data_vencimento || null);
  const desde = isAdmin ? 'ACIC-DF' : (session.data_associacao || '');

  const isAtivo = status === 'ativo';
  const badgeSt = isAtivo ? 'background:#dcfce7;color:#166534' : 'background:#fee2e2;color:#991b1b';
  const badgeTxt = isAtivo ? 'ASSOCIADO ATIVO' : status.toUpperCase();

  const qrData = JSON.stringify({ doc, nome, plano, validade, src:'acic-conecta' });

  let qrSrc = '';
  try {
    if (typeof QRCode !== 'undefined') {
      const qd = document.createElement('div');
      new QRCode(qd, { text: qrData, width: 200, height: 200, colorDark: '#1B2B6B', colorLight: '#ffffff' });
      await new Promise(r => setTimeout(r, 150));
      const cv = qd.querySelector('canvas');
      if (cv) qrSrc = cv.toDataURL('image/png');
      else { const im = qd.querySelector('img'); if (im) qrSrc = im.src; }
    }
  } catch(e) {}

  container.innerHTML =
    '<div id="carteirinha-card" style="background:linear-gradient(135deg,#1B2B6B 0%,#1a3a7a 50%,#2d4a9a 100%);border-radius:20px;padding:28px 24px 24px;color:#fff;max-width:440px;margin:0 auto 24px;position:relative;overflow:hidden;box-shadow:0 8px 32px rgba(26,43,74,.3)">' +
      '<div style="position:absolute;top:-60px;right:-60px;width:180px;height:180px;background:rgba(232,112,26,.15);border-radius:50%"></div>' +
      '<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;position:relative;z-index:1">' +
        '<img src="/conecta/uploads/logo-dark-320.png?v=2" alt="ACIC" style="height:32px">' +
        '<div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;opacity:.8">Carteira Digital do Associado</div>' +
      '</div>' +
      '<div style="font-size:22px;font-weight:800;margin-bottom:4px;position:relative;z-index:1">' + nome + '</div>' +
      '<div style="font-size:13px;opacity:.75;margin-bottom:16px;position:relative;z-index:1">' + doc + '</div>' +
      '<div style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;' + badgeSt + ';position:relative;z-index:1">' + badgeTxt + '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;position:relative;z-index:1">' +
        '<div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Plano</div><div style="font-size:14px;font-weight:600">' + plano + '</div></div>' +
        '<div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Associado Desde</div><div style="font-size:14px;font-weight:600">' + (desde || '\u2014') + '</div></div>' +
        '<div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Validade</div><div style="font-size:14px;font-weight:600">' + (validade || 'Administrador') + '</div></div>' +
        '<div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;opacity:.6;margin-bottom:2px">Status</div><div style="font-size:14px;font-weight:600">' + badgeTxt + '</div></div>' +
      '</div>' +
      '<div style="text-align:center;padding-top:16px;border-top:1px solid rgba(255,255,255,.12);position:relative;z-index:1">' +
        '<div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:10px;font-weight:600">QR Code de Valida\u00e7\u00e3o</div>' +
        (qrSrc ? '<img src="' + qrSrc + '" width="200" height="200" style="display:block;margin:0 auto;border-radius:8px;background:#fff;padding:8px">' : '<div id="qr-spa"></div>') +
      '</div>' +
    '</div>' +
    '<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">' +
      '<button onclick="downloadCarteirinha()" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#E8701A;color:#fff">Baixar</button>' +
      '<button onclick="shareCarteirinha()" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text)">Compartilhar</button>' +
    '</div>';

  if (!qrSrc && typeof QRCode !== 'undefined') {
    const el = document.getElementById('qr-spa');
    if (el) new QRCode(el, { text: qrData, width: 200, height: 200, colorDark: '#1B2B6B', colorLight: '#ffffff' });
  }
}

function downloadCarteirinha() {
  const el = document.getElementById('carteirinha-card');
  if (!el || typeof html2canvas === 'undefined') return;
  html2canvas(el, { scale: 2, useCORS: true, backgroundColor: null }).then(c => {
    const a = document.createElement('a');
    a.download = 'carteirinha-acic.png';
    a.href = c.toDataURL('image/png');
    a.click();
  });
}

function shareCarteirinha() {
  const el = document.getElementById('carteirinha-card');
  if (!el) return;
  if (navigator.share && typeof html2canvas !== 'undefined') {
    html2canvas(el, { scale: 2, useCORS: true, backgroundColor: null }).then(c => {
      c.toBlob(blob => {
        const file = new File([blob], 'carteirinha-acic.png', { type: 'image/png' });
        navigator.share({ title: 'Carteirinha ACIC-DF', files: [file] }).catch(() => {});
      });
    });
  } else downloadCarteirinha();
}

// ============================================================
// COBRANCAS — SPA (sem page reload)
// ============================================================
async function loadCobrancas() {
  const container = document.getElementById('cobrancas-content');
  if (!container) return;

  const token = getToken();
  if (!token) {
    container.innerHTML = '<div style="text-align:center;padding:48px 24px;color:var(--text3)"><h3 style="color:var(--text)">Sess\u00e3o expirada</h3><p>Fa\u00e7a login novamente.</p></div>';
    return;
  }

  container.innerHTML = '<div style="text-align:center;padding:40px"><div class="sp" style="width:24px;height:24px;border-width:2px;margin:0 auto"></div></div>';

  try {
    const res = await fetch(AUTH_URL + '?action=cobrancas', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Erro');
    _renderCobrancasSPA(json.data.cobrancas || []);
  } catch(e) {
    container.innerHTML =
      '<div style="text-align:center;padding:48px 24px;color:var(--text3)">' +
      '<h3 style="color:var(--text)">Erro ao carregar</h3><p>' + (e.message || 'Erro') + '</p>' +
      '<button onclick="loadCobrancas()" style="margin-top:12px;padding:8px 20px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);cursor:pointer">Tentar novamente</button></div>';
  }
}

function _renderCobrancasSPA(list) {
  const container = document.getElementById('cobrancas-content');
  const money = v => 'R$ ' + Number(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
  const fmtD = d => { if(!d)return'\u2014'; const p=d.split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d; };
  const cls = c => {
    if(c.status==='pago'||c.status==='paid')return'paid';
    if(!c.data_vencimento)return'pending';
    const t=new Date();t.setHours(0,0,0,0);
    return new Date(c.data_vencimento+'T00:00:00')<t?'overdue':'pending';
  };
  const badge = c => c==='paid'?'Pago':c==='overdue'?'Vencido':'Pendente';
  const colors = {paid:'#22c55e',pending:'#E8701A',overdue:'#ef4444'};

  if (!list.length) {
    container.innerHTML = '<div style="text-align:center;padding:48px 24px;color:var(--text3)"><h3 style="color:var(--text)">Nenhuma cobran\u00e7a</h3><p>Suas cobran\u00e7as aparecer\u00e3o aqui.</p></div>';
    return;
  }

  const pagas = list.filter(c => cls(c)==='paid').length;
  const venc = list.filter(c => cls(c)==='overdue').length;
  const total = list.reduce((s,c) => s+Number(c.valor||0), 0);

  let html = '<div style="background:linear-gradient(135deg,#1B2B6B 0%,#2d3f8a 100%);border-radius:16px;padding:24px 22px;color:#fff;margin-bottom:22px">' +
    '<div style="font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.85;margin-bottom:4px">Resumo Financeiro</div>' +
    '<div style="font-size:28px;font-weight:800">' + money(total) + '</div>' +
    '<div style="font-size:12px;opacity:.7;margin-top:2px">Total em cobran\u00e7as</div>' +
    '<div style="display:flex;gap:18px;margin-top:16px"><div style="text-align:center"><div style="font-size:20px;font-weight:700">' + pagas + '</div><div style="font-size:11px;opacity:.7">Pagas</div></div>' +
    '<div style="text-align:center"><div style="font-size:20px;font-weight:700">' + venc + '</div><div style="font-size:11px;opacity:.7">Vencidas</div></div></div></div>';

  list.forEach(c => {
    const st = cls(c);
    const canPay = (st==='pending'||st==='overdue') && c.gateway_url;
    const desc = c.descricao || c.plano_nome || 'Cobran\u00e7a ACIC-DF';
    html += '<div style="background:var(--surface);border-radius:14px;padding:18px 20px;margin-bottom:12px;border-left:4px solid '+colors[st]+'">' +
      '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">' +
        '<div style="flex:1"><div style="font-weight:600;font-size:14px;color:var(--text);margin-bottom:4px">' + desc + '</div>' +
        '<div style="font-size:12px;color:var(--text3)">Venc: ' + fmtD(c.data_vencimento) + ' <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;margin-left:6px;' +
          (st==='paid'?'background:#dcfce7;color:#166534':st==='overdue'?'background:#fee2e2;color:#991b1b':'background:#fef3c7;color:#92400e') + '">' + badge(st) + '</span></div></div>' +
        '<div style="font-size:18px;font-weight:700;color:var(--text)">' + money(c.valor) + '</div>' +
      '</div>' +
      (canPay ? '<div style="margin-top:12px"><a href="' + c.gateway_url + '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:#fff;background:' + (st==='overdue'?'#ef4444':'#E8701A') + '">Pagar</a></div>' : '') +
    '</div>';
  });

  container.innerHTML = html;
}


let _lastPermRefresh = 0;
async function refreshPermissoes() {
  if (Date.now() - _lastPermRefresh < 300000) return;
  _lastPermRefresh = Date.now();
  const token = getToken();
  if (!token) return;
  try {
    const res = await fetch(_baseUrl + '/auth.php?action=permissoes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token })
    });
    const json = await res.json();
    if (json.success && json.data && json.data.modulos) {
      const sess = getSession() || {};
      sess.modulos = json.data.modulos;
      setSession(sess);
      aplicarModulosSidebar(json.data.modulos);
    }
  } catch(e) {}
}

function aplicarModulosSidebar(modulos) {
  const map = {
    'dashboard':   'button[onclick*="dashboard"]',
    'catalogo':    'button[onclick*="catalogo"]',
    'carteirinha': 'button[onclick*="carteirinha"]',
    'cobrancas':   'button[onclick*="cobrancas"]',
    'empresa':     'button[onclick*="empresa"]',
    'metricas':    '#nav-metricas',
    'comunicados': '#nav-comunicados',
    'produtos':    '#nav-admin',
    'categorias':  '#nav-categorias',
    'parceiros':   '#nav-parceiros',
  };
  Object.entries(map).forEach(([mod, sel]) => {
    const el = document.querySelector(sel);
    if (!el) return;
    if (modulos.includes(mod)) {
      el.className = el.className.replace(/hidden/g, '').trim();
      el.setAttribute('style', 'display:flex !important');
    } else {
      el.setAttribute('style', 'display:none !important');
    }
  });
}

async function carregarMetricas() {
  const session = getSession() || {};
  const role = session.role || session.tipo || '';
  const isAdmin = session.is_admin || role === 'superadmin' || role === 'gestor';
  const container = document.querySelector('#section-admin-metricas');
  if (!container) return;

  try {
    if (isAdmin) {
      const m = await adminApi('metricas', {}, 'GET');
      const el = id => document.getElementById(id);
      if (el('met-total')) el('met-total').textContent = m.totais?.total_usuarios || 0;
      if (el('met-sem-acesso')) el('met-sem-acesso').textContent = m.sem_acesso?.length || 0;
      if (el('met-views')) el('met-views').textContent = m.produtos?.total_views || 0;
      if (el('met-clicks')) el('met-clicks').textContent = m.produtos?.total_clicks || 0;
      renderGraficoAcessos(m.acessos_30d || []);
      renderTopClicks(m.top_clicks || []);
      renderSemAcesso(m.sem_acesso || []);
    } else {
      // Ocultar HTML admin
      container.querySelectorAll('.stats-grid,.metricas-grid,.dash-card').forEach(el => el.style.display = 'none');

      let dynEl = document.getElementById('metricas-dinamico');
      if (!dynEl) {
        dynEl = document.createElement('div');
        dynEl.id = 'metricas-dinamico';
        const sh = container.querySelector('.section-header');
        if (sh) sh.after(dynEl); else container.prepend(dynEl);
      }

      if (role === 'associado_empresa') {
        dynEl.innerHTML = '<div style="text-align:center;padding:20px"><div class="sp" style="width:24px;height:24px;border-width:2px;margin:0 auto"></div></div>';

        const token = getToken();
        let cobrancas = [];
        try {
          const res = await fetch(AUTH_URL + '?action=cobrancas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
          });
          const json = await res.json();
          if (json.success) cobrancas = json.data.cobrancas || [];
        } catch(e) {}

        let produtos = [];
        try { produtos = (await prodApi('listar', { limit: 50 }, 'GET')).produtos || []; } catch(e) {}
        produtos.sort((a,b) => (b.views+b.clicks) - (a.views+a.clicks));

        const totalViews = produtos.reduce((s,p) => s + (p.views||0), 0);
        const totalClicks = produtos.reduce((s,p) => s + (p.clicks||0), 0);
        const pagas = cobrancas.filter(c => c.status === 'pago').length;
        const pendentes = cobrancas.filter(c => c.status !== 'pago' && c.status !== 'cancelado').length;

        dynEl.innerHTML =
          '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px">' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:18px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Cobran\u00e7as Pagas</div><div style="font-size:26px;font-weight:800;color:#22c55e">' + pagas + '</div></div>' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:18px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Pendentes</div><div style="font-size:26px;font-weight:800;color:#E8701A">' + pendentes + '</div></div>' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:18px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Total Views</div><div style="font-size:26px;font-weight:800;color:var(--text)">' + totalViews + '</div></div>' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:18px;border:1px solid var(--border)"><div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Total Cliques</div><div style="font-size:26px;font-weight:800;color:var(--text)">' + totalClicks + '</div></div>' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:20px;border:1px solid var(--border)">' +
              '<div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:14px">Produtos Mais Acessados</div>' +
              (produtos.length === 0 ? '<div style="text-align:center;padding:16px;color:var(--text3)">Nenhum produto</div>'
              : '<div style="display:grid;gap:8px">' + produtos.slice(0,5).map(function(p,i) {
                  var bg = i===0?'#E8701A':i===1?'#1B2B6B':'var(--border)';
                  return '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;background:var(--surface2,#f8f9fa)"><div style="width:24px;height:24px;border-radius:6px;background:'+bg+';color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">'+(i+1)+'</div><div style="flex:1;min-width:0"><div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+(p.nome||'')+'</div><div style="font-size:11px;color:var(--text3)">'+(p.categoria_nome||'')+'</div></div><div style="text-align:right;font-size:11px;color:var(--text3)">'+(p.views||0)+' views<br>'+(p.clicks||0)+' clicks</div></div>';
                }).join('') + '</div>') +
            '</div>' +
            '<div style="background:var(--surface,#fff);border-radius:12px;padding:20px;border:1px solid var(--border)">' +
              '<div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:14px">Hist\u00f3rico de Cobran\u00e7as</div>' +
              (cobrancas.length === 0 ? '<div style="text-align:center;padding:16px;color:var(--text3)">Nenhuma cobran\u00e7a</div>'
              : '<div style="display:grid;gap:8px">' + cobrancas.slice(0,5).map(function(co) {
                  var st = co.status==='pago'?'#22c55e':co.status==='pendente'?'#E8701A':'#ef4444';
                  return '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:8px;background:var(--surface2,#f8f9fa)"><div><div style="font-size:13px;font-weight:600;color:var(--text)">'+(co.descricao||co.plano_nome||'Cobran\u00e7a')+'</div><div style="font-size:11px;color:var(--text3)">'+(co.data_vencimento||'')+'</div></div><div style="text-align:right"><div style="font-size:13px;font-weight:700;color:var(--text)">R$ '+Number(co.valor||0).toFixed(2).replace('.',',')+'</div><div style="font-size:10px;font-weight:600;color:'+st+';text-transform:uppercase">'+(co.status||'')+'</div></div></div>';
                }).join('') + '</div>') +
            '</div>' +
          '</div>';
      } else {
        dynEl.innerHTML = '<div style="background:var(--surface,#fff);border-radius:12px;padding:24px;border:1px solid var(--border);text-align:center"><div style="font-size:40px;margin-bottom:8px">\ud83d\udcca</div><div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">Suas M\u00e9tricas</div><div style="font-size:13px;color:var(--text3)">Explore o cat\u00e1logo para ver produtos e benef\u00edcios.</div></div>';
      }
    }
  } catch(e) {
    console.error('Metricas:', e.message);
  }
}

function renderGraficoAcessos(dados) {
  const ctx = document.getElementById('chart-acessos');
  if (!ctx || typeof Chart === 'undefined') return;

  const labels = dados.map(d => {
    const dt = new Date(d.dia);
    return dt.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});
  });
  const values = dados.map(d => parseInt(d.total));

  const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
  const accent = '#E8640A';
  const grid   = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
  const text   = isDark ? '#7A8EAA' : '#4A6080';

  if (typeof _chartAcessos !== 'undefined' && _chartAcessos) _chartAcessos.destroy();
  _chartAcessos = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data:            values,
        borderColor:     accent,
        backgroundColor: 'rgba(232,100,10,.08)',
        borderWidth:     2,
        pointRadius:     values.length > 20 ? 0 : 3,
        pointBackgroundColor: accent,
        tension:         0.4,
        fill:            true,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, maxTicksLimit: 10 } },
        y: { grid: { color: grid }, ticks: { color: text, font: { size: 11 }, stepSize: 1 }, beginAtZero: true },
      }
    }
  });
}

function renderTopClicks(lista) {
  const el = document.getElementById('top-clicks-list');
  if (!el) return;
  if (!lista.length) { el.innerHTML = '<p style="color:var(--text3);font-size:13px">Sem dados.</p>'; return; }
  const max = Math.max(...lista.map(p => parseInt(p.clicks)||0), 1);
  el.innerHTML = lista.map(p => `
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <span style="font-size:13px;font-weight:600;color:var(--text)">${p.nome}</span>
        <span style="font-size:12px;color:var(--text3)">${p.clicks||0} cliques</span>
      </div>
      <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:${Math.round((parseInt(p.clicks)||0)/max*100)}%;background:var(--accent);border-radius:3px;transition:width .6s ease"></div>
      </div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px">${p.categoria||''} · ${p.views||0} views</div>
    </div>
  `).join('');
}

function renderSemAcesso(lista) {
  const el = document.getElementById('sem-acesso-list');
  if (!el) return;
  if (!lista.length) { el.innerHTML = '<p style="color:var(--success);font-size:13px">✓ Todos os associados já acessaram o portal!</p>'; return; }
  el.innerHTML = `
    <div style="margin-bottom:10px;font-size:12px;color:var(--text3)">${lista.length} associado(s) nunca acessaram</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px">
      ${lista.map(u => `
        <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border)">
          <div class="sb-avatar" style="width:28px;height:28px;font-size:12px;flex-shrink:0;background:var(--border);color:var(--text3)">${u.cpf_cnpj[0]}</div>
          <div style="min-width:0">
            <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${u.cpf_cnpj}</div>
            <div style="font-size:10px;color:var(--text3)">Desde ${new Date(u.created_at).toLocaleDateString('pt-BR')}</div>
          </div>
        </div>
      `).join('')}
    </div>`;
}

// ============================================================
// SISTEMA DE NOTIFICAÇÕES
// ============================================================
const NOTIF_URL = _baseUrl + '/notificacoes.php';
let _notifAberto    = false;
let _notifInterval  = null;
let _notifData      = [];

// Inicia polling de notificações após login
function iniciarNotificacoes() {
  // Pequeno delay garante que token está disponível após transição de tela
  setTimeout(carregarNaoLidas, 500);
  _notifInterval = setInterval(carregarNaoLidas, 60000); // a cada 1 min
  // Fecha painel ao clicar fora
  document.addEventListener('click', (e) => {
    if (_notifAberto && !document.getElementById('notif-wrap')?.contains(e.target)) {
      fecharNotifPanel();
    }
  });
}

function pararNotificacoes() {
  if (_notifInterval) clearInterval(_notifInterval);
}

// Carrega apenas a contagem de não lidas (leve)
async function carregarNaoLidas() {
  if (!getToken()) return;
  try {
    const res  = await fetch(NOTIF_URL + '?action=nao_lidas', {
      headers: { 'Authorization': 'Bearer ' + getToken() }
    });
    const data = await res.json();
    if (data.success) atualizarBadge(data.data.total ?? data.data.count ?? 0);
  } catch(e) { /* silencioso */ }
}

// Carrega lista completa de notificações
async function carregarNotificacoes() {
  if (!getToken()) return;
  const lista = document.getElementById('notif-lista');
  if (!lista) return;
  lista.innerHTML = '<div class="notif-empty"><div class="spinner" style="width:20px;height:20px;margin:0 auto"></div></div>';
  try {
    const res  = await fetch(NOTIF_URL + '?action=listar', {
      headers: { 'Authorization': 'Bearer ' + getToken() }
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    _notifData = data.data.notificacoes || [];
    atualizarBadge(data.data.nao_lidas || 0);
    renderNotificacoes(_notifData);
  } catch(e) {
    lista.innerHTML = `<div class="notif-empty" style="color:var(--danger)">${e.message}</div>`;
  }
}

// Cache de notificações para lookup seguro por ID
const _notifCache = new Map();

// Cache de produtos para lookup seguro por ID (evita JSON no onclick)
const _prodCache = new Map();

function renderNotificacoes(lista) {
  const el = document.getElementById('notif-lista');
  if (!el) return;
  if (!lista.length) {
    el.innerHTML = '<div class="notif-empty">Nenhum comunicado por enquanto 🎉</div>';
    return;
  }
  const icones = { info:'ℹ️', aviso:'⚠️', alerta:'🔔', sucesso:'✅' };
  // Armazena no cache para abrirModalComunicado usar por ID
  lista.forEach(n => _notifCache.set(n.id, n));
  el.innerHTML = lista.map(n => `
    <div class="notif-item ${n.lida ? '' : 'nao-lida'}" onclick="abrirModalComunicadoPorId(${n.id})">
      <div class="notif-icone ${n.tipo}">${icones[n.tipo]||'🔔'}</div>
      <div class="notif-corpo">
        <div class="notif-titulo-item">${n.titulo}</div>
        <div class="notif-msg">${(n.mensagem||'').replace(/<[^>]*>/g,'').substring(0,80)}${(n.mensagem||'').length>80?'...':''}</div>
        <div class="notif-tempo">${tempoRelativo(n.created_at)}</div>
      </div>
    </div>
  `).join('');
}

function abrirModalComunicadoPorId(id) {
  const n = _notifCache.get(id);
  if (n) abrirModalComunicado(n);
}

function abrirModalComunicado(n) {
  const modal  = document.getElementById('comunicado-modal');
  const body   = document.getElementById('comunicado-modal-body');
  const header = document.getElementById('comunicado-modal-header');
  const tipoBadge = document.getElementById('comunicado-modal-tipo');
  if (!modal || !body) return;

  const cfg = {
    info:   { cor:'#378ADD', bg:'rgba(55,138,221,.1)',  label:'Informativo', icone:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' },
    aviso:  { cor:'#E8640A', bg:'rgba(232,100,10,.1)',  label:'Aviso',       icone:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' },
    alerta: { cor:'#E24B4A', bg:'rgba(226,75,74,.1)',   label:'Alerta',      icone:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' },
    sucesso:{ cor:'#1D9E75', bg:'rgba(29,158,117,.1)',  label:'Sucesso',     icone:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' },
  };
  const c = cfg[n.tipo] || cfg.info;

  // Header colorido
  if (header) {
    header.style.background = c.bg;
    header.style.borderBottom = `1px solid ${c.cor}22`;
    header.style.padding = '20px 28px 16px';
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.justifyContent = 'space-between';
  }

  // Badge de tipo
  if (tipoBadge) {
    tipoBadge.innerHTML = `<span style="display:inline-flex;align-items:center;gap:6px;color:${c.cor};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px">${c.icone} ${c.label}</span>`;
  }

  // Detectar se mensagem é HTML ou texto puro
  const isHtml = /<[a-z][\s\S]*>/i.test(n.mensagem);

  body.innerHTML = `
    <h2 style="font-size:19px;font-weight:800;color:var(--text);margin:0 0 16px;line-height:1.3;font-family:var(--font-display)">${n.titulo}</h2>
    <div class="comunicado-mensagem-body">${isHtml ? n.mensagem : (n.mensagem||'').split('\n').join('<br>')}</div>
    ${n.link && n.link !== 'null' && n.link !== '' ? `
      <div style="margin-top:20px">
        <a href="${n.link}" target="_blank" rel="noopener" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;width:auto;padding:10px 20px;font-size:13px;text-decoration:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Ver mais
        </a>
      </div>` : ''}
    <div style="margin-top:20px;padding-top:14px;border-top:1px solid var(--border);display:flex;align-items:center;gap:8px">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span style="font-size:11px;color:var(--text3)">${tempoRelativo(n.created_at)}</span>
    </div>
  `;

  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  fecharNotifPanel();

  // Marca como lida sem fechar o modal
  try {
    fetch(NOTIF_URL + '?action=marcar_lida', {
      method:'POST',
      headers:{'Content-Type':'application/json','Authorization':'Bearer '+getToken()},
      body: JSON.stringify({id: n.id})
    });
    carregarNaoLidas();
  } catch(e) {}
}

function fecharModalComunicado() {
  const modal = document.getElementById('comunicado-modal');
  if (!modal) return;
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

function atualizarBadge(count) {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = 'flex';
    badge.classList.add('pulse');
  } else {
    badge.style.display = 'none';
    badge.classList.remove('pulse');
  }
}

function toggleNotifPanel() {
  if (_notifAberto) {
    fecharNotifPanel();
  } else {
    abrirNotifPanel();
  }
}

function abrirNotifPanel() {
  const panel   = document.getElementById('notif-panel');
  const overlay = document.getElementById('notif-overlay');
  if (!panel) return;
  panel.classList.remove('hidden');
  if (overlay) overlay.classList.add('active');
  if (window.innerWidth <= 768) document.body.style.overflow = 'hidden';
  _notifAberto = true;
  carregarNotificacoes();
}

// Versão do sistema
const SISTEMA_VERSAO = 'v1.1.0';
function renderVersao() {
  const el = document.getElementById('sistema-versao');
  if (el) el.textContent = SISTEMA_VERSAO;
}

function fecharNotifPanel() {
  const panel   = document.getElementById('notif-panel');
  const overlay = document.getElementById('notif-overlay');
  if (!panel) return;
  panel.classList.add('hidden');
  if (overlay) overlay.classList.remove('active');
  document.body.style.overflow = '';
  _notifAberto = false;
}

async function clicarNotif(id, link) {
  // Marca como lida
  try {
    await fetch(NOTIF_URL + '?action=marcar_lida', {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'Authorization':'Bearer '+getToken() },
      body: JSON.stringify({ id })
    });
    // Atualiza visual
    const item = document.querySelector(`.notif-item[onclick*="${id}"]`);
    if (item) item.classList.remove('nao-lida');
    carregarNaoLidas();
  } catch(e) { /* silencioso */ }

  // Navega se tiver link
  if (link && link !== 'null' && link !== '') {
    window.open(link, '_blank');
    fecharNotifPanel();
  }
}

async function marcarTodasLidas() {
  try {
    await fetch(NOTIF_URL + '?action=marcar_todas', {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'Authorization':'Bearer '+getToken() },
      body: JSON.stringify({})
    });
    atualizarBadge(0);
    carregarNotificacoes();
  } catch(e) { notify.erro(e.message); }
}

// Tempo relativo
function tempoRelativo(dataStr) {
  const diff = Date.now() - new Date(dataStr).getTime();
  const min  = Math.floor(diff / 60000);
  if (min < 1)  return 'Agora mesmo';
  if (min < 60) return `${min} min atrás`;
  const h = Math.floor(min / 60);
  if (h < 24)   return `${h}h atrás`;
  const d = Math.floor(h / 24);
  if (d < 7)    return `${d} dia${d>1?'s':''} atrás`;
  return new Date(dataStr).toLocaleDateString('pt-BR');
}

// ── TOAST NOTIFICATION SYSTEM ──
const _toastIcons = {
  sucesso: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  erro:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
  aviso:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  alerta:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
};
function _getToastContainer() {
  let c = document.getElementById('toast-container');
  if (!c) { c = document.createElement('div'); c.id = 'toast-container'; c.className = 'toast-container'; document.body.appendChild(c); }
  return c;
}
function mostrarToast(titulo, mensagem, tipo = 'info', duracao = 5000) {
  // Mapear tipo legado 'alerta' → 'erro'
  if (tipo === 'alerta') tipo = 'erro';
  const container = _getToastContainer();
  const toast = document.createElement('div');
  toast.className = `toast toast--${tipo}`;
  const escapedMsg = String(mensagem || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const escapedTit = titulo ? String(titulo).replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
  toast.innerHTML = `
    <div class="toast-icon">${_toastIcons[tipo] || _toastIcons.info}</div>
    <div class="toast-body">
      ${escapedTit ? `<div class="toast-titulo">${escapedTit}</div>` : ''}
      <div class="toast-msg">${escapedMsg}</div>
    </div>
    <button class="toast-close" aria-label="Fechar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="toast-progress" style="width:100%"></div>
  `;
  toast.querySelector('.toast-close').onclick = () => removerToast(toast);
  container.appendChild(toast);
  // Animate progress bar
  const bar = toast.querySelector('.toast-progress');
  bar.style.transitionDuration = duracao + 'ms';
  requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = '0%'; }));
  // Max 5 visible toasts
  const all = container.querySelectorAll('.toast:not(.saindo)');
  if (all.length > 5) removerToast(all[0]);
  setTimeout(() => removerToast(toast), duracao);
}
function removerToast(toast) {
  if (!toast || toast.classList.contains('saindo')) return;
  toast.classList.add('saindo');
  setTimeout(() => toast.remove(), 280);
}
// Convenience shortcuts
const notify = {
  sucesso: (msg, titulo) => mostrarToast(titulo || 'Sucesso', msg, 'sucesso'),
  erro:    (msg, titulo) => mostrarToast(titulo || 'Erro', msg, 'erro', 7000),
  aviso:   (msg, titulo) => mostrarToast(titulo || 'Atenção', msg, 'aviso', 6000),
  info:    (msg, titulo) => mostrarToast(titulo || '', msg, 'info')
};

// ============================================================
// ADMIN — PARCEIROS
// ============================================================
let _parceirosCache = [];

async function popularSelectParceiros(selectedId) {
  const sel = document.getElementById('fp-parceiro');
  if (!sel) return;
  sel.innerHTML = '<option value="">Sem parceiro</option>';
  try {
    if (!_parceirosCache.length) {
      const lista = await adminApi('parceiros', {}, 'GET');
      _parceirosCache = lista || [];
    }
    _parceirosCache.filter(p => p.ativo).forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.nome;
      if (String(p.id) === String(selectedId)) opt.selected = true;
      sel.appendChild(opt);
    });
  } catch(e) { /* silencioso */ }
}

async function carregarParceiros() {
  const grid = document.getElementById('parceiros-grid');
  if (!grid) return;
  grid.innerHTML = '<div style="text-align:center;color:var(--text3);padding:40px;grid-column:1/-1"><div class="spinner" style="width:20px;height:20px;margin:0 auto 8px"></div></div>';
  try {
    const lista = await adminApi('parceiros', {}, 'GET');
    _parceirosCache = lista || [];
    if (!lista || !lista.length) {
      grid.innerHTML = '<div style="text-align:center;color:var(--text3);padding:40px;grid-column:1/-1"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:.4"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg><div style="font-weight:600;margin-bottom:4px">Nenhum parceiro cadastrado</div><div style="font-size:12px">Clique em "+ Novo Parceiro" para começar.</div></div>';
      return;
    }
    grid.innerHTML = lista.map(p => {
      const initials = escHtml((p.nome || '').substring(0,2).toUpperCase());
      const catColors = {
        'Saúde':'#10B981','Tecnologia':'#3B82F6','Financeiro':'#8B5CF6','Seguros':'#F59E0B',
        'Educação':'#EC4899','Jurídico':'#6366F1','Contabilidade':'#14B8A6','Marketing':'#F97316'
      };
      const catColor = catColors[p.categoria] || 'var(--accent)';
      return `
      <div class="card" style="padding:18px;border-radius:14px;border:1px solid var(--border);background:var(--surface);transition:var(--trans);position:relative${!p.ativo ? ';opacity:.55' : ''}">
        <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--surface2);border:2px solid var(--border);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
            ${p.logo_url ? `<img src="${escHtml(p.logo_url)}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display=''"><span style="display:none;font-size:18px;font-weight:700;color:var(--text3)">${initials}</span>` : `<span style="font-size:18px;font-weight:700;color:var(--text3)">${initials}</span>`}
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(p.nome_fantasia || p.nome)}</div>
            ${p.categoria ? `<span style="display:inline-block;font-size:10px;padding:2px 8px;border-radius:99px;background:${catColor}18;color:${catColor};font-weight:600;margin-top:3px">${escHtml(p.categoria)}</span>` : ''}
          </div>
          <span style="font-size:9px;padding:3px 8px;border-radius:99px;background:${p.ativo ? 'rgba(29,158,117,.12)' : 'rgba(226,75,74,.12)'};color:${p.ativo ? '#1D9E75' : '#E24B4A'};font-weight:600;white-space:nowrap">${p.ativo ? 'Ativo' : 'Inativo'}</span>
        </div>
        ${p.cnpj ? `<div style="font-size:11.5px;color:var(--text3);margin-bottom:4px">CNPJ: ${escHtml(p.cnpj)}</div>` : ''}
        ${parseFloat(p.split_percentual) > 0 ? `<div style="font-size:11px;color:var(--accent);font-weight:600;margin-bottom:4px">Split: ${parseFloat(p.split_percentual).toFixed(1)}%</div>` : ''}
        ${p.email ? `<div style="font-size:11.5px;color:var(--text2);display:flex;align-items:center;gap:4px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>${escHtml(p.email)}</div>` : ''}
        <div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
          <button onclick="editarParceiro(${p.id})" style="flex:1;font-size:12px;padding:7px 0;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text);cursor:pointer;font-weight:500;transition:var(--trans)">Editar</button>
          <button onclick="toggleParceiro(${p.id})" style="flex:1;font-size:12px;padding:7px 0;border-radius:8px;border:1px solid ${p.ativo ? 'rgba(226,75,74,.3)' : 'rgba(29,158,117,.3)'};background:transparent;color:${p.ativo ? '#E24B4A' : '#1D9E75'};cursor:pointer;font-weight:500;transition:var(--trans)">${p.ativo ? 'Desativar' : 'Ativar'}</button>
        </div>
      </div>`;
    }).join('');
  } catch(e) { grid.innerHTML = `<div style="color:var(--danger);padding:20px;grid-column:1/-1">Erro: ${e.message}</div>`; }
}

function abrirFormParceiro(p = null) {
  document.getElementById('parceiro-form-titulo').textContent = p ? 'Editar Parceiro' : 'Novo Parceiro';
  document.getElementById('parc-id').value = p?.id || '';
  document.getElementById('parc-nome').value = p?.nome || '';
  document.getElementById('parc-fantasia').value = p?.nome_fantasia || '';
  document.getElementById('parc-cnpj').value = p?.cnpj || '';
  document.getElementById('parc-categoria').value = p?.categoria || '';
  document.getElementById('parc-email').value = p?.email || '';
  document.getElementById('parc-telefone').value = p?.telefone || '';
  document.getElementById('parc-site').value = (p?.site || '').replace(/^https?:\/\//,'');
  document.getElementById('parc-logo').value = p?.logo_url || '';
  document.getElementById('parc-desc').value = p?.descricao || '';
  document.getElementById('parc-split').value = p?.split_percentual || 0;
  // Logo preview
  const prev = document.getElementById('parc-logo-preview');
  const ph = document.getElementById('parc-logo-placeholder');
  if (p?.logo_url) { prev.src = p.logo_url; prev.style.display = 'block'; if(ph) ph.style.display='none'; }
  else { prev.style.display = 'none'; if(ph) ph.style.display=''; }
  // Open drawer
  document.getElementById('drawer-parceiro-overlay').classList.remove('hidden');
  document.getElementById('drawer-parceiro').style.right = '0';
  document.body.style.overflow = 'hidden';
}

function fecharFormParceiro() {
  document.getElementById('drawer-parceiro').style.right = '-500px';
  document.getElementById('drawer-parceiro-overlay').classList.add('hidden');
  document.body.style.overflow = '';
}

// Logo preview on URL change
document.addEventListener('DOMContentLoaded', () => {
  const inp = document.getElementById('parc-logo');
  if (inp) inp.addEventListener('input', () => {
    const prev = document.getElementById('parc-logo-preview');
    const ph = document.getElementById('parc-logo-placeholder');
    if (inp.value.trim()) {
      prev.src = inp.value.trim();
      prev.style.display = 'block';
      if(ph) ph.style.display = 'none';
      prev.onerror = () => { prev.style.display = 'none'; if(ph) ph.style.display = ''; };
    } else { prev.style.display = 'none'; if(ph) ph.style.display = ''; }
  });
});

async function salvarParceiro() {
  const id = document.getElementById('parc-id').value;
  const nome = document.getElementById('parc-nome').value.trim();
  if (!nome) { notify.aviso('Nome do parceiro é obrigatório.'); return; }
  const btn = document.getElementById('btn-salvar-parceiro');
  btn.disabled = true; btn.textContent = 'Salvando...';
  try {
    const body = {
      id: id || undefined,
      nome,
      nome_fantasia: document.getElementById('parc-fantasia').value.trim(),
      cnpj: document.getElementById('parc-cnpj').value.trim(),
      categoria: document.getElementById('parc-categoria').value.trim(),
      email: document.getElementById('parc-email').value.trim(),
      telefone: document.getElementById('parc-telefone').value.trim(),
      site: document.getElementById('parc-site').value.trim(),
      logo_url: document.getElementById('parc-logo').value.trim(),
      descricao: document.getElementById('parc-desc').value.trim(),
      split_percentual: parseFloat(document.getElementById('parc-split').value) || 0,
    };
    await adminApi(id ? 'parceiro_editar' : 'parceiro_criar', body);
    fecharFormParceiro();
    carregarParceiros();
    mostrarToast('Parceiro salvo', id ? 'Parceiro atualizado.' : 'Novo parceiro criado.', 'sucesso');
  } catch(e) { notify.erro(e.message); }
  finally { btn.disabled = false; btn.textContent = 'Salvar Parceiro'; }
}

function editarParceiro(id) {
  const p = _parceirosCache.find(x => x.id === id);
  if (p) abrirFormParceiro(p);
}

async function toggleParceiro(id) {
  try {
    await adminApi('parceiro_toggle', { id });
    carregarParceiros();
  } catch(e) { notify.erro(e.message); }
}

// ============================================================
// ADMIN — COMBOS (vínculo plano CRM → produtos)
// ============================================================
let _combosCache = [];
let _comboEditando = null;

async function carregarCombos() {
  const tbody = document.getElementById('admin-combos-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:20px"><div class="spinner" style="width:18px;height:18px;margin:0 auto"></div></td></tr>';
  try {
    const lista = await prodApi('combo_listar');
    _combosCache = lista || [];
    if (!lista || !lista.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:24px">Nenhum combo cadastrado. Clique em "Novo Combo" para vincular planos a produtos.</td></tr>';
      return;
    }
    tbody.innerHTML = lista.map(c => `
      <tr>
        <td><strong>${escHtml(c.nome)}</strong></td>
        <td><code style="font-size:12px;background:var(--surface2);padding:2px 8px;border-radius:4px">${escHtml(c.plano_crm)}</code></td>
        <td style="text-align:center"><span style="background:var(--accent);color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:99px">${c.total_itens || 0}</span></td>
        <td><span style="font-size:11px;padding:3px 10px;border-radius:99px;background:${c.ativo == 1 ? 'rgba(29,158,117,.12)' : 'rgba(226,75,74,.12)'};color:${c.ativo == 1 ? '#1D9E75' : '#E24B4A'};font-weight:600">${c.ativo == 1 ? 'Ativo' : 'Inativo'}</span></td>
        <td>
          <div style="display:flex;gap:6px">
            <button class="btn-table-action" onclick="editarCombo(${c.id})">Editar</button>
            <button class="btn-table-action danger" onclick="confirmarExcluirCombo(${c.id},'${escHtml(c.nome).replace(/'/g,"\\'")}')">Excluir</button>
          </div>
        </td>
      </tr>`).join('');
  } catch(e) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--danger);padding:20px">Erro: ${e.message}</td></tr>`; }
}

function abrirFormCombo(c = null) {
  _comboEditando = c;
  document.getElementById('combo-form-titulo').textContent = c ? 'Editar Combo' : 'Novo Combo';
  document.getElementById('combo-id').value = c?.id || '';
  document.getElementById('combo-nome').value = c?.nome || '';
  document.getElementById('combo-plano-crm').value = c?.plano_crm || '';
  document.getElementById('combo-descricao').value = c?.descricao || '';
  document.getElementById('combo-ativo').checked = c ? c.ativo == 1 : true;
  const itensSection = document.getElementById('combo-itens-section');
  if (c && c.id) { itensSection.style.display = ''; carregarItensCombo(c.id); }
  else { itensSection.style.display = 'none'; }
  document.getElementById('drawer-combo-overlay').classList.remove('hidden');
  document.getElementById('drawer-combo').style.right = '0';
  document.body.style.overflow = 'hidden';
}

function fecharFormCombo() {
  document.getElementById('drawer-combo').style.right = '-560px';
  document.getElementById('drawer-combo-overlay').classList.add('hidden');
  document.body.style.overflow = '';
  _comboEditando = null;
}

async function salvarCombo() {
  const nome = document.getElementById('combo-nome').value.trim();
  const plano = document.getElementById('combo-plano-crm').value.trim();
  if (!nome || !plano) { notify.aviso('Nome e Plano CRM são obrigatórios.'); return; }
  const btn = document.getElementById('btn-salvar-combo');
  btn.disabled = true; btn.textContent = 'Salvando...';
  try {
    const id = document.getElementById('combo-id').value;
    const body = {
      id: id || undefined,
      nome,
      plano_crm: plano,
      descricao: document.getElementById('combo-descricao').value.trim(),
      ativo: document.getElementById('combo-ativo').checked ? 1 : 0,
    };
    const action = id ? 'combo_editar' : 'combo_criar';
    const res = await prodApi(action, body);
    notify.sucesso(id ? 'Combo atualizado!' : 'Combo criado!');
    if (!id && res.id) {
      document.getElementById('combo-id').value = res.id;
      document.getElementById('combo-itens-section').style.display = '';
      _comboEditando = { ...body, id: res.id };
      carregarItensCombo(res.id);
    } else { fecharFormCombo(); }
    carregarCombos();
  } catch(e) { notify.erro(e.message); }
  finally { btn.disabled = false; btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:5px"><polyline points="20 6 9 17 4 12"/></svg> Salvar Combo'; }
}

async function editarCombo(id) {
  const c = _combosCache.find(x => x.id == id);
  if (c) abrirFormCombo(c);
}

async function confirmarExcluirCombo(id, nome) {
  if (!confirm(`Excluir combo "${nome}" e todos os vínculos? Esta ação não pode ser desfeita.`)) return;
  try {
    await prodApi('combo_excluir', { id });
    notify.sucesso('Combo excluído.');
    carregarCombos();
  } catch(e) { notify.erro(e.message); }
}

async function carregarItensCombo(comboId) {
  const lista = document.getElementById('combo-itens-lista');
  lista.innerHTML = '<div style="text-align:center;padding:12px"><div class="spinner" style="width:16px;height:16px;margin:0 auto"></div></div>';
  try {
    const itens = await prodApi('combo_itens', { combo_id: comboId }, 'GET');
    if (!itens || !itens.length) {
      lista.innerHTML = '<p style="text-align:center;color:var(--text3);font-size:13px;padding:12px">Nenhum produto vinculado</p>';
      return;
    }
    lista.innerHTML = itens.map(i => `
      <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
        ${i.produto_imagem ? `<img src="${escHtml(i.produto_imagem)}" style="width:40px;height:40px;border-radius:8px;object-fit:cover">` : '<div style="width:40px;height:40px;border-radius:8px;background:var(--border);display:flex;align-items:center;justify-content:center"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>'}
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px;color:var(--text)">${escHtml(i.produto_nome)}${i.subproduto_nome ? ' → <span style="color:var(--accent)">' + escHtml(i.subproduto_nome) + '</span>' : ''}</div>
          <div style="font-size:11px;color:var(--text3)">${i.incluido == 1 ? '✓ Incluído grátis' : 'Desconto: ' + parseFloat(i.desconto_percentual).toFixed(0) + '%'}</div>
        </div>
        <button onclick="removerItemCombo(${i.id},${comboId})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:4px" title="Remover">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>`).join('');
  } catch(e) { lista.innerHTML = `<p style="color:var(--danger);font-size:12px">${e.message}</p>`; }
}

async function abrirAddItemCombo() {
  const sel = document.getElementById('combo-item-produto');
  sel.innerHTML = '<option value="">Selecione um produto...</option>';
  try {
    const prods = await prodApi('admin_listar');
    prods.forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.nome; sel.appendChild(o); });
  } catch(e) { notify.erro('Erro ao carregar produtos: ' + e.message); return; }
  document.getElementById('combo-item-subproduto').innerHTML = '<option value="">Produto inteiro</option>';
  document.getElementById('combo-item-sub-wrap').style.display = 'none';
  document.getElementById('combo-item-incluido').checked = true;
  document.getElementById('combo-item-desconto').value = 0;
  document.getElementById('modal-combo-item').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function fecharAddItemCombo() {
  document.getElementById('modal-combo-item').classList.add('hidden');
  document.body.style.overflow = 'hidden'; // drawer still open
}

async function carregarSubprodutosCombo(produtoId) {
  const wrap = document.getElementById('combo-item-sub-wrap');
  const sel = document.getElementById('combo-item-subproduto');
  sel.innerHTML = '<option value="">Produto inteiro</option>';
  if (!produtoId) { wrap.style.display = 'none'; return; }
  try {
    const det = await prodApi('detalhe', { id: produtoId }, 'GET');
    if (det.subprodutos && det.subprodutos.length) {
      det.subprodutos.forEach(s => { const o = document.createElement('option'); o.value = s.id; o.textContent = s.nome + (s.preco ? ` (R$ ${parseFloat(s.preco).toFixed(2)})` : ''); sel.appendChild(o); });
      wrap.style.display = '';
    } else { wrap.style.display = 'none'; }
  } catch(e) { wrap.style.display = 'none'; }
}

async function salvarItemCombo() {
  const comboId = document.getElementById('combo-id').value;
  const produtoId = document.getElementById('combo-item-produto').value;
  if (!comboId || !produtoId) { notify.aviso('Selecione um produto.'); return; }
  try {
    await prodApi('combo_item_add', {
      combo_id: parseInt(comboId),
      produto_id: parseInt(produtoId),
      subproduto_id: document.getElementById('combo-item-subproduto').value || null,
      incluido: document.getElementById('combo-item-incluido').checked ? 1 : 0,
      desconto_percentual: parseFloat(document.getElementById('combo-item-desconto').value) || 0,
    });
    notify.sucesso('Produto vinculado ao combo!');
    fecharAddItemCombo();
    carregarItensCombo(comboId);
    carregarCombos();
  } catch(e) { notify.erro(e.message); }
}

async function removerItemCombo(itemId, comboId) {
  if (!confirm('Remover este produto do combo?')) return;
  try {
    await prodApi('combo_item_remove', { id: itemId });
    carregarItensCombo(comboId);
    carregarCombos();
  } catch(e) { notify.erro(e.message); }
}

// ============================================================
// ADMIN — ENVIAR NOTIFICAÇÃO
// ============================================================
async function abrirModalNotif() {
  const modal = document.getElementById('modal-enviar-notif');
  if (!modal) return;
  // Carrega lista de usuários como opções
  const sel = document.getElementById('notif-dest');
  if (sel && sel.options.length === 1) {
    try {
      const users = await adminApi('usuarios', {}, 'GET');
      users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = u.doc_fmt || u.cpf_cnpj;
        sel.appendChild(opt);
      });
    } catch(e) { /* usa só broadcast */ }
  }
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function fecharModalNotif() {
  const modal = document.getElementById('modal-enviar-notif');
  if (modal) modal.classList.add('hidden');
  document.body.style.overflow = '';
}

async function enviarNotificacao(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-enviar-notif');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  const dest = document.getElementById('notif-dest').value;
  const body = {
    tipo:          document.getElementById('notif-tipo').value,
    titulo:        document.getElementById('notif-titulo').value,
    mensagem:      document.getElementById('notif-mensagem').value,
    link:          document.getElementById('notif-link').value || null,
    user_id:       dest === 'todos' ? null : dest,
    enviar_email:  document.getElementById('notif-email').checked,
  };

  try {
    const res  = await fetch(NOTIF_URL + '?action=enviar', {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'Authorization':'Bearer '+getToken() },
      body: JSON.stringify(body)
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    fecharModalNotif();
    mostrarToast('Notificação enviada', `${data.data.enviadas} notificação(ões) enviada(s).`, 'sucesso');
    // Limpa formulário
    document.getElementById('notif-titulo').value   = '';
    document.getElementById('notif-mensagem').value = '';
    document.getElementById('notif-link').value     = '';
    document.getElementById('notif-email').checked  = false;
  } catch(err) {
    notify.erro(err.message, 'Erro ao enviar');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Enviar notificação';
  }
}

// Integra iniciarNotificacoes() ao showPortal
const _origShowPortal = showPortal;
// (já chamado via loadPortalData — iniciarNotificacoes é chamado após login)

// ════════════════════════════════════════════════════════════


// ============================================================
// ADMIN CATEGORIAS
// ============================================================
async function loadAdminCategorias() {
  const section = document.getElementById('section-admin-categorias');
  if (!section) return;
  section.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text3)"><div class="spinner" style="width:24px;height:24px;margin:0 auto 8px"></div></div>';

  try {
    const token = getToken();
    const res = await fetch(_baseUrl + '/produtos.php?action=categorias_admin', {
      headers: { 'Authorization': 'Bearer ' + token }
    });
    const json = await res.json();
    if (!json.success || !json.data) { section.innerHTML = '<p style="text-align:center;color:var(--text3);padding:40px">Erro ao carregar categorias</p>'; return; }

    const cats = json.data;
    section.innerHTML =
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">' +
        '<h3 style="font-size:16px;font-weight:700;color:var(--text)">Categorias</h3>' +
        '<button class="btn-primary" onclick="criarCategoria()" style="width:auto;padding:8px 16px;font-size:12px"><i class="bi bi-plus-lg"></i> Nova Categoria</button>' +
      '</div>' +
      '<div style="display:grid;gap:8px">' + (cats.length === 0 ? '<div style="padding:32px;text-align:center;color:var(--text3)">Nenhuma categoria cadastrada.</div>' :
      cats.map(cat =>
        '<div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;transition:all .2s" onmouseenter="this.style.borderColor='var(--accent)'" onmouseleave="this.style.borderColor='var(--border)'">' +
          '<span style="font-size:22px;width:36px;text-align:center">' + (cat.icone||'\ud83d\udcc1') + '</span>' +
          '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:14px;color:var(--text)">' + cat.nome + '</div>' +
          '<div style="font-size:12px;color:var(--text3)">' + (cat.total_produtos||0) + ' produtos &middot; ' + (cat.ativo ? 'Ativa' : 'Inativa') + '</div></div>' +
          '<div style="display:flex;gap:6px">' +
            '<button class="btn-table-action" onclick="editarCategoria(' + cat.id + ',\'' + (cat.nome||'').replace(/'/g,"\\'") + '\',\'' + (cat.icone||'') + '\')"><i class="bi bi-pencil"></i></button>' +
            '<button class="btn-table-action danger" onclick="excluirCategoria(' + cat.id + ',\'' + (cat.nome||'').replace(/'/g,"\\'") + '\')"><i class="bi bi-trash"></i></button>' +
          '</div>' +
        '</div>'
      ).join('')) + '</div>';
  } catch(e) {
    section.innerHTML = '<p style="text-align:center;color:var(--danger);padding:40px">Erro: ' + e.message + '</p>';
  }
}

async function criarCategoria() {
  const nome = prompt('Nome da categoria:');
  if (!nome) return;
  const icone = prompt('Emoji/Icone (ex: \ud83d\udcbc):', '\ud83d\udcbc');
  try {
    await prodApi('categoria_criar', { nome, icone: icone || '', token: getToken() });
    mostrarToast('Categoria criada!', nome, 'sucesso');
    loadAdminCategorias();
  } catch(e) { mostrarToast('Erro', e.message, 'alerta'); }
}

async function editarCategoria(id, nome, icone) {
  const novoNome = prompt('Nome da categoria:', nome);
  if (!novoNome || novoNome === nome) {
    const novoIcone = prompt('Emoji/Icone:', icone);
    if (!novoIcone && !novoNome) return;
    try {
      await prodApi('categoria_editar', { id, nome: novoNome || nome, icone: novoIcone || icone, token: getToken() });
      mostrarToast('Categoria atualizada!', '', 'sucesso');
      loadAdminCategorias();
    } catch(e) { mostrarToast('Erro', e.message, 'alerta'); }
    return;
  }
  const novoIcone = prompt('Emoji/Icone:', icone);
  try {
    await prodApi('categoria_editar', { id, nome: novoNome, icone: novoIcone || icone, token: getToken() });
    mostrarToast('Categoria atualizada!', '', 'sucesso');
    loadAdminCategorias();
  } catch(e) { mostrarToast('Erro', e.message, 'alerta'); }
}

async function excluirCategoria(id, nome) {
  if (!confirm('Excluir categoria "' + nome + '"?')) return;
  try {
    await prodApi('categoria_excluir', { id, token: getToken() });
    mostrarToast('Categoria excluida', nome, 'sucesso');
    loadAdminCategorias();
  } catch(e) { mostrarToast('Erro', e.message, 'alerta'); }
}

// COMUNICADOS — Editor rico com busca de destinatários
// ════════════════════════════════════════════════════════════

let _comDestinatarios = []; // { id, label, todos: bool }
let _comUsuariosCache = [];
let _comTipoSelecionado = 'info';
let _comRascunhoId = null;

// ── Inicializa a seção ──────────────────────────────────────
async function iniciarComunicados() {
  // Garante estado inicial correto do editor
  novoRascunho();
  // Pré-carrega lista de usuários para busca (sempre recarrega)
  try {
    _comUsuariosCache = await adminApi('usuarios', {}, 'GET');
  } catch(e) { console.error('Erro ao carregar usuários:', e); }
  atualizarContadorDest();
  carregarHistoricoComunicados();
}

// ── Novo rascunho — limpa o editor ─────────────────────────
function novoRascunho() {
  _comRascunhoId = null;
  _comDestinatarios = [{ id: 'todos', label: 'Todos os associados', todos: true }];
  _comTipoSelecionado = 'info';
  document.getElementById('com-titulo').value = '';
  document.getElementById('com-mensagem').value = '';
  document.getElementById('com-link').value = '';
  document.getElementById('canal-portal').checked = true;
  document.getElementById('canal-email').checked = false;
  renderTagsDest();
  document.querySelectorAll('.tipo-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.tipo === 'info');
  });
  document.getElementById('editor-status-label').textContent = 'Novo comunicado';
  document.getElementById('com-preview').innerHTML = '<p style="color:var(--text3);font-style:italic;font-size:12px">Preencha o assunto e a mensagem para ver o preview...</p>';
  atualizarContadorDest();
}

// ── Tags de destinatários ───────────────────────────────────
function renderTagsDest() {
  const wrap = document.getElementById('dest-tags-wrap');
  const input = document.getElementById('dest-busca');
  // Remove tags antigas (mantém o input)
  wrap.querySelectorAll('.dest-tag').forEach(t => t.remove());

  _comDestinatarios.forEach(d => {
    const tag = document.createElement('span');
    tag.className = d.todos ? 'dest-tag dest-tag-todos' : 'dest-tag dest-tag-user';
    tag.innerHTML = d.todos
      ? `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> ${d.label} <button type="button" onclick="removerDest('${d.id}',event)" style="background:none;border:none;cursor:pointer;color:inherit;padding:0;margin-left:4px;font-size:13px;opacity:.7">×</button>`
      : `${d.label} <button type="button" onclick="removerDest('${d.id}',event)" style="background:none;border:none;cursor:pointer;color:var(--text3);padding:0;margin-left:4px;font-size:13px">×</button>`;
    wrap.insertBefore(tag, input);
  });
  atualizarContadorDest();
}

function removerDest(id, e) {
  e?.stopPropagation();
  _comDestinatarios = _comDestinatarios.filter(d => String(d.id) !== String(id));
  renderTagsDest();
}

function toggleTodos(e) { e?.stopPropagation(); }

function removerTodos(e) {
  e?.stopPropagation();
  removerDest('todos', e);
}

function atualizarContadorDest() {
  const temTodos = _comDestinatarios.some(d => d.todos);
  const label = temTodos
    ? 'Todos os associados'
    : `${_comDestinatarios.length} destinatário(s)`;
  const el = document.getElementById('com-dest-count');
  if (el) el.textContent = label;
}

// ── Busca de destinatários — AJAX com debounce ──────────────
let _destDebounceTimer = null;

function buscarDestinatarios(q) {
  const lista = document.getElementById('dest-lista');
  const wrap  = document.getElementById('dest-sugestoes');
  if (!q.trim()) { wrap.style.display = 'none'; return; }

  // Atalho: digitar "todos" mostra opção de broadcast
  const termo = q.toLowerCase().trim();
  if ('todos os associados'.includes(termo) || termo === 'todos') {
    const jaTodos = _comDestinatarios.some(d => d.todos);
    if (!jaTodos) {
      lista.innerHTML = `<div class="dest-sugestao-item" onclick="adicionarTodosViaAjax()">
        <div class="dest-sugestao-avatar" style="background:var(--accent)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div style="font-weight:600">Todos os associados</div>
          <div style="font-size:11px;color:var(--text3)">Enviar para toda a base</div>
        </div>
      </div>`;
      wrap.style.display = 'block';
      return;
    }
  }

  // Mostra loading imediato
  lista.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:var(--text3);display:flex;align-items:center;gap:8px"><div class="spinner" style="width:14px;height:14px;flex-shrink:0"></div>Buscando...</div>';
  wrap.style.display = 'block';

  // Debounce 300ms
  clearTimeout(_destDebounceTimer);
  _destDebounceTimer = setTimeout(async () => {
    try {
      const res = await fetch(`${ADMIN_URL}?action=buscar_associado&q=${encodeURIComponent(q)}`, {
        headers: { 'Authorization': 'Bearer ' + getToken() }
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);

      const jaAdicionados = new Set(_comDestinatarios.map(d => String(d.id)));
      const resultados = (data.data || []).filter(u => !jaAdicionados.has(String(u.id)));

      if (!resultados.length) {
        lista.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:var(--text3)">Nenhum associado encontrado</div>';
      } else {
        lista.innerHTML = resultados.map(u => {
          const nomeRazao = u.nome || '';
          const doc       = u.doc_fmt || u.cpf_cnpj;
          const inicial   = (nomeRazao || doc)[0]?.toUpperCase() || '?';
          const tipo      = u.tipo === 'empresa' || (u.cpf_cnpj?.length > 11) ? 'Empresa' : 'Contribuinte';
          const labelPrincipal = nomeRazao || doc;
          const labelSec       = nomeRazao ? doc : tipo;
          return `<div class="dest-sugestao-item" onclick="adicionarDest(${u.id},'${escHtml(nomeRazao || doc)}')">
            <div class="dest-sugestao-avatar">${inicial}</div>
            <div>
              <div style="font-weight:600">${escHtml(labelPrincipal)}</div>
              <div style="font-size:11px;color:var(--text3)">${escHtml(labelSec)}</div>
            </div>
          </div>`;
        }).join('');
      }
      wrap.style.display = 'block';
    } catch(e) {
      lista.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:var(--danger)">Erro ao buscar. Tente novamente.</div>';
    }
  }, 300);
}

function mostrarSugestoes() {
  const q = document.getElementById('dest-busca')?.value || '';
  if (q.trim()) buscarDestinatarios(q);
}

function adicionarTodosViaAjax() {
  _comDestinatarios = [{ id: 'todos', label: 'Todos os associados', todos: true }];
  document.getElementById('dest-busca').value = '';
  document.getElementById('dest-sugestoes').style.display = 'none';
  renderTagsDest();
  atualizarContadorDest();
}

function adicionarDest(id, label) {
  // Remove "todos" se adicionar específico
  _comDestinatarios = _comDestinatarios.filter(d => !d.todos);
  if (!_comDestinatarios.find(d => String(d.id) === String(id))) {
    _comDestinatarios.push({ id, label, todos: false });
  }
  document.getElementById('dest-busca').value = '';
  document.getElementById('dest-sugestoes').style.display = 'none';
  renderTagsDest();
}

// Fecha sugestões ao clicar fora
document.addEventListener('click', (e) => {
  const wrap = document.getElementById('dest-sugestoes');
  if (wrap && !wrap.contains(e.target) && e.target.id !== 'dest-busca') {
    wrap.style.display = 'none';
  }
});

// ── Seleção de tipo ─────────────────────────────────────────
function selecionarTipo(tipo, btn) {
  _comTipoSelecionado = tipo;
  document.querySelectorAll('.tipo-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  atualizarPreview();
}

// ── Preview em tempo real ───────────────────────────────────
function atualizarPreview() {
  const titulo   = document.getElementById('com-titulo')?.value || '';
  const mensagem = document.getElementById('com-mensagem')?.value || '';
  const link     = document.getElementById('com-link')?.value || '';
  const preview  = document.getElementById('com-preview');
  if (!preview) return;

  if (!titulo && !mensagem) {
    preview.innerHTML = '<p style="color:var(--text3);font-style:italic;font-size:12px">Preencha o assunto e a mensagem para ver o preview...</p>';
    return;
  }

  const cores = { info:'#3b82f6', aviso:'#f59e0b', alerta:'#ef4444', sucesso:'#22c55e' };
  const icones = { info:'ℹ️', aviso:'⚠️', alerta:'🔔', sucesso:'✅' };
  const cor = cores[_comTipoSelecionado] || cores.info;
  const icone = icones[_comTipoSelecionado] || 'ℹ️';

  preview.innerHTML = `
    <div style="border-left:3px solid ${cor};padding:10px 14px;background:${cor}0d;border-radius:0 6px 6px 0;margin-bottom:10px">
      <div style="font-size:11px;font-weight:700;color:${cor};text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">${icone} ${_comTipoSelecionado}</div>
      <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px">${escHtml(titulo) || '<em style="color:var(--text3)">Sem assunto</em>'}</div>
      <div style="font-size:12px;color:var(--text2);line-height:1.5;white-space:pre-wrap">${escHtml(mensagem)}</div>
      ${link ? `<a href="${escHtml(link)}" style="display:inline-block;margin-top:8px;font-size:12px;color:${cor};font-weight:600">→ Acessar link</a>` : ''}
    </div>
    <div style="font-size:11px;color:var(--text3)">Assim é como o associado verá no portal</div>
  `;
}

// ── Salvar rascunho (local) ─────────────────────────────────
function salvarRascunho() {
  const titulo   = document.getElementById('com-titulo')?.value || '';
  const mensagem = document.getElementById('com-mensagem')?.value || '';
  if (!titulo && !mensagem) { mostrarToast('⚠️ Rascunho vazio', 'Escreva algo antes de salvar.', 'aviso'); return; }
  const rascunho = {
    titulo, mensagem,
    link:  document.getElementById('com-link')?.value || '',
    tipo:  _comTipoSelecionado,
    dest:  _comDestinatarios,
    email: document.getElementById('canal-email')?.checked,
    salvoEm: new Date().toISOString(),
  };
  localStorage.setItem('acic_rascunho_comunicado', JSON.stringify(rascunho));
  document.getElementById('editor-status-label').textContent = 'Rascunho salvo ✓';
  mostrarToast('✅ Rascunho salvo', 'Disponível nesta sessão do navegador.', 'sucesso');
}

// ── Enviar comunicado ───────────────────────────────────────
async function enviarComunicado() {
  const titulo   = document.getElementById('com-titulo')?.value?.trim();
  const mensagem = document.getElementById('com-mensagem')?.value?.trim();
  if (!titulo)   { mostrarToast('⚠️ Campo obrigatório', 'Informe o assunto.', 'aviso'); return; }
  if (!mensagem) { mostrarToast('⚠️ Campo obrigatório', 'Escreva a mensagem.', 'aviso'); return; }
  if (!_comDestinatarios.length) { mostrarToast('⚠️ Sem destinatários', 'Adicione pelo menos um destinatário.', 'aviso'); return; }

  const btn = document.getElementById('btn-enviar-comunicado');
  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;margin-right:6px"></span>Enviando...';

  // Se array vazio ou tem flag todos, trata como broadcast
  const temTodos = _comDestinatarios.length === 0 || _comDestinatarios.some(d => d.todos);
  const userIds  = temTodos ? null : _comDestinatarios.map(d => d.id);

  const body = {
    tipo:         _comTipoSelecionado,
    titulo,
    mensagem,
    link:         document.getElementById('com-link')?.value?.trim() || null,
    user_id:      userIds?.length === 1 ? userIds[0] : null,
    user_ids:     userIds,
    enviar_email: document.getElementById('canal-email')?.checked || false,
  };

  try {
    const res  = await fetch(NOTIF_URL + '?action=enviar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getToken() },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);

    const emailInfo = body.enviar_email ? ` + ${data.data.emails_enviados || 0} e-mail(s)` : '';
    mostrarToast('✅ Comunicado enviado!', `${data.data.enviadas} notificação(ões) entregues${emailInfo}.`, 'sucesso');
    document.getElementById('editor-status-label').textContent = 'Enviado ✓';
    localStorage.removeItem('acic_rascunho_comunicado');
    carregarHistoricoComunicados();
    // Limpa campos após 1s
    setTimeout(() => {
      novoRascunho();
    }, 1200);
  } catch(err) {
    mostrarToast('❌ Erro ao enviar', err.message, 'alerta');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Enviar agora';
  }
}

// ── Histórico de comunicados enviados ──────────────────────
async function carregarHistoricoComunicados() {
  const container = document.getElementById('comunicados-historico');
  if (!container) return;
  container.innerHTML = '<div style="padding:16px;font-size:13px;color:var(--text3);text-align:center"><div class="spinner" style="width:18px;height:18px;margin:0 auto 6px"></div>Carregando...</div>';
  try {
    const res  = await fetch(NOTIF_URL + '?action=historico', {
      headers: { 'Authorization': 'Bearer ' + getToken() }
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    renderHistoricoComunicados(data.data || []);
  } catch(e) {
    container.innerHTML = `<div style="padding:16px;font-size:13px;color:var(--danger)">Erro: ${e.message}</div>`;
  }
}

// Cache do histórico para recarregar sem JSON inline
const _histCache = new Map();

function renderHistoricoComunicados(lista) {
  const container = document.getElementById('comunicados-historico');
  if (!lista.length) {
    container.innerHTML = '<div style="padding:16px;font-size:13px;color:var(--text3);text-align:center">Nenhum comunicado enviado ainda.</div>';
    return;
  }
  const isAdmin = getSession()?.is_admin === true;
  const badges = { info:'badge-info', aviso:'badge-aviso', alerta:'badge-alerta', sucesso:'badge-sucesso' };
  lista.forEach(c => _histCache.set(c.id, c));

  container.innerHTML = lista.map(c => {
    const lidas = parseInt(c.total_lidas) || 0;
    const total = parseInt(c.total_dest) || 0;
    const destBadge = total <= 1 && lidas === 0
      ? `<span class="dest-badge dest-badge-todos">Todos</span>`
      : `<span class="dest-badge dest-badge-ind">${total} dest.</span>`;

    return `
    <div class="com-hist-item">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
        <span class="com-hist-badge ${badges[c.tipo] || 'badge-info'}">${c.tipo.toUpperCase()}</span>
        <div class="com-hist-titulo" style="flex:1;cursor:pointer" onclick="histRecarregar(${c.id})">${escHtml(c.titulo)}</div>
        ${isAdmin ? `<button type="button" class="btn-del-hist" onclick="excluirComunicado(${c.id})" title="Excluir">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>` : ''}
      </div>
      <div class="com-hist-meta" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
        <span>${tempoRelativo(c.created_at)}</span>
        <span style="color:var(--text3)">·</span>
        ${destBadge}
        ${c.enviou_email ? `<span class="dest-badge" style="background:rgba(55,138,221,.1);color:#378ADD;border-color:rgba(55,138,221,.3)">📧 e-mail</span>` : ''}
        <span style="color:var(--text3);font-size:11px">${lidas}/${total} lidos</span>
      </div>
    </div>`;
  }).join('');
}

function histRecarregar(id) {
  const c = _histCache.get(id);
  if (c) recarregarComunicado(c);
}

async function excluirComunicado(id) {
  if (!confirm('Excluir este comunicado? Os destinatários não poderão mais vê-lo.')) return;
  try {
    const res = await fetch(NOTIF_URL + '?action=excluir_comunicado', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + getToken() },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    mostrarToast('✅ Excluído', 'Comunicado removido com sucesso.', 'sucesso');
    _histCache.delete(id);
    carregarHistoricoComunicados();
  } catch(e) {
    mostrarToast('❌ Erro', e.message, 'alerta');
  }
}

function recarregarComunicado(c) {
  // Pré-preenche o editor com um comunicado anterior para reenviar/editar
  document.getElementById('com-titulo').value   = c.titulo || '';
  document.getElementById('com-mensagem').value = c.mensagem || '';
  document.getElementById('com-link').value     = c.link || '';
  _comTipoSelecionado = c.tipo || 'info';
  document.querySelectorAll('.tipo-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.tipo === _comTipoSelecionado);
  });
  // Reset destinatários para "todos"
  _comDestinatarios = [{ id: 'todos', label: 'Todos os associados', todos: true }];
  renderTagsDest();
  atualizarPreview();
  document.getElementById('editor-status-label').textContent = 'Reenviar comunicado';
  document.getElementById('com-titulo').focus();
}

// Capa hover overlay
document.getElementById('capa-preview-box')?.addEventListener('mouseenter', function() {
  const img = document.getElementById('fp-img-preview');
  const overlay = document.getElementById('capa-hover-overlay');
  if (img && img.style.display !== 'none' && overlay) overlay.style.display = 'flex';
});
document.getElementById('capa-preview-box')?.addEventListener('mouseleave', function() {
  const overlay = document.getElementById('capa-hover-overlay');
  if (overlay) overlay.style.display = 'none';
});
