# Conecta CRM — Design System MASTER
> Fonte da Verdade Global · Estilo: **Soft UI Evolution** · Gerado via ui-ux-pro-max
> Versão: 1.0.0 · Stack: React + Vite + shadcn/ui · Atualizado: 2026-03-27

---

## 1. Identidade Visual

### 1.1 Filosofia de Estilo — Soft UI Evolution

O Soft UI Evolution combina profundidade sutil com superfícies suaves para criar interfaces que transmitem **confiança e clareza operacional** — essencial para um CRM de associações. Características centrais:

- Sombras suaves em múltiplas camadas (sem borders rígidas)
- Superfícies ligeiramente elevadas com gradientes sutis
- Transições fluidas (200–300ms) em todas as interações
- Hierarquia visual por elevação, não por cor
- Paleta restrita com acentos estratégicos

---

## 2. Paleta de Cores

### 2.1 Cores Primárias

| Token | Hex | Uso |
|---|---|---|
| `--color-primary` | `#2563EB` | CTAs principais, links ativos, badges de status |
| `--color-primary-light` | `#EFF6FF` | Backgrounds de cards selecionados, hover suave |
| `--color-primary-dark` | `#1E40AF` | Hover em botões primários, estados pressionados |

### 2.2 Cores de Superfície

| Token | Hex | Uso |
|---|---|---|
| `--color-bg-base` | `#F8FAFC` | Background principal da aplicação |
| `--color-bg-surface` | `#FFFFFF` | Cards, modais, painéis |
| `--color-bg-elevated` | `#FFFFFF` | Dropdowns, tooltips, popovers |
| `--color-bg-subtle` | `#F1F5F9` | Inputs, áreas de conteúdo secundário |
| `--color-bg-muted` | `#E2E8F0` | Bordas suaves, separadores, skeleton |

### 2.3 Cores de Texto

| Token | Hex | Uso |
|---|---|---|
| `--color-text-primary` | `#0F172A` | Títulos, labels críticos |
| `--color-text-secondary` | `#475569` | Subtítulos, descrições, metadados |
| `--color-text-muted` | `#94A3B8` | Placeholders, disabled, hints |
| `--color-text-inverse` | `#FFFFFF` | Texto sobre fundos escuros/primários |

### 2.4 Cores Semânticas

| Token | Hex | Uso |
|---|---|---|
| `--color-success` | `#10B981` | Associado ativo, pagamento confirmado |
| `--color-success-light` | `#ECFDF5` | Background de badges de sucesso |
| `--color-warning` | `#F59E0B` | Inadimplência, prazo próximo |
| `--color-warning-light` | `#FFFBEB` | Background de alertas de atenção |
| `--color-danger` | `#EF4444` | Cancelado, bloqueado, erro crítico |
| `--color-danger-light` | `#FEF2F2` | Background de badges de erro |
| `--color-info` | `#3B82F6` | Informativo neutro, notificações |
| `--color-info-light` | `#EFF6FF` | Background de banners informativos |

### 2.5 Cores do Pipeline (Kanban)

| Estágio | Token | Hex |
|---|---|---|
| Prospecção | `--color-stage-prospect` | `#8B5CF6` |
| Qualificação | `--color-stage-qualify` | `#3B82F6` |
| Proposta | `--color-stage-proposal` | `#F59E0B` |
| Negociação | `--color-stage-negotiation` | `#F97316` |
| Fechado/Ganho | `--color-stage-won` | `#10B981` |
| Fechado/Perdido | `--color-stage-lost` | `#94A3B8` |

---

## 3. Tipografia

### 3.1 Family Stack

```css
/* Fonte principal — Interface */
--font-sans: 'Inter', system-ui, -apple-system, sans-serif;

/* Fonte de dados — Tabelas, métricas, códigos */
--font-mono: 'JetBrains Mono', 'Fira Code', monospace;
```

> Google Fonts import:
> `https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap`

### 3.2 Escala Tipográfica

