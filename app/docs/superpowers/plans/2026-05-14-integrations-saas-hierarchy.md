# Integrations, SaaS Sessions & User Hierarchy — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the integrations settings tab (multi-API + n8n per-sector controls), enforce 1 primary WhatsApp session per tenant with quota, and add sector-scoped user visibility with a superadmin role invisible to tenants.

**Architecture:** UI-first incremental — backend schema/API changes first, then frontend. Each task produces independently testable, committable code. The existing `sectors.settings` JSON column is **not** used for AI settings; a dedicated `ai_settings` column is added to keep concerns separate.

**Tech Stack:** Laravel 11, Pest v3 (pestphp/pest-plugin-laravel), Vue 3 + Inertia, Spatie Permission, SQLite (dev), shadcn-vue components.

---

## File Map

**New files:**
- `database/migrations/2026_05_14_000001_add_ai_settings_to_sectors.php`
- `database/migrations/2026_05_14_000002_add_superadmin_role.php`
- `app/Policies/WhatsappSessionPolicy.php`
- `app/Jobs/TriggerAiWebhook.php`
- `resources/js/Components/vora/SectorAiSettings.vue`
- `tests/Feature/SectorAiSettingsTest.php`
- `tests/Feature/UserVisibilityTest.php`
- `tests/Feature/WhatsappSessionQuotaTest.php`
- `tests/Feature/GatewaySettingsTest.php`
- `tests/Pest.php`

**Modified files:**
- `app/Domain/Sector/Models/Sector.php` — add `ai_settings` to fillable + casts
- `app/Http/Controllers/Api/V1/SectorController.php` — add `aiSettings()` + `n8nAction()` + expose `ai_settings` in index
- `app/Http/Controllers/Api/V1/UserController.php` — sector-scoped visibility in `index()`
- `app/Http/Controllers/Api/V1/WhatsappSessionController.php` — quota enforcement + primary enforcement
- `app/Http/Controllers/Api/V1/TenantController.php` — `updateGateway()` + expose `gateway` in `show()`
- `app/Domain/Ticket\Services/ConversationOrchestrator.php` — dispatch AI webhook when sector has `ai_enabled`
- `database/seeders/RolesAndPermissionsSeeder.php` — add `superadmin` role
- `routes/api.php` — new routes, restructure session permissions
- `resources/js/Pages/Settings/Index.vue` — gateway card redesign + wire SectorAiSettings

---

## Task 1: Test bootstrap

**Files:**
- Create: `tests/Pest.php`
- Create: `tests/Feature/UserVisibilityTest.php` (empty placeholder — filled in Task 5)

- [ ] **Step 1: Create `tests/Pest.php`**

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->in('Feature');

// Helper: create a tenant + seeded roles, return the tenant
function createTenant(string $name = 'Acme'): \App\Domain\Tenancy\Models\Tenant
{
    return \App\Domain\Tenancy\Models\Tenant::create([
        'name'   => $name,
        'slug'   => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
        'plan'   => 'starter',
        'active' => true,
    ]);
}

// Helper: create a user with a given role inside a tenant
function createUser(
    \App\Domain\Tenancy\Models\Tenant $tenant,
    string $role = 'attendant',
    array $attrs = []
): \App\Domain\Auth\Models\User {
    $user = \App\Domain\Auth\Models\User::create(array_merge([
        'tenant_id' => $tenant->id,
        'name'      => 'User '.uniqid(),
        'email'     => 'user'.uniqid().'@test.com',
        'password'  => bcrypt('password'),
        'is_active' => true,
    ], $attrs));
    $user->assignRole($role);
    return $user;
}

// Helper: create a sector inside a tenant
function createSector(
    \App\Domain\Tenancy\Models\Tenant $tenant,
    string $name = 'Suporte'
): \App\Domain\Sector\Models\Sector {
    return \App\Domain\Sector\Models\Sector::create([
        'tenant_id'   => $tenant->id,
        'name'        => $name,
        'slug'        => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
        'color'       => '#737373',
        'active'      => true,
        'order'       => 0,
    ]);
}
```

- [ ] **Step 2: Verify Pest can be invoked**

Run: `cd app && ./vendor/bin/pest --list-tests`
Expected: no errors (even if 0 tests listed is fine at this stage)

- [ ] **Step 3: Commit**

```bash
git add tests/Pest.php
git commit -m "test: bootstrap Pest helpers for tenant/user/sector fixtures"
```

---

## Task 2: Migration — `sectors.ai_settings`

**Files:**
- Create: `database/migrations/2026_05_14_000001_add_ai_settings_to_sectors.php`

- [ ] **Step 1: Create the migration**

```php
<?php
// database/migrations/2026_05_14_000001_add_ai_settings_to_sectors.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->json('ai_settings')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('ai_settings');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_05_14_000001_add_ai_settings_to_sectors` ... `Migrated`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_14_000001_add_ai_settings_to_sectors.php
git commit -m "feat: add ai_settings column to sectors"
```

---

## Task 3: Sector model + AI Settings API + n8n action API

**Files:**
- Modify: `app/Domain/Sector/Models/Sector.php`
- Modify: `app/Http/Controllers/Api/V1/SectorController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/SectorAiSettingsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SectorAiSettingsTest.php

