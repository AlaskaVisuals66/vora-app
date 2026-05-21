<?php

use App\Domain\Client\Models\Client;
use App\Domain\Ticket\Models\Ticket;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->tenant = createTenant();
});

function mkClient($tenant): Client
{
    return Client::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Client '.uniqid(),
        'phone'     => '+55119'.rand(10000000, 99999999),
    ]);
}

function mkTicket($tenant, $sector, $assignedTo = null, string $status = 'open'): Ticket
{
    return Ticket::create([
        'tenant_id'       => $tenant->id,
        'protocol'        => strtoupper(substr(md5(uniqid()), 0, 8)),
        'client_id'       => mkClient($tenant)->id,
        'sector_id'       => $sector->id,
        'assigned_to'     => $assignedTo,
        'status'          => $status,
        'last_message_at' => now(),
    ]);
}

it('attendant Default view (no sector filter) shows only tickets from their sectors or assigned to them', function () {
    $comercial   = createSector($this->tenant, 'Comercial');
    $financeiro  = createSector($this->tenant, 'Financeiro');
    $manutencao  = createSector($this->tenant, 'Manutencao');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($comercial->id);

    $t1 = mkTicket($this->tenant, $comercial);
    $t2 = mkTicket($this->tenant, $financeiro);
    $t3 = mkTicket($this->tenant, $manutencao);

    $response = $this->withToken(jwtToken($bruno))->getJson('/api/v1/tickets');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($t1->id)
                ->not->toContain($t2->id)
                ->not->toContain($t3->id);
});

it('attendant does NOT see tickets assigned to them in sectors they were removed from', function () {
    $comercial   = createSector($this->tenant, 'Comercial');
    $financeiro  = createSector($this->tenant, 'Financeiro');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($comercial->id);

    // ticket previously assigned to Bruno but in a sector he no longer belongs to
    $orphan = mkTicket($this->tenant, $financeiro, $bruno->id);

    $response = $this->withToken(jwtToken($bruno))->getJson('/api/v1/tickets');

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($orphan->id);
});

it('attendant cannot open a ticket assigned to them outside their sectors', function () {
    $comercial  = createSector($this->tenant, 'Comercial');
    $financeiro = createSector($this->tenant, 'Financeiro');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($comercial->id);

    $orphan = mkTicket($this->tenant, $financeiro, $bruno->id);

    $this->withToken(jwtToken($bruno))
        ->getJson("/api/v1/tickets/{$orphan->id}")
        ->assertStatus(403);

    $this->withToken(jwtToken($bruno))
        ->getJson("/api/v1/tickets/{$orphan->id}/messages")
        ->assertStatus(403);
});

it('attendant can open tickets in their sector', function () {
    $comercial = createSector($this->tenant, 'Comercial');

    $bruno = createUser($this->tenant, 'attendant');
    $bruno->sectors()->attach($comercial->id);

    $ticket = mkTicket($this->tenant, $comercial);

    $this->withToken(jwtToken($bruno))
        ->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertStatus(200);
});