| Token | Size | Weight | Line Height | Uso |
|---|---|---|---|---|
| `--text-xs` | 11px | 400 | 1.5 | Labels de badge, captions |
| `--text-sm` | 13px | 400/500 | 1.5 | Corpo secundário, metadados |
| `--text-base` | 15px | 400 | 1.6 | Corpo principal, descrições |
| `--text-md` | 16px | 500/600 | 1.4 | Labels de form, subtítulos de card |
| `--text-lg` | 18px | 600 | 1.3 | Títulos de seção, card headers |
| `--text-xl` | 22px | 700 | 1.2 | Títulos de página |
| `--text-2xl` | 28px | 700 | 1.1 | Métricas de KPI, números grandes |
| `--text-3xl` | 36px | 700 | 1.0 | Hero metrics, destaques de dashboard |

### 3.3 Regras Tipográficas

- **Nunca** usar weight abaixo de 400 em texto funcional
- Métricas numéricas usam `font-variant-numeric: tabular-nums` para alinhamento
- Capitalização: `sentence case` para UI (nunca ALL CAPS em labels)
- Truncamento: `text-overflow: ellipsis` com `title` attribute para acessibilidade

---

## 4. Espaçamento e Grid

### 4.1 Escala de Espaçamento (base 4px)

```css
--space-1:  4px;
--space-2:  8px;
--space-3:  12px;
--space-4:  16px;
--space-5:  20px;
--space-6:  24px;
--space-8:  32px;
--space-10: 40px;
--space-12: 48px;
--space-16: 64px;
--space-20: 80px;
```

### 4.2 Grid de Layout

```css
/* Layout principal */
--layout-sidebar-width:     240px;
--layout-sidebar-collapsed: 64px;
--layout-header-height:     60px;
--layout-content-max:       1280px;
--layout-content-padding:   var(--space-6);

/* Grid de cards (Dashboard) */
--grid-gap: var(--space-4);
```

### 4.3 Breakpoints

| Nome | Valor | Descrição |
|---|---|---|
| `sm` | 375px | Mobile |
| `md` | 768px | Tablet |
| `lg` | 1024px | Laptop |
| `xl` | 1280px | Desktop |
| `2xl` | 1440px | Large Desktop |

---

## 5. Elevação e Sombras (Soft UI)

O Soft UI Evolution usa sombras em camadas para criar profundidade sem uso de borders.

```css
/* Nível 0 — Flush (sem elevação) */
--shadow-none: none;

/* Nível 1 — Cards padrão, inputs */
--shadow-sm: 0 1px 2px rgba(0,0,0,0.04), 0 1px 6px rgba(0,0,0,0.04);

/* Nível 2 — Cards interativos, hover states */
--shadow-md: 0 4px 6px rgba(0,0,0,0.04), 0 2px 12px rgba(0,0,0,0.06);

/* Nível 3 — Painéis, sidebars */
--shadow-lg: 0 8px 16px rgba(0,0,0,0.06), 0 2px 20px rgba(0,0,0,0.04);

/* Nível 4 — Modais, dropdowns */
--shadow-xl: 0 20px 40px rgba(0,0,0,0.10), 0 4px 16px rgba(0,0,0,0.06);

/* Foco — Acessibilidade */
--shadow-focus: 0 0 0 3px rgba(37,99,235,0.25);

/* Sombra de card no hover */
--shadow-hover: 0 8px 24px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06);
```

---

## 6. Border Radius

```css
--radius-sm:   4px;   /* Tags, badges pequenos */
--radius-md:   8px;   /* Inputs, botões, chips */
--radius-lg:   12px;  /* Cards padrão */
--radius-xl:   16px;  /* Cards grandes, modais */
--radius-2xl:  24px;  /* Painéis de destaque */
--radius-full: 9999px; /* Avatars, pills, toggles */
```

---

## 7. Animações e Transições

```css
/* Durações */
--duration-fast:   150ms;
--duration-base:   200ms;
--duration-slow:   300ms;
--duration-slower: 500ms;

/* Easings */
--ease-default:  cubic-bezier(0.4, 0, 0.2, 1);
--ease-in:       cubic-bezier(0.4, 0, 1, 1);
--ease-out:      cubic-bezier(0, 0, 0.2, 1);
--ease-spring:   cubic-bezier(0.34, 1.56, 0.64, 1);

/* Padrão global de transição */
transition: all var(--duration-base) var(--ease-default);
```

