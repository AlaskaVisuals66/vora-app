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
