<?php

use App\Domain\Ticket\Models\WhatsappSession;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin  = createUser($this->tenant, 'admin');

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
    ]);
});

it('admin can create a session', function () {
    $response = $this->withToken(jwtToken($this->admin))
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

    $this->withToken(jwtToken($this->admin))
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

    $this->withToken(jwtToken($this->admin))
        ->postJson('/api/v1/whatsapp/sessions', ['instance_name' => 'overflow-'.uniqid()])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Limite'));
});

it('attendant can create a non-primary session', function () {
    $attendant = createUser($this->tenant, 'attendant');

    $this->withToken(jwtToken($attendant))
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

    $this->withToken(jwtToken($attendant))
        ->deleteJson("/api/v1/whatsapp/sessions/{$session->id}")
        ->assertStatus(403);
});