**Regras:**
- `prefers-reduced-motion` deve desativar todas as animações decorativas
- Hover states: sempre usar `--duration-fast` (150ms)
- Modais e drawers: `--duration-slow` (300ms) com ease-out
- Skeleton loaders: `animation: pulse 2s ease-in-out infinite`

---

## 8. Componentes — Dashboard

### 8.1 KPI Card

Card de métrica principal do dashboard. Mostra um número destacado com variação percentual e trend.

**Anatomia:**
```
┌─────────────────────────────┐
│ [Ícone]  Label da Métrica   │  ← Header
│                             │
│  1.247                      │  ← Valor principal (--text-3xl, --color-text-primary)
│  ↑ +12,4% vs mês anterior   │  ← Variação (--text-sm, verde/vermelho)
│                             │
│  ████████░░ 78%             │  ← Progress bar (opcional)
└─────────────────────────────┘
```

**Especificações:**
- Background: `--color-bg-surface`
- Shadow: `--shadow-sm` (hover: `--shadow-hover`)
- Radius: `--radius-lg`
- Padding: `--space-6`
- Transition: `box-shadow var(--duration-fast) var(--ease-default)`
- Ícone: 20×20px, cor `--color-primary`, background `--color-primary-light`, radius `--radius-md`
- Valor: `--text-3xl`, `font-variant-numeric: tabular-nums`
- Variação positiva: `--color-success` com ícone `↑`
- Variação negativa: `--color-danger` com ícone `↓`

**KPIs obrigatórios no Dashboard principal:**
1. Total de Associados Ativos
2. Inadimplentes (%)
3. Receita do Mês (R$)
4. Novos no Pipeline
5. Taxa de Renovação (%)
6. Tickets Abertos

---

### 8.2 Sidebar de Navegação

**Especificações:**
- Largura: `--layout-sidebar-width` (240px) / colapsada: `--layout-sidebar-collapsed` (64px)
- Background: `--color-bg-surface`
- Shadow: `--shadow-lg` (direita)
- Itens de nav: padding `--space-3` `--space-4`, radius `--radius-md`
- Estado ativo: background `--color-primary-light`, text `--color-primary`, font-weight 600
- Estado hover: background `--color-bg-subtle`
- Ícones: 18×18px (Lucide Icons)
- Transição de colapso: `width var(--duration-slow) var(--ease-default)`

**Estrutura de navegação:**
```
PRINCIPAL
  ├── Dashboard
  ├── Associados
  ├── Financeiro
  └── Pipeline

GESTÃO
  ├── Comunicados
  ├── Links Importantes
  └── Configurações

CONTA
  └── Minha Empresa
```

---

### 8.3 Header Global

- Altura: `--layout-header-height` (60px)
- Background: `--color-bg-surface` com `--shadow-sm` (bottom)
- Conteúdo: logo esquerda · busca global central · notificações + avatar direita
- Busca: input com ícone, placeholder "Buscar associado, empresa..." · radius `--radius-full`
- Avatar: 36×36px, radius `--radius-full`, fallback com iniciais + `--color-primary-light`

---

### 8.4 Tabela de Dados

**Especificações:**
- Header: background `--color-bg-subtle`, `--text-xs`, uppercase, `--color-text-muted`, letter-spacing 0.05em
- Linhas: height 52px, border-bottom `1px solid --color-bg-muted`
- Hover de linha: background `--color-bg-subtle`
- Padding de célula: `--space-4` horizontal
- Ordenação: ícone de seta ao lado do label, `--color-primary` quando ativo

**Status badges em tabela:**
```
Ativo      → background: --color-success-light · text: --color-success · "● Ativo"
Inadimpl.  → background: --color-warning-light · text: --color-warning · "● Inadimplente"
Cancelado  → background: --color-bg-muted      · text: --color-text-muted · "● Cancelado"
```

