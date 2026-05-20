# User-Sector Binding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir vincular usuário a um ou mais setores pela tela de Usuários, com validação obrigatória para atendentes.

**Architecture:** Pequena alteração full-stack: migration de limpeza no pivot `attendant_sectors`, ajuste em `UserController` para aceitar `sector_ids` em store/update, accessor/eager-load de `sectors` nas respostas, e novo campo multi-select na tela `Users/Index.vue`.

**Tech Stack:** Laravel 11, Pest (PHP), Vue 3 + Inertia, Tailwind, shadcn-vue components.

---

## File Structure

**Create:**
- `app/database/migrations/2026_05_20_000001_drop_unused_columns_from_attendant_sectors.php`
- `app/tests/Feature/UserSectorAssignmentTest.php`

**Modify:**
- `app/app/Domain/Auth/Models/User.php` — remover `withPivot(['is_default','priority'])` da relação `sectors()`
- `app/app/Domain/Sector/Models/Sector.php` — remover `withPivot(['is_default','priority'])` da relação `attendants()`
- `app/app/Http/Controllers/Api/V1/UserController.php` — store/update aceitam `sector_ids`; index/present incluem sectors
- `app/resources/js/Pages/Users/Index.vue` — campo "Setores" no form + coluna "Setores" na tabela

---

### Task 1: Migration que dropa colunas não usadas

**Files:**
- Create: `app/database/migrations/2026_05_20_000001_drop_unused_columns_from_attendant_sectors.php`

- [ ] **Step 1: Criar migration**

Arquivo `app/database/migrations/2026_05_20_000001_drop_unused_columns_from_attendant_sectors.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendant_sectors', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('attendant_sectors', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(1);
        });
    }
};
```

- [ ] **Step 2: Rodar migration localmente**

Run: `cd app && php artisan migrate`
Expected: "Migrated: 2026_05_20_000001_drop_unused_columns_from_attendant_sectors"

- [ ] **Step 3: Commit**

```bash
git add app/database/migrations/2026_05_20_000001_drop_unused_columns_from_attendant_sectors.php
git commit -m "chore: drop unused is_default/priority from attendant_sectors"
```

---

### Task 2: Remover withPivot dos models

**Files:**
- Modify: `app/app/Domain/Auth/Models/User.php:51`
- Modify: `app/app/Domain/Sector/Models/Sector.php:30`

- [ ] **Step 1: Editar User.php linha 51**

Substituir:
```php
public function sectors(): BelongsToMany { return $this->belongsToMany(Sector::class, 'attendant_sectors')->withPivot(['is_default','priority'])->withTimestamps(); }
```

Por:
```php
public function sectors(): BelongsToMany { return $this->belongsToMany(Sector::class, 'attendant_sectors')->withTimestamps(); }
```

- [ ] **Step 2: Editar Sector.php linha 30**

Substituir:
```php
public function attendants(): BelongsToMany { return $this->belongsToMany(User::class, 'attendant_sectors')->withPivot(['is_default','priority'])->withTimestamps(); }
```

Por:
```php
public function attendants(): BelongsToMany { return $this->belongsToMany(User::class, 'attendant_sectors')->withTimestamps(); }
```

- [ ] **Step 3: Rodar testes existentes pra garantir que nada quebrou**

Run: `cd app && ./vendor/bin/pest --filter=UserVisibilityTest`
Expected: 3 passed

- [ ] **Step 4: Commit**

```bash
git add app/app/Domain/Auth/Models/User.php app/app/Domain/Sector/Models/Sector.php
git commit -m "chore: drop pivot fields from sectors relations"
```

---

### Task 3: Feature tests para vínculo de setor

**Files:**
- Create: `app/tests/Feature/UserSectorAssignmentTest.php`

- [ ] **Step 1: Escrever testes (RED)**

Arquivo `app/tests/Feature/UserSectorAssignmentTest.php`:

