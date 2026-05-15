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

    $response = $this->withToken(jwtToken($admin))
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
    $other = createUser($this->tenant, 'attendant');

    $me->sectors()->attach($sector1->id);
    $peer->sectors()->attach($sector1->id);
    $other->sectors()->attach($sector2->id);

    $response = $this->withToken(jwtToken($me))
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

    $response = $this->withToken(jwtToken($spy))
        ->getJson('/api/v1/users');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toBeEmpty();
});