use App\Domain\Sector\Models\Sector;

beforeEach(function () {
    \Database\Seeders\RolesAndPermissionsSeeder::class;
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin = createUser($this->tenant, 'admin');
    $this->sector = createSector($this->tenant);
});

it('admin can update sector ai_settings', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson("/api/v1/sectors/{$this->sector->id}/ai-settings", [
            'ai_enabled'       => true,
            'ai_prompt'        => 'Você é um assistente de suporte.',
            'n8n_workflow_id'  => 'abc123',
            'n8n_webhook_path' => 'sector-ai',
        ]);

    $response->assertStatus(200)
             ->assertJsonPath('data.ai_settings.ai_enabled', true)
             ->assertJsonPath('data.ai_settings.ai_prompt', 'Você é um assistente de suporte.');
});

it('attendant cannot update sector ai_settings', function () {
    $attendant = createUser($this->tenant, 'attendant');

    $this->actingAs($attendant, 'api')
        ->putJson("/api/v1/sectors/{$this->sector->id}/ai-settings", ['ai_enabled' => true])
        ->assertStatus(403);
});

it('sector index includes ai_settings', function () {
    $this->sector->update(['ai_settings' => ['ai_enabled' => true, 'ai_prompt' => 'test']]);

    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $first = $response->json('data.0');
    $this->assertArrayHasKey('ai_settings', $first);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/SectorAiSettingsTest.php`
Expected: FAIL — route not found or method not defined

- [ ] **Step 3: Update Sector model**

In `app/Domain/Sector/Models/Sector.php`, add `'ai_settings'` to `$fillable` and `$casts`:

```php
protected $fillable = [
    'tenant_id','parent_id','name','slug','menu_key','color','icon','description','working_hours','settings','ai_settings','active','order',
];

protected $casts = [
    'working_hours' => 'array',
    'settings'      => 'array',
    'ai_settings'   => 'array',
    'active' => 'bool',
    'order'  => 'int',
];
```

- [ ] **Step 4: Add `aiSettings()` + `n8nAction()` to SectorController**

In `app/Http/Controllers/Api/V1/SectorController.php`, add these two methods and update `$present` in `index()`:

```php
// Add to the top of the file, in the $present closure inside index(), add:
// 'ai_settings' => $s->ai_settings ?? ['ai_enabled' => false, 'ai_prompt' => '', 'n8n_workflow_id' => '', 'n8n_webhook_path' => ''],
// Full closure update:
$present = function (Sector $s) use ($openCounts, &$present): array {
    return [
        'id'               => $s->id,
        'parent_id'        => $s->parent_id,
        'name'             => $s->name,
        'slug'             => $s->slug,
        'menu_key'         => $s->menu_key,
        'color'            => $s->color,
        'description'      => $s->description,
        'open_tickets'     => (int) ($openCounts[$s->id] ?? 0),
        'attendants_count' => (int) $s->attendants_count,
        'children_count'   => $s->relationLoaded('children') ? $s->children->count() : 0,
        'children'         => $s->relationLoaded('children')
            ? $s->children->map($present)->values()
            : [],
        'ai_settings'      => $s->ai_settings ?? ['ai_enabled' => false, 'ai_prompt' => '', 'n8n_workflow_id' => '', 'n8n_webhook_path' => ''],
    ];
};
```

Add the two new methods at the end of the class (before the private `uniqueSlug` method):

```php
public function aiSettings(Request $request, Sector $sector): JsonResponse
{
    abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

    $data = $request->validate([
        'ai_enabled'       => ['boolean'],
        'ai_prompt'        => ['nullable', 'string', 'max:5000'],
        'n8n_workflow_id'  => ['nullable', 'string', 'max:255'],
        'n8n_webhook_path' => ['nullable', 'string', 'max:255'],
    ]);

    $sector->ai_settings = array_merge($sector->ai_settings ?? [], $data);
    $sector->save();

    return response()->json(['data' => $sector->fresh()]);
}

public function n8nAction(Request $request, Sector $sector): JsonResponse
{
    abort_unless($sector->tenant_id === $request->user()->tenant_id, 404);

    $data = $request->validate([
        'type' => ['required', \Illuminate\Validation\Rule::in(['edit-number', 'edit-conversation'])],
    ]);

    $path = $data['type'] === 'edit-number' ? 'edit-number' : 'edit-conversation';

    app(\App\Infra\N8n\N8nClient::class)->trigger($path, [
        'sector_id' => $sector->id,
        'tenant_id' => $sector->tenant_id,
    ]);

    return response()->json(['ok' => true]);
}
```

- [ ] **Step 5: Add routes**

In `routes/api.php`, inside the `role:admin` middleware group, add after the existing sector routes:

```php
Route::put('sectors/{sector}/ai-settings', [SectorController::class, 'aiSettings']);
Route::post('sectors/{sector}/n8n-action',  [SectorController::class, 'n8nAction']);
```

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/SectorAiSettingsTest.php`
Expected: 3 tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Domain/Sector/Models/Sector.php \
        app/Http/Controllers/Api/V1/SectorController.php \
        routes/api.php \
        tests/Feature/SectorAiSettingsTest.php
git commit -m "feat: sector ai_settings API and n8n action endpoint"
```

---

## Task 4: Add `superadmin` role

**Files:**
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`
- Create: `database/migrations/2026_05_14_000002_add_superadmin_role.php`

- [ ] **Step 1: Update the seeder**

In `database/seeders/RolesAndPermissionsSeeder.php`, add after the `$attendant` block:

```php
$superadmin = Role::findOrCreate('superadmin', 'api');
$superadmin->syncPermissions(Permission::all());
```

- [ ] **Step 2: Create a migration that inserts the role for existing deployments**

```php
<?php
// database/migrations/2026_05_14_000002_add_superadmin_role.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('superadmin', 'api');
    }

    public function down(): void
    {
        Role::where('name', 'superadmin')->where('guard_name', 'api')->delete();
    }
};
```

- [ ] **Step 3: Run migration**

Run: `php artisan migrate`
Expected: `Migrating: 2026_05_14_000002_add_superadmin_role` ... `Migrated`

- [ ] **Step 4: Commit**

```bash
git add database/seeders/RolesAndPermissionsSeeder.php \
        database/migrations/2026_05_14_000002_add_superadmin_role.php
git commit -m "feat: add superadmin role via migration and seeder"
```

---

## Task 5: User visibility — sector-scoped

**Files:**
- Modify: `app/Http/Controllers/Api/V1/UserController.php`
- Create: `tests/Feature/UserVisibilityTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/UserVisibilityTest.php

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
});

it('admin sees all tenant users but not superadmin', function () {
    $admin      = createUser($this->tenant, 'admin');
    $attendant1 = createUser($this->tenant, 'attendant');
    $attendant2 = createUser($this->tenant, 'attendant');
    $superadmin = createUser($this->tenant, 'superadmin');

    $response = $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($admin->id)
                ->toContain($attendant1->id)
                ->toContain($attendant2->id)
                ->not->toContain($superadmin->id);
});

it('attendant sees only users in shared sectors', function () {
    $sector1 = createSector($this->tenant, 'Setor A');
    $sector2 = createSector($this->tenant, 'Setor B');

    $me    = createUser($this->tenant, 'attendant');
    $peer  = createUser($this->tenant, 'attendant');
    $other = createUser($this->tenant, 'attendant'); // different sector

    $me->sectors()->attach($sector1->id);
    $peer->sectors()->attach($sector1->id);
    $other->sectors()->attach($sector2->id);

    $response = $this->actingAs($me, 'api')
        ->getJson('/api/v1/users');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($me->id)
                ->toContain($peer->id)
                ->not->toContain($other->id);
});

it('attendant from a different tenant cannot see users', function () {
    $otherTenant = createTenant('Other');
    $spy = createUser($otherTenant, 'attendant');

    createUser($this->tenant, 'attendant');

    $response = $this->actingAs($spy, 'api')
        ->getJson('/api/v1/users');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toBeEmpty();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/UserVisibilityTest.php`
Expected: FAIL — admin currently returns superadmins; attendant returns all users

- [ ] **Step 3: Update `UserController::index`**

Replace the `index` method in `app/Http/Controllers/Api/V1/UserController.php`:

```php
public function index(Request $request): JsonResponse
{
    $tenantId  = $request->user()->tenant_id;
    $me        = $request->user();
    $today     = now()->startOfDay();
    $threshold = now()->subMinutes(5);

    $base = User::query()
        ->where('tenant_id', $tenantId)
        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'superadmin'))
        ->with('roles:id,name')
        ->orderBy('name');

    if ($me->hasRole('attendant') || $me->hasRole('supervisor')) {
        $mySectorIds = $me->sectors()->pluck('sectors.id');
        $base->whereHas('sectors', fn ($q) => $q->whereIn('sectors.id', $mySectorIds));
    }

    $users = $base->get(['id','name','email','phone','is_active','last_seen_at']);

    $inProgress = Ticket::query()
        ->where('tenant_id', $tenantId)
        ->whereIn('status', ['open','pending'])
        ->whereNotNull('assigned_to')
        ->select('assigned_to', DB::raw('count(*) as total'))
        ->groupBy('assigned_to')
        ->pluck('total', 'assigned_to');

    $resolved = Ticket::query()
        ->where('tenant_id', $tenantId)
        ->where('status', 'closed')
        ->where('closed_at', '>=', $today)
        ->whereNotNull('assigned_to')
        ->select('assigned_to', DB::raw('count(*) as total'))
        ->groupBy('assigned_to')
        ->pluck('total', 'assigned_to');

    return response()->json([
        'data' => $users->map(fn($u) => [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'phone'       => $u->phone,
            'is_active'   => (bool) $u->is_active,
            'role'        => optional($u->roles->first())->name ?? 'attendant',
            'in_progress' => (int) ($inProgress[$u->id] ?? 0),
            'resolved'    => (int) ($resolved[$u->id] ?? 0),
            'status'      => ($u->last_seen_at && $u->last_seen_at->gte($threshold)) ? 'online' : 'offline',
        ])->values(),
    ]);
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/UserVisibilityTest.php`
Expected: 3 tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/UserController.php \
        tests/Feature/UserVisibilityTest.php
git commit -m "feat: sector-scoped user visibility, hide superadmins from tenant endpoints"
```

---

## Task 6: WhatsApp session — quota enforcement + attendant permissions

**Files:**
- Create: `app/Policies/WhatsappSessionPolicy.php`
- Modify: `app/Http/Controllers/Api/V1/WhatsappSessionController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/WhatsappSessionQuotaTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/WhatsappSessionQuotaTest.php

use App\Domain\Ticket\Models\WhatsappSession;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin  = createUser($this->tenant, 'admin');
});

