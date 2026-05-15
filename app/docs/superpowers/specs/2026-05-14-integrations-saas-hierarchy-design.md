# Design: Integrations Redesign, SaaS Sessions & User Hierarchy

**Date:** 2026-05-14  
**Approach:** UI-first incremental (A)  
**Status:** Approved

---

## Overview

Three interconnected features delivered as one implementation plan:

1. **Settings/Integrations tab** — rename Evolution API card to generic "APIs & n8n", add per-sector n8n AI controls
2. **WhatsApp session model** — enforce 1 primary session per tenant, SaaS-safe quota
3. **User hierarchy + visibility** — SuperAdmin > Owner (admin) > Employee (attendant), sector-scoped visibility

---

## Section 1 — APIs & n8n Settings Tab

### Integrations tab rename

Current tab label "Integrações" stays; the card title changes from "Evolution API" to something generic. Suggested: **"Gateway de Mensagens"**.

### Card 1 — Gateway de Mensagens

Stored in `tenants.settings` JSON as:

```json
{
  "gateway": {
    "type": "evolution",
    "config": {}
  }
}
```

- `type: "evolution"` — existing behavior, read-only display of URL + API key status + webhook URL
- `type: "webhook"` — generic outbound webhook; editable fields: send URL, secret header name, secret value, event mapping (JSON textarea or key-value pairs for incoming/outgoing events)

UI: a `<select>` for type. Fields change based on selection. Saving updates `tenants.settings.gateway`.

No new DB column needed — `settings` is already a JSON cast column on `Tenant`.

### Card 2 — n8n por Setor

New column `ai_settings` (JSON) added to `sectors` table via migration:

```json
{
  "ai_enabled": false,
  "ai_prompt": "",
  "n8n_workflow_id": "",
  "n8n_webhook_path": ""
}
```

UI in Settings > APIs & n8n:
- Accordion or table, one row per sector
- Toggle "IA ativada" (boolean)
- Textarea "Prompt do bot" (system prompt sent to n8n)
- Input "Workflow ID" (n8n workflow to activate/deactivate)
- Button "Editar número" → triggers `POST /webhook/edit-number` on n8n with `{sector_id, tenant_id}`
- Button "Editar conversa" → triggers `POST /webhook/edit-conversation` on n8n with `{sector_id, tenant_id}`

API: `PUT /api/v1/sectors/{id}/ai-settings` — admin only.

### ConversationOrchestrator integration

When a message arrives on a sector with `ai_enabled = true`, `ConversationOrchestrator` calls `N8nClient::trigger($sector->ai_settings['n8n_webhook_path'], $payload)` where payload includes the prompt, conversation, and client info.

---

## Section 2 — WhatsApp Session Model (SaaS)

### 1 primary session per tenant

`whatsapp_sessions.is_primary` already exists. Add:
- Application-level enforcement (DB is SQLite): inside a transaction, `UPDATE whatsapp_sessions SET is_primary = 0 WHERE tenant_id = ? AND id != ?` before setting the new primary. No migration index needed.
- Optional: `DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ...')` in a migration for SQLite partial index support.

### Session quota

`tenants.settings.max_sessions` (integer, default 3). `WhatsappSessionController::store` validates `count < max_sessions` before creating.

### Employee session permissions

- `admin` role: create, delete, set primary, reconnect — all actions
- `attendant` role: can create and reconnect (limited to non-primary sessions), cannot delete, cannot set primary

Policy: `WhatsappSessionPolicy` added.

---

## Section 3 — User Hierarchy & Sector-Scoped Visibility

### Roles (Spatie Permission, already installed)

| Role | Level | Scope |
|---|---|---|
| `superadmin` | Platform | All tenants. Never appears in tenant user lists. |
| `admin` | Tenant owner | All users within their tenant. Can create/manage users. |
| `attendant` | Employee | Only users sharing at least one sector. Cannot create users. |

`supervisor` role (already exists) is treated as `attendant` for visibility purposes — it can be kept for internal routing logic.

### Visibility rules

`UserController::index` applies scoping:

```php
if (auth()->user()->hasRole('superadmin')) {
    // superadmin has its own platform panel — not this endpoint
    abort(403);
}

if (auth()->user()->hasRole('admin')) {
    $query = User::where('tenant_id', $tenant->id)
                 ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'));
}

if (auth()->user()->hasRole('attendant') || auth()->user()->hasRole('supervisor')) {
    $mySectorIds = auth()->user()->sectors()->pluck('sectors.id');
    $query = User::where('tenant_id', $tenant->id)
                 ->whereHas('sectors', fn($q) => $q->whereIn('sectors.id', $mySectorIds))
                 ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'));
}
```

Superadmin is **never** returned in tenant user endpoints.

### User creation

- Only `admin` can `POST /api/v1/users` (create tenant users)
- Newly created users get role `attendant` by default; admin can promote to `admin`
- Superadmin creation is out of scope (platform-level concern)

### Example

byrees tenant has sector "byrees-team" with members Patrick and Andre. Both have role `attendant`. When Patrick calls `GET /api/v1/users`, he gets only Andre (and himself) — not users from other sectors, not superadmins.

---

## Data Changes Summary

| Change | Type | Migration needed? |
|---|---|---|
| `sectors.ai_settings` JSON column | New column | Yes |
| Unique partial index on `whatsapp_sessions` | Index | Yes |
| `tenants.settings.max_sessions` | JSON field | No (existing JSON) |
| `tenants.settings.gateway` | JSON field | No (existing JSON) |
| `superadmin` role | Spatie role | Seeder/migration |
| `WhatsappSessionPolicy` | PHP class | No DB |
| Sector-scoped user query in `UserController` | PHP change | No DB |

---

## Out of Scope

- SuperAdmin management UI (own platform panel, separate SP)
- Multi-gateway routing (messages always use primary session regardless of gateway type)
- Billing / plan enforcement beyond `max_sessions`