**Regras:**
- Paginação abaixo da tabela: "Mostrando X–Y de Z resultados"
- Ações de linha (editar, ver) sempre no final da row, visíveis apenas no hover
- Checkbox de seleção múltipla na primeira coluna

---

### 8.5 Gráfico de Receita Mensal

- Tipo: Bar chart (Recharts) com barras arredondadas (`radius={[4,4,0,0]}`)
- Cor das barras: `--color-primary`
- Background do tooltip: `--color-bg-surface`, shadow `--shadow-xl`, radius `--radius-md`
- Grid lines: `--color-bg-muted`, dashed
- Eixo Y: valores em R$, `--text-sm`, `--color-text-muted`
- Eixo X: meses abreviados, `--text-sm`, `--color-text-muted`
- Hover: barra destacada com `opacity: 0.85`, tooltip aparece com `--duration-fast`

---

### 8.6 Feed de Atividade Recente

Lista vertical de eventos recentes (pagamentos, novos associados, vencimentos).

**Anatomia de item:**
```
[Avatar/Ícone 32px] [Texto da ação]          [Tempo relativo]
                    [Subtexto/detalhe]
```

- Separador: linha `1px solid --color-bg-muted`
- Ícone de ação colorido por tipo (pagamento=verde, alerta=amarelo, novo=azul)
- Tempo: `--text-xs`, `--color-text-muted`
- Limite visível: 8 itens · link "Ver tudo" ao final

---

## 9. Componentes — Pipeline de Vendas (Kanban)

### 9.1 Estrutura do Board

Layout horizontal com scroll, colunas fixas de largura igual.

```
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│PROSPECÇÃO│ │QUALIFIC. │ │PROPOSTA  │ │NEGOCIAÇ. │ │FECHADO   │
│ (n cards)│ │ (n cards)│ │ (n cards)│ │ (n cards)│ │ (n cards)│
│          │ │          │ │          │ │          │ │          │
│ [Card]   │ │ [Card]   │ │ [Card]   │ │ [Card]   │ │ [Card]   │
│ [Card]   │ │          │ │ [Card]   │ │          │ │ [Card]   │
│          │ │          │ │          │ │          │ │          │
│+ Adicionar│ │+ Adicionar│ │+ Adicionar│ │+ Adicionar│ │          │
└──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘
```

**Especificações da coluna:**
- Largura: 280px (fixa)
- Background: `--color-bg-subtle`
- Radius: `--radius-xl`
- Padding: `--space-4`
- Gap entre cards: `--space-3`
- Header da coluna: label do estágio + contagem de cards + valor total do estágio
- Cor do header: usa `--color-stage-*` correspondente como borda superior (4px)

---

### 9.2 Card de Prospecto (Kanban Card)

**Anatomia:**
```
┌──────────────────────────────┐  ← border-left 3px com --color-stage-*
│ Nome da Empresa              │  ← --text-md, font-weight 600
│ Contato: João Silva          │  ← --text-sm, --color-text-secondary
│                              │
│ R$ 1.200,00/ano              │  ← valor do plano, --text-sm, --color-primary
│                              │
│ [Badge: MEI] [Badge: Combo]  │  ← tags de plano/produto
│                              │
│ [Avatar] há 2 dias           │  ← responsável + tempo desde último contato
└──────────────────────────────┘
```

**Especificações:**
- Background: `--color-bg-surface`
- Shadow: `--shadow-sm`
- Shadow hover: `--shadow-hover`
- Radius: `--radius-lg`
- Padding: `--space-4`
- Border-left: `3px solid var(--color-stage-*)` — cor do estágio
- Cursor: `grab` (dragging: `grabbing`)
- Transição de drag: `opacity: 0.7` + `transform: rotate(2deg) scale(1.02)`
- Drop zone ativa: outline dashed `2px var(--color-primary)` com background `--color-primary-light`

**Estados do card:**
- Default: `--shadow-sm`
- Hover: `--shadow-hover`, `transform: translateY(-1px)`
- Dragging: `--shadow-xl`, `opacity: 0.75`, `rotate(2deg)`
- Atrasado (sem contato > 7 dias): badge `--color-warning` "Sem contato"