it('admin can create a session', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/whatsapp/sessions', [
            'instance_name' => 'test-instance-'.uniqid(),
            'is_primary'    => true,
        ]);

    $response->assertStatus(201);
});

it('setting a session as primary unsets previous primary', function () {
    WhatsappSession::create([
        'tenant_id'     => $this->tenant->id,
        'instance_name' => 'first-'.uniqid(),
        'display_name'  => 'First',
        'state'         => 'qr_pending',
        'is_primary'    => true,
    ]);

    $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/whatsapp/sessions', [
            'instance_name' => 'second-'.uniqid(),
            'is_primary'    => true,
        ])
        ->assertStatus(201);

    expect(
        WhatsappSession::where('tenant_id', $this->tenant->id)->where('is_primary', true)->count()
    )->toBe(1);
});

it('rejects creation when max_sessions reached', function () {
    $settings = $this->tenant->settings ?? [];
    $settings['max_sessions'] = 2;
    $this->tenant->settings = $settings;
    $this->tenant->save();

    WhatsappSession::create(['tenant_id'=>$this->tenant->id,'instance_name'=>'s1','display_name'=>'S1','state'=>'qr_pending','is_primary'=>false]);
    WhatsappSession::create(['tenant_id'=>$this->tenant->id,'instance_name'=>'s2','display_name'=>'S2','state'=>'qr_pending','is_primary'=>false]);

    $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/whatsapp/sessions', ['instance_name' => 'overflow-'.uniqid()])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Limite'));
});

