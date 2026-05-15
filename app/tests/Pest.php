<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

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
    \Spatie\Permission\Models\Role::findOrCreate($role, 'api');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $user = \App\Domain\Auth\Models\User::create(array_merge([
        'tenant_id' => $tenant->id,
        'name'      => 'User '.uniqid(),
        'email'     => 'user'.uniqid().'@test.com',
        'password'  => 'password',
        'is_active' => true,
    ], $attrs));
    $user->assignRole($role);
    return $user;
}

// Helper: generate a JWT token for a user
function jwtToken(\App\Domain\Auth\Models\User $user): string
{
    return \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
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