```php
<?php

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin  = createUser($this->tenant, 'admin');
});

it('creates attendant linked to one or more sectors', function () {
    $s1 = createSector($this->tenant, 'Suporte');
    $s2 = createSector($this->tenant, 'Comercial');

    $response = $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/users', [
            'name'       => 'Maria',
            'email'      => 'maria@test.com',
            'password'   => 'secret123',
            'role'       => 'attendant',
            'sector_ids' => [$s1->id, $s2->id],
        ]);

    $response->assertStatus(201);
    $userId = $response->json('data.id');
    $user   = \App\Domain\Auth\Models\User::find($userId);

    expect($user->sectors()->pluck('sectors.id')->all())
        ->toEqualCanonicalizing([$s1->id, $s2->id]);
});

it('rejects attendant creation without sectors', function () {
    $response = $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/users', [
            'name'     => 'Sem Setor',
            'email'    => 'sem@test.com',
            'password' => 'secret123',
            'role'     => 'attendant',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['sector_ids']);
});

it('allows admin creation without sectors', function () {
    $response = $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/users', [
            'name'     => 'Outro Admin',
            'email'    => 'admin2@test.com',
            'password' => 'secret123',
            'role'     => 'admin',
        ]);

    $response->assertStatus(201);
});

it('allows supervisor creation without sectors', function () {
    $response = $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/users', [
            'name'     => 'Super',
            'email'    => 'sup@test.com',
            'password' => 'secret123',
            'role'     => 'supervisor',
        ]);

    $response->assertStatus(201);
});

it('syncs sectors on update', function () {
    $s1 = createSector($this->tenant, 'A');
    $s2 = createSector($this->tenant, 'B');
    $s3 = createSector($this->tenant, 'C');

    $u = createUser($this->tenant, 'attendant');
    $u->sectors()->sync([$s1->id, $s2->id]);

    $response = $this->withToken(jwtToken($this->admin))
        ->putJson("/api/v1/users/{$u->id}", [
            'sector_ids' => [$s2->id, $s3->id],
        ]);

    $response->assertStatus(200);
    expect($u->fresh()->sectors()->pluck('sectors.id')->all())
        ->toEqualCanonicalizing([$s2->id, $s3->id]);
});

it('rejects sector_ids from other tenant', function () {
    $other = createTenant('Other');
    $alien = createSector($other, 'Alien');

    $response = $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/users', [
            'name'       => 'Hack',
            'email'      => 'hack@test.com',
            'password'   => 'secret123',
            'role'       => 'attendant',
            'sector_ids' => [$alien->id],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['sector_ids.0']);
});

it('index response includes sectors array per user', function () {
    $s1 = createSector($this->tenant, 'Suporte');
    $u  = createUser($this->tenant, 'attendant');
    $u->sectors()->attach($s1->id);

    $response = $this->withToken(jwtToken($this->admin))
        ->getJson('/api/v1/users');

    $row = collect($response->json('data'))->firstWhere('id', $u->id);
    expect($row['sectors'])->toHaveCount(1);
    expect($row['sectors'][0]['id'])->toBe($s1->id);
    expect($row['sectors'][0]['name'])->toBe('Suporte');
});
```

- [ ] **Step 2: Rodar e verificar que falha (todos os testes vão dar RED)**

Run: `cd app && ./vendor/bin/pest --filter=UserSectorAssignmentTest`
Expected: FAIL — store ainda não valida sector_ids e response não inclui sectors

- [ ] **Step 3: Commit (test files only — TDD)**

```bash
git add app/tests/Feature/UserSectorAssignmentTest.php
git commit -m "test: failing tests for user-sector binding"
```

---

### Task 4: Backend — UserController aceitando sector_ids

**Files:**
- Modify: `app/app/Http/Controllers/Api/V1/UserController.php`

- [ ] **Step 1: Atualizar `store()` para validar e sincronizar setores**

Substituir o método `store()` inteiro (linhas 69-94) por:

```php
public function store(Request $request): JsonResponse
{
    $tenantId = $request->user()->tenant_id;
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

    $data = $request->validate([
        'name'         => ['required','string','max:120'],
        'email'        => ['required','email','max:160', Rule::unique('users','email')->whereNull('deleted_at')],
        'phone'        => ['nullable','string','max:32'],
        'password'     => ['required','string','min:8'],
        'role'         => ['required', Rule::in(['admin','supervisor','attendant'])],
        'is_active'    => ['boolean'],
        'sector_ids'   => [
            Rule::requiredIf(fn () => $request->input('role') === 'attendant'),
            'array',
        ],
        'sector_ids.*' => [
            'integer',
            Rule::exists('sectors','id')->where('tenant_id', $tenantId),
        ],
    ]);

    $user = User::create([
        'tenant_id' => $tenantId,
        'name'      => $data['name'],
        'email'     => $data['email'],
        'phone'     => $data['phone'] ?? null,
        'password'  => Hash::make($data['password']),
        'is_active' => $data['is_active'] ?? true,
    ]);
    $user->syncRoles([$data['role']]);

    if (array_key_exists('sector_ids', $data)) {
        $user->sectors()->sync($data['sector_ids']);
    }

    return response()->json(['data' => $this->present($user)], 201);
}
```

- [ ] **Step 2: Atualizar `update()` para aceitar `sector_ids`**

Substituir `update()` (linhas 96-122) por:

