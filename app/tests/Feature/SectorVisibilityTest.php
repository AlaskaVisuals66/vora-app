<?php

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
});

it('admin sees all tenant sectors', function () {
    $admin = createUser($this->tenant, 'admin');
    $comercial   = createSector($this->tenant, 'Comercial');
    $financeiro  = createSector($this->tenant, 'Financeiro');
    $manutencao  = createSector($this->tenant, 'Manutencao');

    $response = $this->withToken(jwtToken($admin))->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($comercial->id)
                ->toContain($financeiro->id)
                ->toContain($manutencao->id);
});

it('attendant sees only sectors they are bound to via attendant_sectors', function () {
    $comercial   = createSector($this->tenant, 'Comercial');
    $financeiro  = createSector($this->tenant, 'Financeiro');
    $manutencao  = createSector($this->tenant, 'Manutencao');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($comercial->id);

    $response = $this->withToken(jwtToken($bruno))->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($comercial->id)
                ->not->toContain($financeiro->id)
                ->not->toContain($manutencao->id);
});

it('attendant bound to a child sector sees parent with only that child', function () {
    $comercial = createSector($this->tenant, 'Comercial');
    $vendas    = \App\Domain\Sector\Models\Sector::create([
        'tenant_id' => $this->tenant->id,
        'parent_id' => $comercial->id,
        'name'      => 'Vendas',
        'slug'      => 'vendas-'.uniqid(),
        'color'     => '#737373',
        'active'    => true,
        'order'     => 0,
    ]);
    $prevenda  = \App\Domain\Sector\Models\Sector::create([
        'tenant_id' => $this->tenant->id,
        'parent_id' => $comercial->id,
        'name'      => 'Pre-venda',
        'slug'      => 'prevenda-'.uniqid(),
        'color'     => '#737373',
        'active'    => true,
        'order'     => 1,
    ]);
    $financeiro = createSector($this->tenant, 'Financeiro');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($vendas->id);

    $response = $this->withToken(jwtToken($bruno))->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $rootIds = collect($response->json('data'))->pluck('id');
    expect($rootIds)->toContain($comercial->id)
                    ->not->toContain($financeiro->id);

    $comercialNode = collect($response->json('data'))->firstWhere('id', $comercial->id);
    $childIds      = collect($comercialNode['children'])->pluck('id');
    expect($childIds)->toContain($vendas->id)
                     ->not->toContain($prevenda->id);
});

it('supervisor sees only sectors they are bound to', function () {
    $comercial  = createSector($this->tenant, 'Comercial');
    $financeiro = createSector($this->tenant, 'Financeiro');

    $sup = createUser($this->tenant, 'supervisor');
    $sup->sectors()->attach($comercial->id);

    $response = $this->withToken(jwtToken($sup))->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($comercial->id)
                ->not->toContain($financeiro->id);
});
