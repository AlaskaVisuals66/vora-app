<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Models\Tenant;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instanceName = $request->query('instance');

        if ($instanceName) {
            $session = WhatsappSession::where('instance_name', $instanceName)->first();
            $tenant  = $session?->tenant ?? Tenant::first();
        } else {
            $tenant = Tenant::first();
        }

        if (! $tenant) {
            return response()->json(['data' => $this->defaults()]);
        }

        $settings = $tenant->settings ?? [];
        $bot = array_merge($this->defaults(), is_array($settings['bot'] ?? null) ? $settings['bot'] : []);

        return response()->json(['data' => $bot]);
    }

    private function defaults(): array
    {
        return [
            'enabled'         => false,
            'menu_message'    => "Olá, {name}! 👋 Bem-vindo(a) ao atendimento.\n\nEscolha um setor:\n\n{sectors}\n\nDigite o número.",
            'confirm_message' => "✅ Seu atendimento foi direcionado!\n\nAguarde, em breve um atendente entrará em contato.\n\nSe quiser, descreva sua dúvida enquanto aguarda.",
            'invalid_message' => "❌ Opção inválida. Digite o número:\n\n{sectors}",
            'delay_seconds'   => 1,
            'sectors'         => [
                ['key' => '1', 'label' => 'Financeiro',     'emoji' => '💰', 'state' => 'financial'],
                ['key' => '2', 'label' => 'Suporte Técnico','emoji' => '🔧', 'state' => 'support'],
                ['key' => '3', 'label' => 'Vendas',         'emoji' => '🛒', 'state' => 'sales'],
                ['key' => '4', 'label' => 'Agendamento',    'emoji' => '📅', 'state' => 'scheduling'],
            ],
        ];
    }
}