it('attendant can create a non-primary session', function () {
    $attendant = createUser($this->tenant, 'attendant');

    $this->actingAs($attendant, 'api')
        ->postJson('/api/v1/whatsapp/sessions', [
            'instance_name' => 'att-'.uniqid(),
            'is_primary'    => false,
        ])
        ->assertStatus(201);
});

it('attendant cannot delete a session', function () {
    $session = WhatsappSession::create([
        'tenant_id'     => $this->tenant->id,
        'instance_name' => 'del-'.uniqid(),
        'display_name'  => 'Del',
        'state'         => 'qr_pending',
        'is_primary'    => false,
    ]);

    $attendant = createUser($this->tenant, 'attendant');

    $this->actingAs($attendant, 'api')
        ->deleteJson("/api/v1/whatsapp/sessions/{$session->id}")
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/WhatsappSessionQuotaTest.php`
Expected: multiple failures

- [ ] **Step 3: Create `WhatsappSessionPolicy`**

```php
<?php
// app/Policies/WhatsappSessionPolicy.php

namespace App\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Ticket\Models\WhatsappSession;

class WhatsappSessionPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool  { return true; /* quota checked in controller */ }
    public function delete(User $user, WhatsappSession $session): bool { return $user->isAdmin(); }
    public function reconnect(User $user, WhatsappSession $session): bool { return true; }
}
```

Register the policy in `app/Providers/AppServiceProvider.php` inside the `boot()` method:

```php
\Illuminate\Support\Facades\Gate::policy(
    \App\Domain\Ticket\Models\WhatsappSession::class,
    \App\Policies\WhatsappSessionPolicy::class
);
```

- [ ] **Step 4: Update `WhatsappSessionController::store`**

Replace the `store` method in `app/Http/Controllers/Api/V1/WhatsappSessionController.php`:

```php
public function store(Request $request): JsonResponse
{
    $tenantId = $request->user()->tenant_id;
    $tenant   = $request->user()->tenant;

    $maxSessions = (int) ($tenant->settings['max_sessions'] ?? 3);
    $current     = WhatsappSession::where('tenant_id', $tenantId)->count();

    if ($current >= $maxSessions) {
        return response()->json(['message' => "Limite de {$maxSessions} sessões atingido."], 422);
    }

    $data = $request->validate([
        'instance_name' => ['required','string','max:64','unique:whatsapp_sessions,instance_name'],
        'display_name'  => ['nullable','string','max:191'],
        'is_primary'    => ['boolean'],
    ]);

    $wantsPrimary = (bool) ($data['is_primary'] ?? false);

    // Attendants cannot set a session as primary
    if ($wantsPrimary && ! $request->user()->isAdmin()) {
        $wantsPrimary = false;
    }

    $session = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $tenantId, $wantsPrimary) {
        if ($wantsPrimary) {
            WhatsappSession::where('tenant_id', $tenantId)->update(['is_primary' => false]);
        }

        return WhatsappSession::create([
            'tenant_id'      => $tenantId,
            'instance_name'  => $data['instance_name'],
            'display_name'   => $data['display_name'] ?? $data['instance_name'],
            'state'          => 'qr_pending',
            'is_primary'     => $wantsPrimary,
            'webhook_events' => ['MESSAGES_UPSERT','MESSAGES_UPDATE','CONNECTION_UPDATE','QRCODE_UPDATED'],
        ]);
    });

    $this->evolution->createInstance($session->instance_name);
    $this->evolution->setWebhook(
        $session->instance_name,
        url('/api/v1/webhooks/evolution'),
        $session->webhook_events ?? []
    );

    return response()->json(['data' => $session], 201);
}
```

Also add `destroy` authorization check at the top of `destroy`:

```php
public function destroy(WhatsappSession $session): JsonResponse
{
    abort_unless($session->tenant_id === request()->user()->tenant_id, 404);
    abort_unless(request()->user()->isAdmin(), 403);

    $this->evolution->deleteInstance($session->instance_name);
    $session->delete();
    return response()->json(['ok' => true]);
}
```

- [ ] **Step 5: Restructure session routes in `routes/api.php`**

Replace the existing whatsapp session routes block (currently inside `role:admin`) with this structure. The outer `role:admin` group remains for other routes; remove the session routes from it and add the new structure:

```php
// Inside the jwt.auth|tenant middleware group, OUTSIDE the role:admin group:
Route::get('whatsapp/sessions',              [WhatsappSessionController::class, 'index']);
Route::get('whatsapp/sessions/{session}/qr', [WhatsappSessionController::class, 'qr']);

