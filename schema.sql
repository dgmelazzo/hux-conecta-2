-- ============================================================
-- CONECTA CRM — Schema Multi-Tenant v1.0
-- Stack: MySQL 8.0+
-- Convenção: tenant_id em TODAS as tabelas operacionais
-- Gerado: 2026-03-27
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ============================================================
-- 0. BANCO
-- ============================================================

CREATE DATABASE IF NOT EXISTS conecta_crm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE conecta_crm;

-- ============================================================
-- 1. TENANTS (associações)
-- Tabela-raiz. Sem tenant_id — ela É o tenant.
-- ============================================================

CREATE TABLE tenants (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(80)  NOT NULL UNIQUE,          -- ex: acicdf, acicamp
  nome          VARCHAR(180) NOT NULL,
  cnpj          VARCHAR(18)  UNIQUE,
  dominio       VARCHAR(120) UNIQUE,                   -- dominio white-label
  logo_url      VARCHAR(255),
  cor_primaria  VARCHAR(7)   DEFAULT '#2563EB',
  cor_secundaria VARCHAR(7)  DEFAULT '#1E40AF',
  plano_saas    ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_slug (slug),
  INDEX idx_dominio (dominio)
) ENGINE=InnoDB;

-- ============================================================
-- 2. USUÁRIOS INTERNOS (equipe da associação)
-- Roles: superadmin | gestor | atendente
-- superadmin não tem tenant_id (vê tudo)
-- ============================================================

CREATE TABLE usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED,                          -- NULL = superadmin HUX
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(180) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  role          ENUM('superadmin','gestor','atendente') NOT NULL DEFAULT 'atendente',
  avatar_url    VARCHAR(255),
  dois_fatores  TINYINT(1)   NOT NULL DEFAULT 0,
  totp_secret   VARCHAR(64),
  ultimo_login  DATETIME,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_tenant (email, tenant_id),
  INDEX idx_tenant (tenant_id),
  INDEX idx_role (role),
  CONSTRAINT fk_usuarios_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. SESSÕES JWT
-- Refresh tokens com revogação explícita
-- ============================================================

CREATE TABLE sessoes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NOT NULL,
  tenant_id     INT UNSIGNED NOT NULL,
  refresh_token VARCHAR(512) NOT NULL UNIQUE,
  ip            VARCHAR(45),
  user_agent    VARCHAR(255),
  expira_em     DATETIME     NOT NULL,
  revogado      TINYINT(1)   NOT NULL DEFAULT 0,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario_id),
  INDEX idx_tenant (tenant_id),
  INDEX idx_token (refresh_token(64)),
  CONSTRAINT fk_sessoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_sessoes_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. CONFIGURAÇÃO DE GATEWAY POR TENANT
-- Um tenant pode ter múltiplos gateways; apenas um ativo
-- ============================================================

CREATE TABLE gateway_configs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  gateway       ENUM('asaas','pagseguro','stripe','mercadopago','monex') NOT NULL,
  api_key       TEXT         NOT NULL,                 -- criptografada em app
  api_secret    TEXT,
  webhook_token VARCHAR(128),
  ambiente      ENUM('sandbox','producao') NOT NULL DEFAULT 'sandbox',
  ativo         TINYINT(1)   NOT NULL DEFAULT 0,
  testado_em    DATETIME,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_gateway (tenant_id, gateway),
  INDEX idx_tenant (tenant_id),
  CONSTRAINT fk_gateway_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. PLANOS / TAXAS ASSOCIATIVAS
-- MEI, ME, EPP, Isento, Combo, Personalizado
-- ============================================================

