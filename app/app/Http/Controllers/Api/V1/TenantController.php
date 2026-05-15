<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant   = $request->user()->tenant;
        $settings = $tenant->settings ?? [];
        $gateway  = $this->gatewayShape($settings['gateway'] ?? null);
        $evolutionConfig = $gateway['type'] === 'evolution' ? $gateway['config'] : [];
        $evolutionApiKey = $evolutionConfig['api_key'] ?? config('services.evolution.api_key');

        return response()->json([
            'data' => [
                'tenant' => [
                    'name'      => $tenant->name,
                    'document'  => $tenant->document,
                    'plan'      => $tenant->plan,
                    'whatsapp'  => $settings['whatsapp'] ?? null,
                    'email'     => $settings['email'] ?? null,
                    'address'   => $this->addressShape($settings['address'] ?? null),
                    'timezone'  => $settings['timezone'] ?? 'America/Cuiaba',
                    'logo_url'  => isset($settings['logo']) ? Storage::url($settings['logo']) : null,
                ],
                'integrations' => [
                    'evolution' => [
                        'url'         => $evolutionConfig['base_url'] ?? (string) config('services.evolution.url'),
                        'api_key_set' => filled($evolutionApiKey),
                        'webhook_url' => $evolutionConfig['webhook_url'] ?? url('/api/v1/webhooks/evolution'),
                    ],
                    'gateway' => $this->publicGatewayShape($gateway),
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'name'               => ['required','string','max:160'],
            'document'           => ['nullable','string','max:32'],
            'whatsapp'           => ['nullable','string','max:32'],
            'email'              => ['nullable','email','max:160'],
            'address'            => ['nullable','array'],
            'address.zip'        => ['nullable','string','max:16'],
            'address.street'     => ['nullable','string','max:160'],
            'address.number'     => ['nullable','string','max:16'],
            'address.complement' => ['nullable','string','max:80'],
            'address.district'   => ['nullable','string','max:80'],
            'address.city'       => ['nullable','string','max:80'],
            'address.state'      => ['nullable','string','max:2'],
        ]);

        $tenant->name     = $data['name'];
        $tenant->document = $data['document'] ?? null;

        $settings = $tenant->settings ?? [];
        $settings['whatsapp'] = isset($data['whatsapp']) ? preg_replace('/\D/', '', $data['whatsapp']) : null;
        $settings['email']    = $data['email'] ?? null;
        $settings['address']  = $this->addressShape($data['address'] ?? null);
        $settings['timezone'] = $settings['timezone'] ?? 'America/Cuiaba';
        $tenant->settings = $settings;
        $tenant->save();

        return $this->show($request);
    }

    private function addressShape($address): array
    {
        $address = is_array($address) ? $address : [];
        $keys    = ['zip','street','number','complement','district','city','state'];

        return array_combine(
            $keys,
            array_map(fn ($k) => $address[$k] ?? null, $keys)
        );
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required','image','mimes:jpeg,png,jpg,webp','max:20480'],
        ]);

        $tenant   = $request->user()->tenant;
        $settings = $tenant->settings ?? [];

        if (! empty($settings['logo'])) {
            Storage::disk('public')->delete($settings['logo']);
        }

        $path = $request->file('logo')->store("tenant-logos/{$tenant->id}", 'public');

        $settings['logo'] = $path;
        $tenant->settings = $settings;
        $tenant->save();

        return $this->show($request);
    }

    public function getBotConfig(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $settings = $tenant->settings ?? [];
        return response()->json(['data' => $this->botConfigShape($settings['bot'] ?? null)]);
    }

    public function updateBot(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'enabled'          => ['boolean'],
            'menu_message'     => ['nullable', 'string', 'max:2000'],
            'confirm_message'  => ['nullable', 'string', 'max:1000'],
            'invalid_message'  => ['nullable', 'string', 'max:500'],
            'delay_seconds'    => ['nullable', 'integer', 'min:0', 'max:10'],
            'sectors'          => ['nullable', 'array', 'max:10'],
            'sectors.*.key'    => ['required_with:sectors', 'string', 'max:8'],
            'sectors.*.label'  => ['required_with:sectors', 'string', 'max:64'],
            'sectors.*.emoji'  => ['nullable', 'string', 'max:8'],
            'sectors.*.state'  => ['required_with:sectors', 'string', 'max:32'],
        ]);

        $settings = $tenant->settings ?? [];
        $current  = $this->botConfigShape($settings['bot'] ?? null);

        $settings['bot'] = array_merge($current, array_filter($data, fn ($v) => ! is_null($v)));
        $tenant->settings = $settings;
        $tenant->save();

        return response()->json(['data' => $this->botConfigShape($settings['bot'])]);
    }

    private function botConfigShape(?array $bot): array
    {
        $defaults = [
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

        return array_merge($defaults, is_array($bot) ? $bot : []);
    }

    public function updateGateway(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'type'                 => ['required', Rule::in(['evolution', 'webhook'])],
            'config'               => ['nullable', 'array'],
            'config.base_url'      => ['nullable', 'url', 'max:255'],
            'config.api_key'       => ['nullable', 'string', 'max:255'],
            'config.webhook_url'   => ['nullable', 'url', 'max:255'],
            'config.webhook_events'=> ['nullable', 'string', 'max:2000'],
            'config.url'           => ['nullable', 'string', 'max:255'],
            'config.secret_header' => ['nullable', 'string', 'max:64'],
            'config.secret_value'  => ['nullable', 'string', 'max:255'],
            'config.event_mapping' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings            = $tenant->settings ?? [];
        $currentGateway      = $this->gatewayShape($settings['gateway'] ?? null);
        $nextGateway         = $this->gatewayShape($data);

        if (
            $nextGateway['type'] === 'evolution'
            && blank($nextGateway['config']['api_key'] ?? null)
            && filled($currentGateway['config']['api_key'] ?? null)
        ) {
            $nextGateway['config']['api_key'] = $currentGateway['config']['api_key'];
        }

        $settings['gateway'] = $nextGateway;
        $tenant->settings    = $settings;
        $tenant->save();

        return $this->show($request);
    }

    private function gatewayShape($gateway): array
    {
        $gateway = is_array($gateway) ? $gateway : [];
        $type = in_array($gateway['type'] ?? null, ['evolution', 'webhook'], true) ? $gateway['type'] : 'evolution';
        $config = is_array($gateway['config'] ?? null) ? $gateway['config'] : [];

        if ($type === 'evolution') {
            return [
                'type' => 'evolution',
                'config' => [
                    'base_url' => filled($config['base_url'] ?? null) ? $config['base_url'] : (string) config('services.evolution.url'),
                    'api_key' => $config['api_key'] ?? null,
                    'webhook_url' => filled($config['webhook_url'] ?? null) ? $config['webhook_url'] : url('/api/v1/webhooks/evolution'),
                    'webhook_events' => filled($config['webhook_events'] ?? null) ? $config['webhook_events'] : 'MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE, QRCODE_UPDATED',
                ],
            ];
        }

        return [
            'type' => 'webhook',
            'config' => [
                'url' => $config['url'] ?? '',
                'secret_header' => $config['secret_header'] ?? '',
                'secret_value' => $config['secret_value'] ?? '',
                'event_mapping' => $config['event_mapping'] ?? '',
            ],
        ];
    }

    private function publicGatewayShape(array $gateway): array
    {
        if ($gateway['type'] === 'evolution') {
            unset($gateway['config']['api_key']);
        }

        return $gateway;
    }
}