```php
public function update(Request $request, User $user): JsonResponse
{
    abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

    $tenantId = $user->tenant_id;
    $effectiveRole = $request->input('role') ?? optional($user->roles->first())->name;

    $data = $request->validate([
        'name'         => ['sometimes','required','string','max:120'],
        'email'        => ['sometimes','required','email','max:160', Rule::unique('users','email')->ignore($user->id)->whereNull('deleted_at')],
        'phone'        => ['nullable','string','max:32'],
        'password'     => ['nullable','string','min:8'],
        'role'         => ['sometimes','required', Rule::in(['admin','supervisor','attendant'])],
        'is_active'    => ['boolean'],
        'sector_ids'   => [
            Rule::requiredIf(fn () => $effectiveRole === 'attendant' && $request->has('sector_ids')),
            'array',
        ],
        'sector_ids.*' => [
            'integer',
            Rule::exists('sectors','id')->where('tenant_id', $tenantId),
        ],
    ]);

    if (! empty($data['password'])) {
        $user->password = Hash::make($data['password']);
    }
    unset($data['password']);

    $role = $data['role'] ?? null;
    unset($data['role']);

    $sectorIds = null;
    if (array_key_exists('sector_ids', $data)) {
        $sectorIds = $data['sector_ids'];
        unset($data['sector_ids']);
    }

    $user->fill($data)->save();
    if ($role) $user->syncRoles([$role]);
    if ($sectorIds !== null) $user->sectors()->sync($sectorIds);

    return response()->json(['data' => $this->present($user->fresh('roles'))]);
}
```

- [ ] **Step 3: Incluir `sectors` no `present()` e no `index()`**

Substituir o método `present()` (linhas 133-145) por:

```php
private function present(User $user): array
{
    $user->loadMissing(['roles:id,name', 'sectors:id,name,color']);
    return [
        'id'        => $user->id,
        'name'      => $user->name,
        'email'     => $user->email,
        'phone'     => $user->phone,
        'is_active' => (bool) $user->is_active,
        'role'      => optional($user->roles->first())->name ?? 'attendant',
        'status'    => 'offline',
        'sectors'   => $user->sectors->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'color' => $s->color,
        ])->values(),
    ];
}
```

E atualizar `index()` — na linha 27 trocar `->with('roles:id,name')` por `->with(['roles:id,name', 'sectors:id,name,color'])`. No `map()` (linhas 55-65) adicionar:

```php
'sectors' => $u->sectors->map(fn($s) => [
    'id'    => $s->id,
    'name'  => $s->name,
    'color' => $s->color,
])->values(),
```

como última chave antes do fechamento.

- [ ] **Step 4: Rodar testes**

Run: `cd app && ./vendor/bin/pest --filter=UserSectorAssignmentTest`
Expected: 7 passed

- [ ] **Step 5: Rodar suite completa (regressão)**

Run: `cd app && ./vendor/bin/pest`
Expected: todos passing

- [ ] **Step 6: Commit**

```bash
git add app/app/Http/Controllers/Api/V1/UserController.php
git commit -m "feat: UserController accepts and returns sector_ids"
```

---

### Task 5: Frontend — campo "Setores" no form + coluna na tabela

**Files:**
- Modify: `app/resources/js/Pages/Users/Index.vue`

- [ ] **Step 1: Carregar setores ao montar**

No bloco `<script setup>`, abaixo de `const users = ref([]);` (linha 15), adicionar:

```javascript
const sectors = ref([]);

async function loadSectors() {
    try {
        const { data } = await axios.get('/api/v1/sectors');
        const flat = [];
        const walk = (list) => list.forEach(s => {
            flat.push({ id: s.id, name: s.name, color: s.color });
            if (s.children?.length) walk(s.children);
        });
        walk(data.data || []);
        sectors.value = flat;
    } catch (_) {}
}
```

E em `form` (linhas 22-25) adicionar `sector_ids: []`:

```javascript
const form = reactive({
    name: '', email: '', phone: '', password: '',
    role: 'attendant', is_active: true, sector_ids: [],
});
```

Em `resetForm()` (linha 53) idem:

```javascript
Object.assign(form, { name: '', email: '', phone: '', password: '', role: 'attendant', is_active: true, sector_ids: [] });
```

Em `openEdit()` (linhas 63-71) adicionar mapeamento dos setores atuais:

```javascript
function openEdit(u) {
    editing.value = u;
    Object.assign(form, {
        name: u.name, email: u.email, phone: u.phone || '',
        password: '', role: u.role, is_active: u.is_active,
        sector_ids: (u.sectors || []).map(s => s.id),
    });
    formError.value = null;
    dialogOpen.value = true;
}
```

Em `onMounted(load)` (linha 110), trocar por:

```javascript
onMounted(() => { load(); loadSectors(); });
```

- [ ] **Step 2: Adicionar validação client-side no save()**

Substituir o início de `save()` (linhas 73-90):