Route::middleware(['role:admin|supervisor|attendant'])->group(function () {
    Route::post('whatsapp/sessions',                        [WhatsappSessionController::class, 'store']);
    Route::post('whatsapp/sessions/{session}/reconnect',    [WhatsappSessionController::class, 'reconnect']);
});

Route::middleware(['role:admin'])->group(function () {
    Route::delete('whatsapp/sessions/{session}', [WhatsappSessionController::class, 'destroy']);
    // ... (keep other admin routes here)
});
```

The final `routes/api.php` authenticated group looks like:

```php
Route::middleware(['jwt.auth','tenant'])->group(function () {
    Route::get('auth/me',     [AuthController::class, 'me']);
    Route::post('auth/logout',[AuthController::class, 'logout']);
    Route::put('auth/profile',[AuthController::class, 'updateProfile']);

    Route::get('presence/ping',    [PresenceController::class, 'ping']);
    Route::post('presence/typing', [PresenceController::class, 'typing']);

    Route::get('sectors', [SectorController::class, 'index']);

    Route::get('tickets',                              [TicketController::class, 'index']);
    Route::get('tickets/{ticket}',                     [TicketController::class, 'show']);
    Route::get('tickets/{ticket}/messages',            [TicketController::class, 'messages']);
    Route::post('tickets/{ticket}/messages',           [TicketController::class, 'send']);
    Route::post('tickets/{ticket}/transfer/sector',    [TicketController::class, 'transferToSector']);
    Route::post('tickets/{ticket}/transfer/user',      [TicketController::class, 'transferToUser']);
    Route::post('tickets/{ticket}/close',              [TicketController::class, 'close']);

    Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);

    // WhatsApp sessions — read available to all authenticated
    Route::get('whatsapp/sessions',              [WhatsappSessionController::class, 'index']);
    Route::get('whatsapp/sessions/{session}/qr', [WhatsappSessionController::class, 'qr']);

    // Create + reconnect: attendants and above
    Route::middleware(['role:admin|supervisor|attendant'])->group(function () {
        Route::post('whatsapp/sessions',                     [WhatsappSessionController::class, 'store']);
        Route::post('whatsapp/sessions/{session}/reconnect', [WhatsappSessionController::class, 'reconnect']);
    });

    Route::middleware(['role:admin'])->group(function () {
        Route::delete('whatsapp/sessions/{session}', [WhatsappSessionController::class, 'destroy']);

        Route::apiResource('users', UserController::class)->only(['index','store','update','destroy']);

        Route::post('sectors',                               [SectorController::class, 'store']);
        Route::put('sectors/{sector}',                       [SectorController::class, 'update']);
        Route::delete('sectors/{sector}',                    [SectorController::class, 'destroy']);
        Route::put('sectors/{sector}/ai-settings',           [SectorController::class, 'aiSettings']);
        Route::post('sectors/{sector}/n8n-action',           [SectorController::class, 'n8nAction']);

        Route::get('tenant',         [TenantController::class, 'show']);
        Route::put('tenant',         [TenantController::class, 'update']);
        Route::post('tenant/logo',   [TenantController::class, 'uploadLogo']);
        Route::put('tenant/gateway', [TenantController::class, 'updateGateway']);
    });
});
```

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/WhatsappSessionQuotaTest.php`
Expected: 5 tests pass (note: the factory-based test needs the inline create approach already in the test)

