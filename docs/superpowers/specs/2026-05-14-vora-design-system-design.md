# Vora Design System — Design (Sub-projeto 1)

**Data:** 2026-05-14
**Status:** aprovado (aguardando revisão final do doc)

## Contexto

Primeiro de 6 sub-projetos da transformação do Vora num SaaS multiempresa premium. Este
sub-projeto entrega a **fundação visual**: tokens, biblioteca de componentes e o shell do
app. As páginas internas (Dashboard, Conversas, etc.) são reworkadas nos sub-projetos
seguintes — aqui elas só herdam o shell novo e são migradas para compilar com a nova lib.

Stack: Laravel 11 + Inertia + Vue 3 + Tailwind. O projeto **já tem**: token system HSL
estilo shadcn (`--background`, `--primary`, etc.), `darkMode: ['class']`,
`tailwindcss-animate`, `lucide-vue-next`, e uma pasta `resources/js/Components/ui/` com 5
componentes customizados (Avatar, Badge, Button, Input, Textarea).

## Decisões tomadas no brainstorming

- **Logo:** wordmark "Vora." tipografado (sem símbolo elaborado), trocável depois.
- **Dark mode:** sim — claro + escuro. O tema escuro É o navy Vora.
- **Sidebar:** rail navy colapsável (toggle por clique + hover-peek), sempre escuro nos
  dois temas.
- **Abordagem de implementação:** adotar `shadcn-vue` (a lib real; o CLI copia o
  código-fonte dos componentes pra dentro do projeto — não vira dependência opaca).

## Escopo

**Inclui:**
- Tokens/paleta Vora mapeados pros CSS vars do shadcn (claro + escuro)
- Setup do `shadcn-vue` (CLI, `components.json`, `lib/utils`)
- ~20 componentes primitivos (shadcn-vue) em `Components/ui/`
- ~8 componentes compostos Vora em `Components/vora/`
- Shell do app: `Sidebar.vue`, `Header.vue`, `AppLayout.vue`
- Migração das páginas existentes para compilar com a nova lib (compile-fix, sem redesign)
- Rota dev-only `/_ui` (showcase) como superfície de QA visual

**Não inclui (sub-projetos próprios):**
- Rework de Dashboard (SP5) e Conversas (SP4)
- Componentes de feature: ConversationList, ChatWindow, MessageBubble, CustomerPanel
  (SP4) · TeamTree, RoleManager (SP3)

## 1. Arquitetura & tooling

- **Dependências novas:** `shadcn-vue` (CLI/dev), `reka-ui`, `class-variance-authority`,
  `clsx`, `tailwind-merge`, `@vueuse/core`. (`lucide-vue-next` e `tailwindcss-animate` já
  existem.)
- **Config:** `components.json` na raiz do `app/`; `resources/js/lib/utils.js` com o
  helper `cn()` (clsx + tailwind-merge).
- **Layout de arquivos:**
  - `resources/js/Components/ui/` — primitivos shadcn-vue (substitui os 5 customizados)
  - `resources/js/Components/vora/` — compostos Vora
  - `resources/js/Layouts/` — shell (AppLayout, Sidebar, Header)
- Stack inalterada. shadcn-vue é Vue 3 puro, sem atrito com Inertia.

## 2. Tokens & temas

Paleta Vora mapeada pros tokens shadcn (HSL CSS vars em `resources/css/app.css`),
claro + escuro. O tema escuro é o navy Vora.

| Token | Claro | Escuro |
|---|---|---|
| `background` | `#F6F7F9` | `#071225` |
| `foreground` | `#08111F` | `#E6E9EF` |
| `card` / `popover` | `#FFFFFF` | `#0F1B33` |
| `card-foreground` / `popover-foreground` | `#08111F` | `#E6E9EF` |
| `primary` | `#F04A24` | `#F04A24` |
| `primary-foreground` | `#FFFFFF` | `#FFFFFF` |
| `secondary` / `muted` | `#F0F1F3` | `#16223D` |
| `secondary-foreground` | `#08111F` | `#E6E9EF` |
| `muted-foreground` | `#667085` | `#94A3B8` |
| `accent` (superfície de hover) | `#EEF0F2` | `#1B2845` |
| `accent-foreground` | `#08111F` | `#E6E9EF` |
| `destructive` | `#E5484D` | `#F2555A` |
| `destructive-foreground` | `#FFFFFF` | `#FFFFFF` |
| `border` / `input` | `#E5E7EB` | `#1E2A47` |
| `ring` (foco) | `#F04A24` | `#F04A24` |