```javascript
async function save() {
    if (form.role === 'attendant' && form.sector_ids.length === 0) {
        formError.value = 'Selecione pelo menos um setor para atendentes.';
        return;
    }
    saving.value = true;
    formError.value = null;
    try {
        const payload = { ...form };
        if (editing.value && !payload.password) delete payload.password;
        if (editing.value) {
            await axios.put(`/api/v1/users/${editing.value.id}`, payload);
        } else {
            await axios.post('/api/v1/users', payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message || 'Falha ao salvar. Verifique os dados.';
    } finally {
        saving.value = false;
    }
}
```

- [ ] **Step 3: Adicionar campo "Setores" no template do dialog**

No template, dentro do `<form>`, depois do bloco do "Papel" (final do grid, depois da linha 240 `</div>` que fecha o select), antes do `</div>` que fecha o grid (linha 241), adicionar uma nova section fora do grid (após o `</div>` do grid, antes do checkbox `is_active`):

Localizar o fechamento `</div>` na linha 241 (logo após o `</select></div>`) e logo abaixo dele, antes da `<label class="flex items-center gap-2.5...` (linha 243), inserir:

```html
                <div class="space-y-1.5">
                    <label class="text-[12px] font-medium text-foreground">
                        Setores
                        <span v-if="form.role === 'attendant'" class="text-destructive">*</span>
                        <span v-else class="text-muted-foreground font-normal">(opcional)</span>
                    </label>
                    <div v-if="sectors.length === 0" class="text-[12px] text-muted-foreground">
                        Nenhum setor cadastrado.
                    </div>
                    <div v-else class="flex flex-wrap gap-1.5">
                        <button
                            v-for="s in sectors"
                            :key="s.id"
                            type="button"
                            @click="form.sector_ids.includes(s.id)
                                ? form.sector_ids = form.sector_ids.filter(id => id !== s.id)
                                : form.sector_ids.push(s.id)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[12px] transition-colors"
                            :class="form.sector_ids.includes(s.id)
                                ? 'border-primary bg-primary/10 text-foreground'
                                : 'border-border bg-card text-muted-foreground hover:bg-muted'">
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: s.color }"></span>
                            {{ s.name }}
                        </button>
                    </div>
                </div>
```

- [ ] **Step 4: Adicionar coluna "Setores" na tabela**

No `<thead>` (linhas 131-138), inserir entre a coluna "Papel" e "Em atendimento":

```html
<th class="px-6 py-3 text-left font-medium text-muted-foreground text-[11.5px] uppercase tracking-wider">Setores</th>
```

No `<tbody>` (linha 140+), inserir entre a célula do role (linha 159-161) e "Em atendimento" (linha 162):

```html
<td class="px-6 py-3.5">
    <div class="flex flex-wrap gap-1">
        <span
            v-for="s in (u.sectors || []).slice(0, 2)"
            :key="s.id"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-border text-[11px] text-foreground bg-card">
            <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: s.color }"></span>
            {{ s.name }}
        </span>
        <span v-if="(u.sectors || []).length > 2"
              class="inline-flex items-center px-2 py-0.5 rounded-full border border-border text-[11px] text-muted-foreground">
            +{{ u.sectors.length - 2 }}
        </span>
        <span v-if="!(u.sectors || []).length" class="text-[12px] text-muted-foreground">—</span>
    </div>
</td>
```

Ajustar o `colspan="6"` da linha 190 (empty state) para `colspan="7"`.

- [ ] **Step 5: Build do frontend**

Run: `cd app && npm run build`
Expected: build sem erros

- [ ] **Step 6: Commit**

```bash
git add app/resources/js/Pages/Users/Index.vue
git commit -m "feat: users page lets admin assign sectors to user"
```

---

### Task 6: Verificação final + push

- [ ] **Step 1: Rodar todos os testes**

Run: `cd app && ./vendor/bin/pest`
Expected: all green

- [ ] **Step 2: Verificar git status limpo**

Run: `git status`
Expected: working tree clean (com untracked files do início, mas nada modificado)

- [ ] **Step 3: Push para main**

Run: `git push origin main`
Expected: push aceito; EasyPanel detecta e auto-deploya em ~3min

- [ ] **Step 4: Aguardar deploy e confirmar**

Acessar https://medser.voraadigital.com/users após ~3-5min e:
- Abrir form de novo usuário → deve aparecer campo "Setores"
- Tentar salvar atendente sem setor → deve dar erro

---

## Self-Review

✅ Spec coverage: schema, backend (store/update/index), frontend (form+tabela), testes — todos cobertos
✅ Placeholders: nenhum
✅ Type consistency: `sector_ids` é array de inteiros em backend e frontend; relação `sectors` retorna `{id, name, color}` em todos os pontos
✅ Sem refactor não-relacionado