---

### 9.3 Header de Coluna do Pipeline

```
┌────────────────────────────────┐
│ ● Prospecção          5 cards  │
│   R$ 6.000,00 em negociação    │
└────────────────────────────────┘
```

- Bullet `●`: `--color-stage-prospect` (cor do estágio)
- Label: `--text-md`, font-weight 600, `--color-text-primary`
- Contagem: badge circular, `--color-bg-muted`, `--text-xs`
- Valor total: `--text-sm`, `--color-text-secondary`
- Linha decorativa no topo da coluna: `4px solid var(--color-stage-*)`

---

### 9.4 Modal de Detalhe do Prospecto

Drawer lateral (direita) que abre ao clicar no card — não interrompe o board.

**Especificações:**
- Largura: 480px
- Background: `--color-bg-surface`
- Shadow: `--shadow-xl` (esquerda)
- Entrada: `translateX(100%)` → `translateX(0)`, `--duration-slow`, `ease-out`
- Overlay: `rgba(0,0,0,0.30)`, click fora fecha

**Seções do drawer:**
1. **Header** — Nome da empresa + estágio atual (badge) + botão fechar
2. **Dados do Prospecto** — CNPJ, segmento, porte, contato principal
3. **Histórico de Relacionamento** — timeline de interações (ligações, e-mails, reuniões)
4. **Próxima Ação** — campo de tarefa com data + responsável
5. **Plano de Interesse** — MEI / ME / EPP / Combo + valor estimado
6. **Ações** — botões Mover Estágio · Registrar Contato · Converter em Associado

---

### 9.5 Barra de Filtros do Pipeline

Localizada acima do board, linha horizontal.

```
[Buscar prospecto...] [Responsável ▼] [Plano ▼] [Período ▼] [Limpar filtros]
```

- Inputs: height 36px, radius `--radius-md`, background `--color-bg-subtle`
- Selects: mesmo estilo do input, ícone de chevron à direita
- Botão limpar: text button, `--color-text-muted`, aparece apenas quando há filtro ativo
- Contador de resultados: "Mostrando 12 de 34 prospectos" — `--text-sm`, `--color-text-secondary`

---

## 10. Componentes Base

### 10.1 Botões

| Variante | Background | Text | Border | Uso |
|---|---|---|---|---|
| `primary` | `--color-primary` | `--color-text-inverse` | none | Ação principal |
| `secondary` | `--color-bg-subtle` | `--color-text-primary` | none | Ação secundária |
| `outline` | transparent | `--color-primary` | `1px solid --color-primary` | Alternativa neutra |
| `ghost` | transparent | `--color-text-secondary` | none | Ações terciárias |
| `danger` | `--color-danger` | `--color-text-inverse` | none | Ações destrutivas |

**Tamanhos:**
- `sm`: height 32px, padding `--space-3` `--space-4`, `--text-sm`
- `md`: height 40px, padding `--space-3` `--space-5`, `--text-base` (padrão)
- `lg`: height 48px, padding `--space-4` `--space-6`, `--text-md`

**Regras:**
- Radius: `--radius-md`
- Transição: `background, box-shadow var(--duration-fast) var(--ease-default)`
- Hover primary: `--color-primary-dark`
- Focus: `--shadow-focus` (outline visível)
- Loading state: spinner 16px + texto "Carregando..." · `disabled` + `cursor-not-allowed`
- Ícone + texto: gap `--space-2`, ícone 16×16px

---

### 10.2 Inputs e Forms

- Height: 40px (padrão)
- Background: `--color-bg-subtle`
- Border: `1px solid --color-bg-muted`
- Border focus: `1px solid --color-primary` + `--shadow-focus`
- Radius: `--radius-md`
- Padding: `--space-3` `--space-4`
- Label: `--text-sm`, font-weight 500, `--color-text-primary`, margin-bottom `--space-2`
- Helper text: `--text-xs`, `--color-text-muted`
- Erro: border `--color-danger` + helper text em `--color-danger`
- Placeholder: `--color-text-muted`

