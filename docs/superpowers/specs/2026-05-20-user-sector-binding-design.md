# Spec 1 — Vínculo de usuário a setor (Foundation)

**Data:** 2026-05-20
**Status:** Aprovado
**Escopo:** Permitir vincular usuários a setores pela tela de Usuários e limpar colunas não usadas do pivot.

---

## 1. Contexto

O sistema Vora é um helpdesk WhatsApp para uma única empresa. A segmentação operacional é por **setor** (ex.: Suporte, Comercial, Financeiro). A tabela `attendant_sectors` (pivot many-to-many entre `users` e `sectors`) já existe e o `TicketController` já filtra a visibilidade do **atendente** por setor.

Hoje **não existe UI** para criar esse vínculo: ao cadastrar um usuário, não há como dizer em qual setor ele atua. O atendente é criado "solto" e fica sem ver nada.

## 2. Regras de visibilidade (referência, não muda nesse spec)

- **Admin**: vê tudo (todos os setores, usuários, tickets)
- **Supervisor**: vê tudo (mesma visibilidade do admin)
- **Atendente**: vê só os setores vinculados via `attendant_sectors`; dentro do setor é fila compartilhada (vê conversas atribuídas a ele + não-atribuídas do setor)
- **Gerenciamento de usuários**: somente admin

O backend de visibilidade já está correto pra essa regra. Esse spec **não** mexe em `TicketController` nem em policies.

## 3. Mudanças

### 3.1 Schema

Migration nova: `drop_unused_columns_from_attendant_sectors`.

- Dropar `is_default` (boolean)
- Dropar `priority` (integer)

Razão: nenhuma parte do código lê ou escreve essas colunas. YAGNI.

Reversível via `down()` recriando ambas com defaults originais.

### 3.2 Backend

**`UserController.store`** (`app/app/Http/Controllers/Api/V1/UserController.php`):
- Adicionar regra de validação `sector_ids`:
  - `nullable|array` quando role for `admin` ou `supervisor`
  - `required|array|min:1` quando role for `attendant`
  - Cada ID validado contra `sectors` da empresa
- Após criar o usuário, `$user->sectors()->sync($validated['sector_ids'] ?? [])`

**`UserController.update`**:
- Mesma validação condicional baseada no novo role (ou role atual se não enviado)
- `$user->sectors()->sync(...)` quando `sector_ids` presente no payload

**`UserController.index`** e **`show`**:
- Retornar `sectors` como relacionamento eager-loaded com colunas `id`, `name`, `color`

**`User` model** (`app/app/Domain/Auth/Models/User.php`):
- Adicionar accessor opcional `sectorIds()` retornando array de IDs (conveniência)

### 3.3 Frontend — Users/Index.vue

**Form dialog (criar/editar):**
- Novo campo "Setores" abaixo do role
- Componente: multi-select com chips (pode reusar pattern existente do sistema; se não existir, criar inline com checkbox list)
- Carregar setores ativos via `GET /api/v1/sectors`
- Validação client-side: bloqueia submit se role = `attendant` e nenhum setor selecionado
- Mensagem de erro: "Selecione pelo menos um setor para atendentes"

**Tabela de usuários:**
- Nova coluna "Setores" entre "Papel" e "Status"
- Renderiza até 2 chips com `name` e `color` do setor
- Se houver mais de 2, mostra "+N"
- Se vazio, mostra "—"

### 3.4 Testes

**Feature tests (PHP) — `tests/Feature/UserSectorAssignmentTest.php`:**
- ✅ Admin cria atendente com 1+ setor → sucesso
- ✅ Admin cria atendente sem setor → 422
- ✅ Admin cria admin sem setor → sucesso
- ✅ Admin cria supervisor sem setor → sucesso
- ✅ Admin atualiza usuário e troca setores → pivot sincronizado
- ✅ Resposta de `index` inclui `sectors` array

**Smoke (manual via dev server):**
- Form renderiza chips de setor
- Submit sem setor pra atendente mostra erro
- Editar usuário pré-preenche setores atuais

## 4. Fora de escopo (Spec 2 depois)

- Dashboard com filtro de setor
- Relatórios/analytics com filtro
- Auditoria do bot/n8n
- Refatoração de tenant
- Hierarquia de subsetores no select (Spec 1 trata setor pai e filho como entradas planas; se for útil agrupar visualmente, fica pro Spec 2)

## 5. Riscos

- **Migration em produção**: drop column é destrutivo. Mitigação: backup automático do EasyPanel + migration tem `down()` reversível.
- **Usuários existentes**: nenhum atendente atual está vinculado a setor. Decisão: deixar como está; admin precisa editar cada um manualmente após deploy. Não há "default" automático possível porque não sabemos qual setor cada um deveria estar.

## 6. Critério de aceitação

- [ ] Migration roda sem erro em prod
- [ ] Admin consegue criar atendente vinculado a setor pela tela
- [ ] Admin não consegue criar atendente sem setor (erro 422 + msg UI)
- [ ] Tabela de usuários mostra os setores de cada um
- [ ] Atendente recém-criado e vinculado consegue ver conversas do setor
- [ ] Testes feature passam
