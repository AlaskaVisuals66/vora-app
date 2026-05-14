<?php

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Sector\Models\Sector;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'default')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $admin = User::updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'admin@helpdesk.local'],
            ['name' => 'Administrador', 'password' => Hash::make('password'), 'status' => 'online'],
        );
        $admin->syncRoles(['admin']);

        $supervisor = User::updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'supervisor@helpdesk.local'],
            ['name' => 'Supervisor', 'password' => Hash::make('password'), 'status' => 'online'],
        );
        $supervisor->syncRoles(['supervisor']);

        $sectorIds = Sector::where('tenant_id', $tenant->id)->whereNotNull('menu_key')->pluck('id')->all();

        foreach (['Ana', 'Bruno', 'Carla'] as $i => $name) {
            $u = User::updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => strtolower($name) . '@helpdesk.local'],
                ['name' => "Atendente {$name}", 'password' => Hash::make('password'), 'status' => 'offline'],
            );
            $u->syncRoles(['attendant']);
            if (method_exists($u, 'sectors')) {
                $u->sectors()->syncWithoutDetaching($sectorIds);
            }
        }
    }
}
