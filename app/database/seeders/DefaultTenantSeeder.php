<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class DefaultTenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['slug' => 'default'],
            [
                'name'     => 'Empresa Demo',
                'document' => null,
                'plan'     => 'pro',
                'settings' => [
                    'menu_greeting' => "Olá! 👋 Bem-vindo. Escolha uma opção:\n\n1️⃣ Comercial\n2️⃣ Financeiro\n3️⃣ Manutenção",
                    'business_hours' => [
                        'mon-fri' => ['08:00', '18:00'],
                        'sat'     => ['09:00', '13:00'],
                    ],
                ],
                'active' => true,
            ],
        );
    }
}