---

### 10.3 Badges e Status Pills

```css
/* Estrutura base */
display: inline-flex;
align-items: center;
gap: var(--space-1);
padding: var(--space-1) var(--space-2);
border-radius: var(--radius-full);
font-size: var(--text-xs);
font-weight: 500;
```

| Tipo | Background | Text |
|---|---|---|
| success | `--color-success-light` | `--color-success` |
| warning | `--color-warning-light` | `--color-warning` |
| danger | `--color-danger-light` | `--color-danger` |
| info | `--color-info-light` | `--color-info` |
| neutral | `--color-bg-muted` | `--color-text-secondary` |

---

### 10.4 Toasts e Notificações

- Posição: bottom-right, stack vertical com gap `--space-3`
- Largura: 360px
- Shadow: `--shadow-xl`
- Radius: `--radius-lg`
- Auto-dismiss: 5s (erro: sem auto-dismiss)
- Entrada: `translateY(100%) → translateY(0)`, `--duration-slow`

---

## 11. Acessibilidade

- Contraste mínimo: **4.5:1** para texto normal, **3:1** para texto grande
- Focus visible em todos os elementos interativos (`--shadow-focus`)
- `aria-label` obrigatório em ícones sem texto visível
- `role="status"` em áreas de loading e mensagens dinâmicas
- Navegação por teclado completa no Kanban (foco nos cards, Enter abre drawer)
- `prefers-reduced-motion`: desativar todas as animações decorativas
- Sem dependência de cor exclusiva para transmitir estado (usar ícone + cor)

---

## 12. Anti-Patterns (Proibidos)

- ❌ Borders pesadas (>1px) em cards e containers
- ❌ Gradientes chamativos ou "AI purple/pink"
- ❌ Sombras com `box-shadow: 0 0 20px rgba(0,0,0,0.5)` — muito pesadas
- ❌ Emojis como ícones — usar Lucide Icons (SVG)
- ❌ Animações sem `prefers-reduced-motion`
- ❌ Texto com contrast ratio abaixo de 4.5:1
- ❌ Elementos clicáveis sem `cursor-pointer`
- ❌ Hover states sem transição
- ❌ Modais que bloqueiam todo o fluxo do Kanban (usar drawer lateral)
- ❌ Tabelas sem estado de loading skeleton
- ❌ Formulários sem validação inline (mostrar erro só no submit)

---

## 13. Pre-Delivery Checklist

Aplicar antes de qualquer entrega de componente ou página:

- [ ] Sem emojis como ícones — apenas SVG (Lucide Icons)
- [ ] `cursor-pointer` em todos os elementos clicáveis
- [ ] Hover states com transição `--duration-fast`
- [ ] Contraste de texto mínimo 4.5:1 verificado
- [ ] Focus states visíveis (teclado)
- [ ] `prefers-reduced-motion` respeitado
- [ ] Responsivo testado: 375px · 768px · 1024px · 1280px · 1440px
- [ ] Estados de loading (skeleton) em toda área de dados assíncrona
- [ ] Estados vazios (empty state) com mensagem + ação sugerida
- [ ] Sem hardcode de cores (usar tokens CSS var())
- [ ] `aria-label` em ícones, botões sem texto, inputs sem label visível
- [ ] Toasts para feedback de ações (não apenas alerts do browser)

---

## 14. Overrides por Página

Para customizações de página específica, criar arquivo em:

```
design-system/pages/
  ├── dashboard.md       ← KPIs, gráficos, feed de atividade
  ├── associados.md      ← Tabela + filtros + drawer de detalhe
  ├── financeiro.md      ← Cobranças, régua, relatórios
  ├── pipeline.md        ← Kanban + drawer de prospecto
  └── configuracoes.md   ← Formulários de configuração
```

**Regra de hierarquia:**
> Arquivo de página **override** o MASTER. Se o arquivo de página não existir, usar este MASTER exclusivamente.

---

*Design System gerado pela metodologia ui-ux-pro-max (Soft UI Evolution) · Conecta CRM · HUX Participações · 2026*
