<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('superadmin', 'api');
    }

    public function down(): void
    {
        Role::where('name', 'superadmin')->where('guard_name', 'api')->delete();
    }
};