- **Sidebar:** sempre navy (`#071225`) nos dois temas. Tokens `sidebar.*` próprios:
  bg `#071225`, foreground `#C7CDD9`, item ativo `#F04A24`, border `#1E2A47`.
- **Laranja claro** `#FF7A3D`: usado em hovers/estados do laranja, não como cor base.
- **Raio:** `--radius: 0.625rem` (10px) → `lg`; `md`/`sm` derivados.
- **Tipografia:** Inter pra sans e display (display com tracking apertado). JetBrains
  Mono pra mono. Remover `"Cal Sans"` do `tailwind.config.js` (não licenciada).
- **Sombras:** manter `soft` / `card` / `pop` (leves). **Remover a sombra `accent`**
  (glow laranja) — regra visual "sem brilho exagerado".
- **Motion:** `fade-in` / `slide-up` em 150–200ms; microinterações suaves; sem
  gradientes fortes.
- O bloco de tokens `vora.*` raw do `tailwind.config.js` é atualizado pra paleta nova.

## 3. Componentes

**Primitivos shadcn-vue** (`Components/ui/`, gerados via CLI):
Button, Input, Textarea, Label, Select, Checkbox, Switch, Badge, Avatar, Dialog (Modal),
Sheet (painéis slide-over), Tabs, DropdownMenu, Tooltip, Popover, Table, Skeleton,
Sonner (Toast), ScrollArea, Separator, Command (busca).

**Compostos Vora** (`Components/vora/`, sobre os primitivos):
MetricCard, ChartCard (casca p/ gráfico), EmptyState, LoadingSkeleton (skeletons de
página), DataTable (wrapper Table com sort/paginação), PageHeader, UserMenu, ThemeToggle.

**Shell do app** (`Layouts/`):
- `Sidebar.vue` — rail navy colapsável: toggle por clique + hover-peek; ícones lucide com
  Tooltip quando recolhido; wordmark "Vora." no topo; user/config no rodapé.
- `Header.vue` — barra branca sobre o conteúdo: título/breadcrumb (PageHeader), trigger
  de busca (Command), ThemeToggle (claro/escuro, persistido em localStorage,
  alternando `.dark` no `<html>`), notificações, UserMenu (Avatar + DropdownMenu).
- `AppLayout.vue` — monta Sidebar + Header + `<Toaster>` (Sonner) + slot do conteúdo.

## 4. Migração

`shadcn-vue` gera componentes em `Components/ui/`, colidindo com os 5 customizados
atuais. Estratégia:

1. Gerar os primitivos shadcn-vue (substituem os customizados — sem código órfão).
2. Identificar todos os usos dos componentes `ui/` antigos (ex.: `Conversations/Index.vue`
   importa Avatar, Badge, Button, Input, Textarea).
3. Pass de **compile-fix**: ajustar props/slots dos usos pra nova API do shadcn-vue.
   Objetivo: páginas **funcionam e compilam**, não são redesenhadas (isso é SP4/SP5).
4. Todas as páginas herdam o shell novo (AppLayout/Sidebar/Header) imediatamente.

## 5. Verificação

Sem Playwright neste ambiente. Critérios:
- `npm run build` passa limpo (zero erros).
- App dá boot sem erro no console.
- Toda rota existente renderiza (login→conversas, dashboard, sectors, users, settings).
- Toggle de tema funciona e persiste; dark mode correto em todos os componentes do shell.
- Colapso/expansão do sidebar funciona; tooltips aparecem quando recolhido.
- Rota dev-only `/_ui` renderiza todos os primitivos + compostos em claro e escuro.
- Disciplina `superpowers:verification-before-completion` antes de declarar concluído.

## Riscos & notas

- **Colisão de componentes:** mitigada pelo pass de compile-fix na migração (passo 4).
- **Sem Playwright/MCPs externos:** QA via build limpo + showcase `/_ui` + verificação
  manual das rotas.
- **Projeto não é repositório git:** inicializar git e fazer commit de baseline antes de
  começar a implementação (passo de "backup/commit" do processo do usuário).
- **APIs divergentes:** componentes shadcn-vue têm API diferente dos customizados; o
  esforço de migração concentra-se nas páginas que mais usam `ui/` (Conversas).
