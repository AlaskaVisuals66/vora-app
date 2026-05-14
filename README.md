# Helpdesk WhatsApp Center

Central de atendimento profissional via WhatsApp — Laravel 11 + Inertia/Vue 3 + Evolution API + n8n + Soketi, com multi-tenant, RBAC, distribuição automática, menu interativo e dashboard em tempo real.

> **Stack:** PHP 8.3 · Laravel 11 · PostgreSQL 16 · Redis 7 · Soketi · Vue 3 + Inertia · Tailwind · Horizon · JWT · Evolution API v2 · n8n · Docker

---

## Sumário

1. [Arquitetura](#1-arquitetura)
2. [Estrutura de pastas](#2-estrutura-de-pastas)
3. [Subindo o ambiente](#3-subindo-o-ambiente)
4. [Configuração inicial](#4-configuração-inicial)
5. [Fluxo de uma conversa](#5-fluxo-de-uma-conversa)
6. [Permissões e papéis](#6-permissões-e-papéis)
7. [Workflows n8n](#7-workflows-n8n)
8. [Operação e monitoramento](#8-operação-e-monitoramento)
9. [Segurança](#9-segurança)
10. [Roadmap](#10-roadmap)

---

## 1. Arquitetura

```
                ┌──────────────────────────────┐
   WhatsApp ───▶│  Evolution API (Baileys)     │──webhook──┐
                └──────────────────────────────┘           │
                                                            ▼
┌────────────────┐   Inertia/HTTPS    ┌────────────────────────────────────┐
│  Vue SPA       │ ◀──────────────────│  Nginx → PHP-FPM (Laravel 11)      │
│  (Inertia)     │   WS: Soketi       │  · API REST v1                     │
└────────────────┘ ◀──────────────────│  · Domain services (DDD-ish)       │
                                      │  · Horizon workers (queues)        │
                                      └─────────┬──────────┬───────────────┘
                                                │          │
                                       ┌────────▼───┐ ┌────▼──────┐
                                       │ PostgreSQL │ │ Redis     │
                                       └────────────┘ └─────┬─────┘
                                                            │
                                                       ┌────▼─────┐
                                                       │  Soketi  │ ◀── browser WS
                                                       └────┬─────┘
                                                            │
                                                       ┌────▼─────┐
                                                       │   n8n    │
                                                       └──────────┘
```

**Princípios:**

- Multi-tenant por coluna `tenant_id` em toda tabela; RBAC com `spatie/permission` em modo *teams* (`team_foreign_key=tenant_id`).
- Domínios isolados em `app/Domain/{Auth,Tenancy,Sector,Client,Ticket,Message,Attendant,Analytics}` — cada um com Models/Services/Events/Jobs próprios.
- Webhooks da Evolution caem em `/api/v1/webhooks/evolution`, são empacotados em DTO e despachados para a fila `webhooks` (Horizon), garantindo resiliência.
- Distribuição automática usa **lock Redis** (`Cache::lock`) para evitar atribuições duplicadas e duas estratégias plugáveis: `round_robin` e `least_busy`.
- Menu interativo é **stateful**: o estado vive na coluna `tickets.menu_state` (JSON), o que permite voltar a um menu pendente mesmo após restart do worker.
- Realtime: eventos `ShouldBroadcast` em canais `tenant.{id}`, `tenant.{id}.sector.{id}`, `tenant.{id}.ticket.{id}` (privados) + `presence-tenant.{id}` para "quem está online".

## 2. Estrutura de pastas

```
.
├── docker-compose.yml             # nginx + php-fpm + horizon + scheduler + postgres + redis + soketi + evolution + n8n
├── docker/
│   ├── nginx/default.conf
│   └── php/{Dockerfile, php.ini}
├── env.example                    # copie para .env (manualmente, sandbox bloqueia dotfiles)
├── n8n/workflows/                 # JSON dos workflows (importar pela UI ou API)
│   ├── ticket-queued-no-attendant.json
│   ├── ticket-assigned.json
│   ├── ticket-transferred.json
│   └── sla-breach.json
└── app/                           # raiz do Laravel
    ├── app/
    │   ├── Domain/{Auth,Tenancy,Sector,Client,Ticket,Message,Attendant,Analytics}/
    │   │   ├── Models/
    │   │   ├── Services/
    │   │   └── Http/{Controllers,Requests,Resources}/
    │   ├── Events/                # MessageReceived, MessageSent, TicketAssigned, ...
    │   ├── Jobs/                  # ProcessIncomingWhatsappEvent, NotifyN8nEvent
    │   ├── Http/{Controllers,Middleware,Resources}/
    │   ├── Infra/{Evolution,N8n,Realtime}/
    │   └── Providers/
    ├── bootstrap/{app,providers}.php
    ├── config/                    # app, auth, jwt, broadcasting, queue, horizon, services, permission, helpdesk, ...
    ├── database/{migrations,seeders,factories}/
    ├── resources/
    │   ├── views/app.blade.php
    │   ├── css/app.css
    │   └── js/
    │       ├── app.js, bootstrap.js
    │       ├── Lib/echo.js
    │       ├── Layouts/{AppLayout, GuestLayout}.vue
    │       ├── Components/{Sidebar, Topbar, Avatar, Badge, TicketListItem, MessageBubble, ClientPanel}.vue
    │       ├── Composables/{useAuth, usePresence, useFormat}.js
    │       ├── Stores/conversations.js
    │       └── Pages/{Auth/Login, Dashboard, Conversations, Sectors, Users, Settings}/Index.vue
    └── routes/{api,web,channels,console}.php
```

## 3. Subindo o ambiente

### Pré-requisitos

- Docker Desktop (Windows/macOS) ou Docker Engine + Compose v2
- 8 GB RAM livres recomendados
- Portas livres: `80, 5432, 6379, 6001, 8080, 5678`

### Passo a passo

```bash
# 1) Clone e entre na pasta
cd "teste gerenciamento whatsapp"

# 2) Crie o .env (sandbox bloqueia escrita direta de dotfiles)
cp env.example .env

# 3) ATENÇÃO — arquivo bloqueado pelo sandbox: app/config/database.php
#    Crie manualmente com o conteúdo padrão de Laravel 11 + driver pgsql
#    apontando para o serviço "postgres" (host) com env DB_*. Veja a seção 4.1.

# 4) Suba a stack
docker compose up -d --build

# 5) Instale dependências
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build      # ou: npm run dev (HMR)

# 6) Bootstrap Laravel
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link

# 7) Horizon (filas)
docker compose logs -f horizon
```

Acesse:

| Serviço            | URL                          |
|--------------------|------------------------------|
| Painel             | http://localhost             |
| Horizon            | http://localhost/horizon     |
| Evolution API      | http://localhost:8080        |
| n8n                | http://localhost:5678        |
| Soketi (WS)        | ws://localhost:6001          |

### 4.1 `app/config/database.php` (criar manualmente)

> O sandbox bloqueia a criação automática deste arquivo. Crie com este conteúdo mínimo:

```php
<?php
return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', 'postgres'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'helpdesk'),
            'username' => env('DB_USERNAME', 'helpdesk'),
            'password' => env('DB_PASSWORD', 'helpdesk'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ],
    ],
    'migrations' => 'migrations',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options'=> ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', 'helpdesk:')],
        'default'   => ['url' => env('REDIS_URL'), 'host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_DB', '0')],
        'cache'     => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_CACHE_DB', '1')],
        'queue'     => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_QUEUE_DB', '2')],
        'presence'  => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => '3'],
        'locks'     => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => '4'],
    ],
];
```

## 4. Configuração inicial

### 4.2 Conectar o WhatsApp

1. Login como **admin@helpdesk.local / password** em http://localhost/login.
2. **Configurações → Sessões WhatsApp → + Conectar número**, informe um nome (ex: `principal`).
3. Clique em **QR Code** e escaneie no app WhatsApp Business do celular operador.
4. O webhook é configurado automaticamente para `http://nginx/api/v1/webhooks/evolution`.

### 4.3 Importar workflows n8n

```bash
# via UI: n8n → Workflows → Import from file → selecione cada JSON em n8n/workflows/
# via API:
for f in n8n/workflows/*.json; do
  curl -X POST http://localhost:5678/api/v1/workflows \
       -H "X-N8N-API-KEY: $N8N_API_TOKEN" \
       -H "Content-Type: application/json" \
       -d @"$f"
done
```

Configure em cada workflow as credenciais (Slack/E-mail) e a env `HELPDESK_API_URL=http://nginx/api`.

## 5. Fluxo de uma conversa

```
Cliente envia "oi"
      │
      ▼
Evolution recebe → webhook → fila webhooks
      │
      ▼
ProcessIncomingWhatsappEvent
  ├─ resolve session → tenant
  ├─ upsert Client
  ├─ obtém ou cria Ticket (status=menu)
  └─ MenuEngine.process()
       ├─ STATE_ROOT     → mostra "1-Comercial / 2-Financeiro / 3-Manutenção"
       ├─ STATE_SUBMENU  → para Manutenção: "1-Téc / 2-Cient"
       └─ STATE_RESOLVED → seta sector_id, status=queued
                          └─ AttendantDistributor.assign()
                               ├─ Cache::lock + candidatos online
                               ├─ round_robin OU least_busy
                               └─ ticket.assigned_to + status=open
                          └─ broadcast TicketAssigned (privado, ao atendente)
                          └─ NotifyN8nEvent (ticket.assigned)
```

A partir daí, mensagens fluem em ambos os sentidos:

- **Inbound:** webhook → `MessageReceived` (broadcast) → painel atualiza em tempo real.
- **Outbound:** atendente envia → `OutboundMessageService.sendText` → Evolution → `MessageSent` (broadcast) + status WhatsApp (`sent → delivered → read`).

## 6. Permissões e papéis

| Papel        | Acesso                                                                 |
|--------------|------------------------------------------------------------------------|
| `admin`      | Tudo, incluindo gestão de sessões WhatsApp, usuários, setores, config |
| `supervisor` | Vê todos os tickets do tenant, transfere, encerra, vê analytics       |
| `attendant`  | Vê apenas tickets atribuídos a si ou ao seu setor                     |

Permissões granulares: `tickets.view.{all,own}`, `tickets.{send,transfer,close}`, `sectors.manage`, `users.manage`, `whatsapp.manage`, `analytics.view`, `settings.manage`.

## 7. Workflows n8n

| Workflow                          | Trigger (evento)        | O que faz                                          |
|-----------------------------------|-------------------------|----------------------------------------------------|
| `ticket-queued-no-attendant.json` | `ticket.queued`         | Alerta Slack + e-mail à supervisão                 |
| `ticket-assigned.json`            | `ticket.assigned`       | Saudação automática enviada como mensagem outbound |
| `ticket-transferred.json`         | `ticket.transferred`    | Posta no canal #helpdesk-transferencias            |
| `sla-breach.json`                 | `sla.breach`            | Alerta SLA com classificação por severidade        |

O Laravel dispara estes eventos via `NotifyN8nEvent` → `POST {N8N_BASE_URL}/webhook/helpdesk/{event}` com header `X-Helpdesk-Secret`.

## 8. Operação e monitoramento

- **Horizon:** http://localhost/horizon — filas `default`, `webhooks`, `notifications`, `media`.
- **Logs:** `docker compose logs -f app horizon`.
- **Schedule:** verifica SLA a cada 5min, rollup analytics diário, snapshot Horizon a cada 5min (em `routes/console.php`).
- **Métricas no painel:** tickets em aberto, na fila, resolvidos, TMA, volume 14 dias, distribuição por setor e por atendente.

## 9. Segurança

- JWT com refresh (`tymon/jwt-auth`); rate limit `5/60s` no `auth/login` por IP.
- Webhooks n8n exigem header `X-Helpdesk-Secret` (env `N8N_WEBHOOK_SECRET`).
- Webhooks Evolution: rede interna Docker + throttle `webhooks`.
- Toda query escopada por `tenant_id` via middleware `tenant` + scopes nos models.
- Auditoria de ações sensíveis em `audit_logs` (login, logout, transfer, close, send).
- Atualize `JWT_SECRET`, `APP_KEY`, `N8N_API_TOKEN`, `N8N_WEBHOOK_SECRET` em produção.

> ⚠️ Se você compartilhou um token n8n em qualquer canal não-criptografado, **revogue e gere um novo** em `n8n → Settings → API`.

## 10. Roadmap

- Importação de contatos via CSV
- Respostas rápidas e tags por setor
- Encaminhamento por palavras-chave (NLU básico)
- Relatórios exportáveis (CSV/PDF)
- App mobile do atendente (PWA installable já funciona)
- Integração com CRMs (HubSpot, Pipedrive)

---

**Credenciais de demo (após `migrate --seed`):**

| Usuário                          | Senha      | Papel       |
|----------------------------------|------------|-------------|
| admin@helpdesk.local             | `password` | admin       |
| supervisor@helpdesk.local        | `password` | supervisor  |
| ana@helpdesk.local               | `password` | attendant   |
| bruno@helpdesk.local             | `password` | attendant   |
| carla@helpdesk.local             | `password` | attendant   |
