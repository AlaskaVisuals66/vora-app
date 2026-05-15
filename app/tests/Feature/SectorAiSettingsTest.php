<?php
// tests/Feature/SectorAiSettingsTest.php

use App\Domain\Sector\Models\Sector;

beforeEach(function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    $this->tenant = createTenant();
    $this->admin = createUser($this->tenant, 'admin');
    $this->sector = createSector($this->tenant);
    $this->adminToken = jwtToken($this->admin);
});

it('admin can update sector ai_settings', function () {
    $response = $this->withToken($this->adminToken)
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
    $attendantToken = jwtToken($attendant);

    $this->withToken($attendantToken)
        ->putJson("/api/v1/sectors/{$this->sector->id}/ai-settings", ['ai_enabled' => true])
        ->assertStatus(403);
});

it('sector index includes ai_settings', function () {
    $this->sector->update(['ai_settings' => ['ai_enabled' => true, 'ai_prompt' => 'test']]);

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/sectors');

    $response->assertStatus(200);
    $first = $response->json('data.0');
    $this->assertArrayHasKey('ai_settings', $first);
});
