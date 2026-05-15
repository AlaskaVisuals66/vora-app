<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'tickets.view.all', 'tickets.view.own', 'tickets.send', 'tickets.transfer', 'tickets.close',
            'sectors.manage', 'users.manage', 'whatsapp.manage', 'analytics.view', 'settings.manage',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'api');
        }

        $admin = Role::findOrCreate('admin', 'api');
        $admin->syncPermissions(Permission::all());

        $supervisor = Role::findOrCreate('supervisor', 'api');
        $supervisor->syncPermissions([
            'tickets.view.all', 'tickets.send', 'tickets.transfer', 'tickets.close', 'analytics.view',
        ]);

        $attendant = Role::findOrCreate('attendant', 'api');
        $attendant->syncPermissions([
            'tickets.view.own', 'tickets.send', 'tickets.transfer', 'tickets.close',
        ]);

        $superadmin = Role::findOrCreate('superadmin', 'api');
        $superadmin->syncPermissions(Permission::all());
    }
}
