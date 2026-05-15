<?php

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
    $this->admin  = createUser($this->tenant, 'admin');
});

it('admin can save webhook gateway settings', function () {
    $response = $this->withToken(jwtToken($this->admin))
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
    $this->withToken(jwtToken($this->admin))
        ->putJson('/api/v1/tenant/gateway', ['type' => 'evolution', 'config' => []])
        ->assertStatus(200)
        ->assertJsonPath('data.integrations.gateway.type', 'evolution');
});

it('gateway is returned in tenant show response', function () {
    $settings = $this->tenant->settings ?? [];
    $settings['gateway'] = ['type' => 'webhook', 'config' => ['url' => 'https://wh.example.com']];
    $this->tenant->settings = $settings;
    $this->tenant->save();

    $this->withToken(jwtToken($this->admin))
        ->getJson('/api/v1/tenant')
        ->assertStatus(200)
        ->assertJsonPath('data.integrations.gateway.type', 'webhook');
});