- [ ] **Step 7: Commit**

```bash
git add app/Policies/WhatsappSessionPolicy.php \
        app/Http/Controllers/Api/V1/WhatsappSessionController.php \
        app/Providers/AppServiceProvider.php \
        routes/api.php \
        tests/Feature/WhatsappSessionQuotaTest.php
git commit -m "feat: WhatsApp session quota, primary enforcement, and attendant permissions"
```

---

## Task 7: TenantController — gateway settings

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TenantController.php`
- Create: `tests/Feature/GatewaySettingsTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/GatewaySettingsTest.php

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin  = createUser($this->tenant, 'admin');
});

it('admin can save webhook gateway settings', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/tenant/gateway', [
            'type'   => 'webhook',
            'config' => [
                'url'           => 'https://gateway.example.com/send',
                'secret_header' => 'X-Secret',
                'secret_value'  => 'abc123',
            ],
        ]);

    $response->assertStatus(200)
             ->assertJsonPath('data.integrations.gateway.type', 'webhook')
             ->assertJsonPath('data.integrations.gateway.config.url', 'https://gateway.example.com/send');
});

it('admin can switch back to evolution gateway', function () {
    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/tenant/gateway', ['type' => 'evolution', 'config' => []])
        ->assertStatus(200)
        ->assertJsonPath('data.integrations.gateway.type', 'evolution');
});