CREATE TABLE planos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  nome            VARCHAR(120) NOT NULL,               -- "MEI Anual", "Combo Pro"
  tipo            ENUM('mei','me','epp','isento','combo','personalizado') NOT NULL,
  descricao       TEXT,
  valor           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  periodicidade   ENUM('mensal','trimestral','semestral','anual') NOT NULL DEFAULT 'mensal',
  desconto_avista DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- % desconto pagamento à vista
  tem_link_publico TINYINT(1)  NOT NULL DEFAULT 1,
  slug_link       VARCHAR(80),                         -- /associe-se/{slug_link}
  conecta_produto_id INT UNSIGNED,                     -- FK para combo com Conecta 2.0
  ativo           TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  UNIQUE KEY uq_tenant_slug (tenant_id, slug_link),
  CONSTRAINT fk_planos_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. ASSOCIADOS
-- Pessoa física ou jurídica gerida pelo tenant
-- ============================================================

CREATE TABLE associados (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  plano_id        INT UNSIGNED,
  -- Identificação
  tipo_pessoa     ENUM('pf','pj') NOT NULL DEFAULT 'pj',
  razao_social    VARCHAR(180),                        -- PJ
  nome_fantasia   VARCHAR(180),
  nome_responsavel VARCHAR(120),                       -- PF ou responsável PJ
  cpf             VARCHAR(14),
  cnpj            VARCHAR(18),
  -- Contato
  email           VARCHAR(180),
  telefone        VARCHAR(20),
  whatsapp        VARCHAR(20),
  -- Endereço
  cep             VARCHAR(9),
  logradouro      VARCHAR(180),
  numero          VARCHAR(10),
  complemento     VARCHAR(80),
  bairro          VARCHAR(80),
  cidade          VARCHAR(80),
  uf              CHAR(2),
  -- Status e datas
  status          ENUM('ativo','inadimplente','suspenso','cancelado','prospecto') NOT NULL DEFAULT 'prospecto',
  data_associacao DATE,
  data_vencimento DATE,                                -- próximo vencimento da taxa
  -- Integração Conecta 2.0
  conecta_user_id INT UNSIGNED,                        -- ID em conecta_users (Conecta 2.0)
  higestor_id     VARCHAR(64),                         -- ID no HiGestor (se migrado)
  -- Campos customizáveis (JSON flexível)
  campos_extras   JSON,
  -- Controle
  importado_csv   TINYINT(1)   NOT NULL DEFAULT 0,
  criado_por      INT UNSIGNED,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_status (status),
  INDEX idx_plano (plano_id),
  INDEX idx_cnpj (cnpj),
  INDEX idx_cpf (cpf),
  INDEX idx_vencimento (data_vencimento),
  CONSTRAINT fk_associados_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_associados_plano
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. AUDIT LOG DE ASSOCIADOS
-- Toda alteração de campo crítico é registrada
-- ============================================================

CREATE TABLE associados_audit (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  associado_id  INT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED,
  acao          ENUM('criado','atualizado','status_alterado','importado','excluido') NOT NULL,
  campo         VARCHAR(80),                           -- campo alterado (se update)
  valor_antes   TEXT,
  valor_depois  TEXT,
  ip            VARCHAR(45),
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_associado (associado_id),
  INDEX idx_criado (criado_em)
) ENGINE=InnoDB;

-- ============================================================
-- 8. COBRANÇAS
-- Toda cobrança criada via PAL
-- ============================================================

CREATE TABLE cobrancas (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id         INT UNSIGNED NOT NULL,
  associado_id      INT UNSIGNED NOT NULL,
  plano_id          INT UNSIGNED,
  -- Gateway
  gateway           ENUM('asaas','pagseguro','stripe','mercadopago','monex') NOT NULL,
  gateway_charge_id VARCHAR(128),                      -- ID retornado pelo gateway
  gateway_url       VARCHAR(512),                      -- link de pagamento
  -- Valores
  valor             DECIMAL(10,2) NOT NULL,
  desconto          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  valor_pago        DECIMAL(10,2),
  modalidade        ENUM('boleto','pix','cartao','ted') NOT NULL DEFAULT 'pix',
  -- Datas
  data_vencimento   DATE         NOT NULL,
  data_pagamento    DATETIME,
  -- Status
  status            ENUM('pendente','pago','cancelado','estornado','falhou','expirado') NOT NULL DEFAULT 'pendente',
  -- Referência
  referencia        VARCHAR(120),                      -- ex: "Taxa MEI Jan/2026"
  descricao         TEXT,
  -- Webhook
  webhook_payload   JSON,                              -- último payload recebido
  webhook_em        DATETIME,
  -- Controle
  criado_por        INT UNSIGNED,
  criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_associado (associado_id),
  INDEX idx_status (status),
  INDEX idx_vencimento (data_vencimento),
  INDEX idx_gateway_id (gateway_charge_id),
  CONSTRAINT fk_cobrancas_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_cobrancas_associado
    FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. RÉGUA DE COBRANÇA (automação)
-- Sequência: D-7, D0, D+3, D+15...
-- ============================================================

CREATE TABLE regua_cobranca (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  nome          VARCHAR(120) NOT NULL,                 -- "Régua Padrão MEI"
  plano_id      INT UNSIGNED,                          -- NULL = aplica a todos
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  CONSTRAINT fk_regua_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE regua_passos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  regua_id      INT UNSIGNED NOT NULL,
  tenant_id     INT UNSIGNED NOT NULL,
  dias_offset   INT          NOT NULL,                 -- negativo = antes, positivo = depois
  canal         ENUM('email','whatsapp','ambos') NOT NULL DEFAULT 'ambos',
  template_id   INT UNSIGNED,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX idx_regua (regua_id),
  CONSTRAINT fk_passos_regua
    FOREIGN KEY (regua_id) REFERENCES regua_cobranca(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. PIPELINE DE PROSPECÇÃO (Kanban)
-- ============================================================

CREATE TABLE pipeline_estagios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  nome          VARCHAR(80)  NOT NULL,                 -- "Prospecção", "Proposta"...
  ordem         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cor           VARCHAR(7)   NOT NULL DEFAULT '#8B5CF6',
  eh_final_ganho TINYINT(1)  NOT NULL DEFAULT 0,
  eh_final_perdido TINYINT(1) NOT NULL DEFAULT 0,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  CONSTRAINT fk_estagios_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pipeline_prospectos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  estagio_id      INT UNSIGNED NOT NULL,
  plano_id        INT UNSIGNED,
  -- Dados do prospecto
  nome_empresa    VARCHAR(180) NOT NULL,
  cnpj            VARCHAR(18),
  nome_contato    VARCHAR(120),
  email           VARCHAR(180),
  whatsapp        VARCHAR(20),
  -- Comercial
  valor_estimado  DECIMAL(10,2),
  origem          ENUM('link_publico','indicacao','prospecção_ativa','evento','outro') DEFAULT 'outro',
  -- Kanban
  ordem_coluna    INT UNSIGNED NOT NULL DEFAULT 0,     -- posição dentro do estágio
  responsavel_id  INT UNSIGNED,                        -- usuario que gerencia
  ultimo_contato  DATETIME,
  proxima_acao    TEXT,
  proxima_acao_em DATE,
  -- Conversão
  convertido_em   DATETIME,
  associado_id    INT UNSIGNED,                        -- preenchido ao converter
  motivo_perda    TEXT,
  -- Origem do link público
  origem_slug     VARCHAR(80),
  -- Controle
  criado_em       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_estagio (estagio_id),
  INDEX idx_responsavel (responsavel_id),
  INDEX idx_ultimo_contato (ultimo_contato),
  CONSTRAINT fk_prospectos_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_prospectos_estagio
    FOREIGN KEY (estagio_id) REFERENCES pipeline_estagios(id),
  CONSTRAINT fk_prospectos_plano
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 11. HISTÓRICO DE RELACIONAMENTO (CRM)
-- Registra interações com associados E prospectos
-- ============================================================

CREATE TABLE relacionamento (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  -- Pode ser vinculado a associado OU prospecto (nunca ambos)
  associado_id    INT UNSIGNED,
  prospecto_id    INT UNSIGNED,
  usuario_id      INT UNSIGNED,
  tipo            ENUM('ligacao','email','whatsapp','reuniao','visita','nota','tarefa','sistema') NOT NULL,
  titulo          VARCHAR(180),
  descricao       TEXT,
  data_contato    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  duracao_min     SMALLINT UNSIGNED,                   -- duração em minutos (ligação/reunião)
  -- Tarefa
  eh_tarefa       TINYINT(1)   NOT NULL DEFAULT 0,
  tarefa_prazo    DATE,
  tarefa_concluida TINYINT(1)  NOT NULL DEFAULT 0,
  tarefa_concluida_em DATETIME,
  -- Referência a cobrança (ex: "Registrou inadimplência")
  cobranca_id     INT UNSIGNED,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_associado (associado_id),
  INDEX idx_prospecto (prospecto_id),
  INDEX idx_tipo (tipo),
  INDEX idx_data (data_contato),
  CONSTRAINT fk_rel_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_associado
    FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  CONSTRAINT fk_rel_prospecto
    FOREIGN KEY (prospecto_id) REFERENCES pipeline_prospectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 12. TEMPLATES DE COMUNICAÇÃO
-- E-mail e WhatsApp — reutilizados na régua e envios manuais
-- ============================================================

CREATE TABLE templates_mensagem (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  nome          VARCHAR(120) NOT NULL,
  canal         ENUM('email','whatsapp','ambos') NOT NULL,
  assunto       VARCHAR(255),                          -- só email
  corpo         TEXT         NOT NULL,                 -- HTML (email) ou texto (WhatsApp)
  variaveis     JSON,                                  -- ex: ["nome","valor","vencimento"]
  categoria     ENUM('cobranca','boas_vindas','renovacao','avulso') NOT NULL DEFAULT 'avulso',
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  CONSTRAINT fk_templates_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 13. ENVIOS (log de mensagens enviadas)
-- ============================================================

CREATE TABLE envios (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  template_id     INT UNSIGNED,
  canal           ENUM('email','whatsapp') NOT NULL,
  destinatario    VARCHAR(180) NOT NULL,               -- email ou número
  assunto         VARCHAR(255),
  corpo_enviado   TEXT,
  -- Contexto
  associado_id    INT UNSIGNED,
  cobranca_id     INT UNSIGNED,
  -- Status
  status          ENUM('enviado','entregue','lido','falhou') NOT NULL DEFAULT 'enviado',
  erro            TEXT,
  enviado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_associado (associado_id),
  INDEX idx_status (status),
  INDEX idx_enviado (enviado_em),
  CONSTRAINT fk_envios_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 14. RENOVAÇÕES
-- Controle de ciclo de vida da associação
-- ============================================================

CREATE TABLE renovacoes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  associado_id    INT UNSIGNED NOT NULL,
  plano_id        INT UNSIGNED,
  cobranca_id     INT UNSIGNED,
  periodo_inicio  DATE         NOT NULL,
  periodo_fim     DATE         NOT NULL,
  status          ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  renovado_em     DATETIME,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_associado (associado_id),
  INDEX idx_periodo (periodo_fim),
  CONSTRAINT fk_renovacoes_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_renovacoes_associado
    FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 15. COMBOS CONECTA 2.0
-- Vincula plano CRM ↔ produto do catálogo Conecta
-- ============================================================

CREATE TABLE conecta_combos (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id             INT UNSIGNED NOT NULL,
  plano_id              INT UNSIGNED NOT NULL,
  conecta_produto_id    INT UNSIGNED NOT NULL,         -- ID em conecta_produtos (Conecta 2.0)
  acesso_automatico     TINYINT(1)   NOT NULL DEFAULT 1, -- libera acesso ao pagar
  descricao             TEXT,
  ativo                 TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  UNIQUE KEY uq_plano_produto (plano_id, conecta_produto_id),
  CONSTRAINT fk_combos_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_combos_plano
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 16. WEBHOOKS RECEBIDOS (log bruto)
-- Armazena payload bruto para reprocessamento
-- ============================================================

CREATE TABLE webhooks_log (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED,                        -- pode ser NULL se tenant não identificado
  gateway         VARCHAR(30)  NOT NULL,
  evento          VARCHAR(80),
  payload         JSON         NOT NULL,
  headers         JSON,
  processado      TINYINT(1)   NOT NULL DEFAULT 0,
  processado_em   DATETIME,
  erro            TEXT,
  ip_origem       VARCHAR(45),
  recebido_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_gateway (gateway),
  INDEX idx_processado (processado),
  INDEX idx_recebido (recebido_em)
) ENGINE=InnoDB;

-- ============================================================
-- 17. FORMULÁRIOS PÚBLICOS DE INSCRIÇÃO
-- Submissões via link público do plano
-- ============================================================

CREATE TABLE inscricoes_publicas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  plano_id        INT UNSIGNED NOT NULL,
  -- Dados preenchidos pelo prospect
  nome_empresa    VARCHAR(180),
  cnpj            VARCHAR(18),
  nome_contato    VARCHAR(120) NOT NULL,
  email           VARCHAR(180) NOT NULL,
  whatsapp        VARCHAR(20),
  -- Processamento
  status          ENUM('pendente','convertido','rejeitado') NOT NULL DEFAULT 'pendente',
  prospecto_id    INT UNSIGNED,                        -- criado automaticamente
  cobranca_id     INT UNSIGNED,                        -- se gerou cobrança
  ip_origem       VARCHAR(45),
  convertido_em   DATETIME,
  criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_plano (plano_id),
  INDEX idx_status (status),
  CONSTRAINT fk_inscricoes_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_inscricoes_plano
    FOREIGN KEY (plano_id) REFERENCES planos(id)
) ENGINE=InnoDB;

-- ============================================================
-- 18. CONFIGURAÇÕES GERAIS DO TENANT
-- Chave-valor para settings flexíveis
-- ============================================================

CREATE TABLE tenant_configs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  chave         VARCHAR(80)  NOT NULL,
  valor         TEXT,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_chave (tenant_id, chave),
  CONSTRAINT fk_configs_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DATA SEEDS — Tenant ACIC-DF + estágios padrão + planos base
-- ============================================================

-- Tenant inicial: ACIC-DF
INSERT INTO tenants (slug, nome, cnpj, dominio, plano_saas) VALUES
('acicdf', 'ACIC-DF — Associação Comercial e Industrial do DF', '00.000.000/0001-00', 'crm.acicdf.org.br', 'pro');

-- Usuário superadmin HUX (sem tenant)
INSERT INTO usuarios (tenant_id, nome, email, senha_hash, role) VALUES
(NULL, 'HUX Admin', 'admin@hux.com.br', '$2y$12$PLACEHOLDER_HASH', 'superadmin');

-- Usuário gestor ACIC-DF
INSERT INTO usuarios (tenant_id, nome, email, senha_hash, role) VALUES
(1, 'Gestor ACIC-DF', 'gestor@acicdf.org.br', '$2y$12$PLACEHOLDER_HASH', 'gestor');

-- Estágios padrão do Pipeline (ACIC-DF)
INSERT INTO pipeline_estagios (tenant_id, nome, ordem, cor) VALUES
(1, 'Prospecção',   1, '#8B5CF6'),
(1, 'Qualificação', 2, '#3B82F6'),
(1, 'Proposta',     3, '#F59E0B'),
(1, 'Negociação',   4, '#F97316'),
(1, 'Fechado/Ganho',5, '#10B981'),
(1, 'Fechado/Perdido',6,'#94A3B8');

UPDATE pipeline_estagios SET eh_final_ganho   = 1 WHERE id = 5;
UPDATE pipeline_estagios SET eh_final_perdido = 1 WHERE id = 6;

-- Planos base ACIC-DF
INSERT INTO planos (tenant_id, nome, tipo, valor, periodicidade, tem_link_publico, slug_link) VALUES
(1, 'MEI Mensal',      'mei',        89.90,  'mensal', 1, 'mei-mensal'),
(1, 'MEI Anual',       'mei',       899.00,  'anual',  1, 'mei-anual'),
(1, 'ME Mensal',       'me',        159.90,  'mensal', 1, 'me-mensal'),
(1, 'ME Anual',        'me',       1499.00,  'anual',  1, 'me-anual'),
(1, 'EPP Mensal',      'epp',       249.90,  'mensal', 1, 'epp-mensal'),
(1, 'EPP Anual',       'epp',      2399.00,  'anual',  1, 'epp-anual'),
(1, 'Isento',          'isento',      0.00,  'anual',  0, NULL),
(1, 'Combo MEI+Conecta','combo',    129.90,  'mensal', 1, 'combo-mei-conecta');

-- Régua de cobrança padrão
INSERT INTO regua_cobranca (tenant_id, nome) VALUES
(1, 'Régua Padrão');

INSERT INTO regua_passos (regua_id, tenant_id, dias_offset, canal) VALUES
(1, 1, -7, 'ambos'),   -- 7 dias antes: lembrete
(1, 1,  0, 'ambos'),   -- dia do vencimento
(1, 1,  3, 'whatsapp'),-- 3 dias depois: alerta inadimplência
(1, 1, 15, 'email');   -- 15 dias depois: comunicado formal

-- ============================================================
-- VIEWS ÚTEIS
-- ============================================================

-- Resumo financeiro por tenant (para BI)
CREATE VIEW vw_resumo_financeiro AS
SELECT
  c.tenant_id,
  t.nome                              AS tenant_nome,
  COUNT(*)                            AS total_cobrancas,
  SUM(CASE WHEN c.status = 'pago'     THEN c.valor_pago ELSE 0 END) AS receita_total,
  SUM(CASE WHEN c.status = 'pendente' THEN c.valor      ELSE 0 END) AS pendente_total,
  SUM(CASE WHEN c.status IN ('pendente','falhou')
           AND c.data_vencimento < CURDATE()
           THEN c.valor ELSE 0 END)   AS inadimplente_total,
  COUNT(CASE WHEN c.status = 'pago'     THEN 1 END) AS qtd_pagos,
  COUNT(CASE WHEN c.status = 'pendente' THEN 1 END) AS qtd_pendentes
FROM cobrancas c
JOIN tenants t ON t.id = c.tenant_id
GROUP BY c.tenant_id, t.nome;

-- Associados com vencimento nos próximos 30 dias
CREATE VIEW vw_vencimentos_proximos AS
SELECT
  a.tenant_id,
  a.id          AS associado_id,
  a.razao_social,
  a.nome_responsavel,
  a.email,
  a.whatsapp,
  a.data_vencimento,
  DATEDIFF(a.data_vencimento, CURDATE()) AS dias_ate_vencimento,
  p.nome        AS plano_nome,
  p.valor       AS plano_valor
FROM associados a
LEFT JOIN planos p ON p.id = a.plano_id
WHERE a.status IN ('ativo','inadimplente')
  AND a.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY);

-- Pipeline: valor total por estágio
CREATE VIEW vw_pipeline_por_estagio AS
SELECT
  pp.tenant_id,
  pe.nome       AS estagio,
  pe.cor,
  pe.ordem,
  COUNT(pp.id)  AS total_prospectos,
  SUM(COALESCE(pp.valor_estimado, 0)) AS valor_total_estimado
FROM pipeline_prospectos pp
JOIN pipeline_estagios pe ON pe.id = pp.estagio_id
GROUP BY pp.tenant_id, pe.id, pe.nome, pe.cor, pe.ordem
ORDER BY pp.tenant_id, pe.ordem;

SET foreign_key_checks = 1;

-- ============================================================
-- FIM DO SCHEMA v1.0
-- 18 tabelas · 3 views · seeds ACIC-DF incluídos
-- Próximo: estrutura do projeto → auth/RBAC → PAL
-- ============================================================
