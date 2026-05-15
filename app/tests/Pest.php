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
