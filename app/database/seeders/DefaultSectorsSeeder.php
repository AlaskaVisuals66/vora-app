<?php

namespace Database\Seeders;

use App\Domain\Sector\Models\Sector;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class DefaultSectorsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'default')->firstOrFail();

        $comercial = Sector::updateOrCreate(
            ['tenant_id' => $tenant->id, 'menu_key' => '1', 'parent_id' => null],
            ['name' => 'Comercial', 'slug' => 'comercial', 'color' => '#6366f1', 'icon' => 'briefcase', 'active' => true],
        );

        $financeiro = Sector::updateOrCreate(
            ['tenant_id' => $tenant->id, 'menu_key' => '2', 'parent_id' => null],
            ['name' => 'Financeiro', 'slug' => 'financeiro', 'color' => '#10b981', 'icon' => 'currency-dollar', 'active' => true],
        );

        $manutencao = Sector::updateOrCreate(
            ['tenant_id' => $tenant->id, 'menu_key' => '3', 'parent_id' => null],
            [
                'name' => 'Manutenção',
                'slug' => 'manutencao',
                'color' => '#f59e0b',
                'icon' => 'wrench',
                'active' => true,
                'settings' => [
                    'submenu_greeting' => "Manutenção — escolha:\n\n1️⃣ Assessoria Técnica\n2️⃣ Assessoria Científica",
                ],
            ],
        );

        Sector::updateOrCreate(
            ['tenant_id' => $tenant->id, 'parent_id' => $manutencao->id, 'menu_key' => '1'],
            ['name' => 'Assessoria Técnica', 'slug' => 'manutencao-tecnica', 'color' => '#f97316', 'active' => true],
        );

        Sector::updateOrCreate(
            ['tenant_id' => $tenant->id, 'parent_id' => $manutencao->id, 'menu_key' => '2'],
            ['name' => 'Assessoria Científica', 'slug' => 'manutencao-cientifica', 'color' => '#ef4444', 'active' => true],
        );
    }
}