it('gateway is returned in tenant show response', function () {
    $settings = $this->tenant->settings ?? [];
    $settings['gateway'] = ['type' => 'webhook', 'config' => ['url' => 'https://wh.example.com']];
    $this->tenant->settings = $settings;
    $this->tenant->save();

    $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/tenant')
        ->assertStatus(200)
        ->assertJsonPath('data.integrations.gateway.type', 'webhook');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/GatewaySettingsTest.php`
Expected: FAIL — route not found

- [ ] **Step 3: Add `updateGateway` to TenantController and expose gateway in `show`**

In `app/Http/Controllers/Api/V1/TenantController.php`:

Update the `show` method's `'integrations'` block:

```php
'integrations' => [
    'evolution' => [
        'url'         => (string) config('services.evolution.url'),
        'api_key_set' => filled(config('services.evolution.api_key')),
        'webhook_url' => url('/api/v1/webhooks/evolution'),
    ],
    'gateway' => $settings['gateway'] ?? ['type' => 'evolution', 'config' => []],
],
```

Add the `updateGateway` method to the class:

```php
public function updateGateway(Request $request): JsonResponse
{
    $tenant = $request->user()->tenant;

    $data = $request->validate([
        'type'                 => ['required', \Illuminate\Validation\Rule::in(['evolution', 'webhook'])],
        'config'               => ['nullable', 'array'],
        'config.url'           => ['nullable', 'string', 'max:255'],
        'config.secret_header' => ['nullable', 'string', 'max:64'],
        'config.secret_value'  => ['nullable', 'string', 'max:255'],
        'config.event_mapping' => ['nullable', 'string', 'max:2000'],
    ]);

    $settings            = $tenant->settings ?? [];
    $settings['gateway'] = $data;
    $tenant->settings    = $settings;
    $tenant->save();

    return $this->show($request);
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/GatewaySettingsTest.php`
Expected: 3 tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/TenantController.php \
        tests/Feature/GatewaySettingsTest.php
git commit -m "feat: gateway settings endpoint and expose in tenant show"
```

---

## Task 8: ConversationOrchestrator — AI trigger job

**Files:**
- Create: `app/Jobs/TriggerAiWebhook.php`
- Modify: `app/Domain/Ticket/Services/ConversationOrchestrator.php`

- [ ] **Step 1: Create `TriggerAiWebhook` job**

```php
<?php
// app/Jobs/TriggerAiWebhook.php

namespace App\Jobs;

use App\Infra\N8n\N8nClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerAiWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $webhookPath,
        public readonly array  $payload,
    ) {}

    public function handle(N8nClient $n8n): void
    {
        $n8n->trigger($this->webhookPath, $this->payload);
    }
}
```

- [ ] **Step 2: Update `ConversationOrchestrator::handleInbound` to dispatch AI webhook**

In `app/Domain/Ticket/Services/ConversationOrchestrator.php`, after the line `$ticket->update(['last_message_at' => now()]);` (the second one inside the transaction, after message persistence), add the AI trigger block.

The full relevant section at the end of `handleInbound` (inside the `DB::transaction` closure), after message persistence and the `#menu` check, after the `elseif` block for queued tickets, add:

```php
// Dispatch AI webhook if sector has ai_enabled
if (in_array($ticket->status, ['open', 'pending'], true) && $ticket->sector_id && $isText) {
    $sector     = $ticket->sector;
    $aiSettings = $sector?->ai_settings ?? [];
    if (! empty($aiSettings['ai_enabled']) && ! empty($aiSettings['n8n_webhook_path'])) {
        \App\Jobs\TriggerAiWebhook::dispatch($aiSettings['n8n_webhook_path'], [
            'tenant_id'   => $tenantId,
            'ticket_id'   => $ticket->id,
            'sector_id'   => $ticket->sector_id,
            'prompt'      => $aiSettings['ai_prompt'] ?? '',
            'message'     => $message->body,
            'client'      => ['phone' => $client->phone, 'name' => $client->name],
        ]);
    }
}
```

Place this block after the closing brace of the `elseif ($ticket->status === 'queued' ...)` block, still inside `DB::transaction`.

- [ ] **Step 3: Run full test suite**

Run: `./vendor/bin/pest`
Expected: all tests pass (no regression)

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/TriggerAiWebhook.php \
        app/Domain/Ticket/Services/ConversationOrchestrator.php
git commit -m "feat: dispatch AI webhook on open ticket messages when sector has ai_enabled"
```

---

## Task 9: Settings UI — Gateway card + n8n per-sector

**Files:**
- Create: `resources/js/Components/vora/SectorAiSettings.vue`
- Modify: `resources/js/Pages/Settings/Index.vue`

- [ ] **Step 1: Create `SectorAiSettings.vue` component**

```vue
<!-- resources/js/Components/vora/SectorAiSettings.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Switch } from '@/Components/ui/switch';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Separator } from '@/Components/ui/separator';

const sectors = ref([]);
const saving = ref({});

async function load() {
    const { data } = await axios.get('/api/v1/sectors');
    // flatten parent + children into a single list with ai_settings
    const flat = [];
    for (const s of (data.data || [])) {
        flat.push(s);
        for (const c of (s.children || [])) flat.push(c);
    }
    sectors.value = flat.map(s => ({
        ...s,
        ai: {
            ai_enabled:       s.ai_settings?.ai_enabled ?? false,
            ai_prompt:        s.ai_settings?.ai_prompt ?? '',
            n8n_workflow_id:  s.ai_settings?.n8n_workflow_id ?? '',
            n8n_webhook_path: s.ai_settings?.n8n_webhook_path ?? '',
        },
    }));
}

async function save(sector) {
    saving.value[sector.id] = true;
    try {
        await axios.put(`/api/v1/sectors/${sector.id}/ai-settings`, sector.ai);
        toast.success(`${sector.name}: configurações salvas`);
    } catch {
        toast.error('Falha ao salvar');
    } finally {
        saving.value[sector.id] = false;
    }
}

async function n8nAction(sector, type) {
    try {
        await axios.post(`/api/v1/sectors/${sector.id}/n8n-action`, { type });
        toast.success(`Ação "${type}" disparada`);
    } catch {
        toast.error('Falha ao disparar ação');
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div v-if="!sectors.length" class="text-[12.5px] text-muted-foreground py-4 text-center">
            Nenhum setor encontrado.
        </div>

        <div v-for="sector in sectors" :key="sector.id"
             class="border border-border rounded-lg p-4 space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[13px] font-medium">{{ sector.name }}</span>
                <div class="flex items-center gap-2 text-[12px] text-muted-foreground">
                    IA ativa
                    <Switch v-model:checked="sector.ai.ai_enabled" />
                </div>
            </div>

            <div v-if="sector.ai.ai_enabled" class="space-y-3">
                <div class="space-y-1.5">
                    <label class="text-[12px] font-medium text-foreground">Prompt do bot</label>
                    <Textarea v-model="sector.ai.ai_prompt"
                              placeholder="Você é um assistente de suporte da empresa..."
                              class="text-[12.5px] min-h-[80px]" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Workflow ID (n8n)</label>
                        <Input v-model="sector.ai.n8n_workflow_id" placeholder="abc123" class="text-[12.5px]" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[12px] font-medium text-foreground">Webhook path (n8n)</label>
                        <Input v-model="sector.ai.n8n_webhook_path" placeholder="sector-ai" class="text-[12.5px]" />
                    </div>
                </div>
            </div>

            <Separator />

            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="n8nAction(sector, 'edit-number')">
                        Editar número
                    </Button>
                    <Button variant="outline" size="sm" @click="n8nAction(sector, 'edit-conversation')">
                        Editar conversa
                    </Button>
                </div>
                <Button variant="default" size="sm"
                        :disabled="saving[sector.id]"
                        @click="save(sector)">
                    {{ saving[sector.id] ? 'Salvando…' : 'Salvar' }}
                </Button>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Update `Settings/Index.vue` — integrations tab**

In `resources/js/Pages/Settings/Index.vue`:

**2a. Add imports** at the top of `<script setup>`:

```js
import SectorAiSettings from '@/Components/vora/SectorAiSettings.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
```

**2b. Add gateway reactive state** after `const integrations = ref(null);`:

```js
const gateway = ref({ type: 'evolution', config: {} });
const gatewaySaving = ref(false);
```

**2c. Update `applyTenant`** — after `integrations.value = data.data.integrations;` in `loadTenant()`, add:

```js
if (data.data.integrations?.gateway) {
    gateway.value = data.data.integrations.gateway;
}
```

**2d. Add `saveGateway` function** after `saveTenant`:

```js
async function saveGateway() {
    gatewaySaving.value = true;
    try {
        const { data } = await axios.put('/api/v1/tenant/gateway', gateway.value);
        if (data.data.integrations?.gateway) gateway.value = data.data.integrations.gateway;
        toast.success('Gateway atualizado');
    } catch {
        toast.error('Falha ao salvar gateway');
    } finally {
        gatewaySaving.value = false;
    }
}
```

**2e. Replace the entire integrations `<TabsContent value="integrations">` section** with:

```vue
<!-- ===== Integrações ===== -->
<TabsContent value="integrations" class="space-y-4">

    <!-- Gateway de Mensagens -->
    <Card>
        <CardHeader>
            <CardTitle>Gateway de Mensagens</CardTitle>
            <CardDescription>
                Selecione e configure o provedor de envio de mensagens WhatsApp.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-4 max-w-xl">
                <div class="space-y-1.5">
                    <label class="text-[12px] font-medium text-foreground">Tipo</label>
                    <Select v-model="gateway.type">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecione o gateway" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="evolution">Evolution API</SelectItem>
                            <SelectItem value="webhook">Webhook Genérico</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Evolution: read-only -->
                <template v-if="gateway.type === 'evolution'">
                    <dl class="divide-y divide-border border border-border rounded-lg overflow-hidden">
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-[12.5px] text-muted-foreground">Endpoint</dt>
                            <dd class="font-mono text-[12.5px] text-foreground truncate">
                                {{ integrations?.evolution?.url || '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-[12.5px] text-muted-foreground">Chave de API</dt>
                            <dd>
                                <Badge :variant="integrations?.evolution?.api_key_set ? 'default' : 'outline'">
                                    {{ integrations?.evolution?.api_key_set ? 'Configurada' : 'Não configurada' }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-[12.5px] text-muted-foreground">Webhook</dt>
                            <dd class="font-mono text-[12.5px] text-foreground truncate">
                                {{ integrations?.evolution?.webhook_url || '—' }}
                            </dd>
                        </div>
                    </dl>
                </template>

                <!-- Generic webhook: editable -->
                <template v-else>
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <label class="text-[12px] font-medium text-foreground">URL de envio</label>
                            <Input v-model="gateway.config.url" placeholder="https://gateway.example.com/send" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="text-[12px] font-medium text-foreground">Header secreto</label>
                                <Input v-model="gateway.config.secret_header" placeholder="X-Secret" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[12px] font-medium text-foreground">Valor do header</label>
                                <Input v-model="gateway.config.secret_value" placeholder="meu-secret" type="password" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[12px] font-medium text-foreground">Mapeamento de eventos (JSON)</label>
                            <Textarea v-model="gateway.config.event_mapping"
                                      placeholder='{"MESSAGE_RECEIVED": "message.upsert"}'
                                      class="font-mono text-[12px] min-h-[80px]" />
                        </div>
                    </div>
                </template>

                <div class="flex justify-end">
                    <Button variant="default" :disabled="gatewaySaving" @click="saveGateway">
                        {{ gatewaySaving ? 'Salvando…' : 'Salvar gateway' }}
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- n8n por Setor -->
    <Card>
        <CardHeader>
            <CardTitle>n8n por Setor</CardTitle>
            <CardDescription>
                Configure o bot de IA e ações n8n para cada setor.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <SectorAiSettings />
        </CardContent>
    </Card>

</TabsContent>
```

- [ ] **Step 3: Verify app compiles (dev server)**

Run: `npm run dev` (or `npm run build`)
Expected: no compilation errors

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/vora/SectorAiSettings.vue \
        resources/js/Pages/Settings/Index.vue
git commit -m "feat: gateway card redesign with multi-API support and n8n per-sector UI"
```

---

## Task 10: Final integration check

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/pest`
Expected: all tests green

- [ ] **Step 2: Run migrations fresh (smoke test)**

Run: `php artisan migrate:fresh --seed`
Expected: all migrations run without errors, seeders complete

- [ ] **Step 3: Final commit (if any loose files)**

```bash
git status
# commit any remaining unstaged changes
```
