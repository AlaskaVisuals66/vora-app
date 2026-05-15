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
                        'url'         => (string) config('services.evolution.url'),
                        'api_key_set' => filled(config('services.evolution.api_key')),
                        'webhook_url' => url('/api/v1/webhooks/evolution'),
                    ],
                    'gateway' => $settings['gateway'] ?? ['type' => 'evolution', 'config' => []],
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

    public function updateGateway(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'type'                 => ['required', Rule::in(['evolution', 'webhook'])],
            'config'               => ['nullable', 'array'],
            'config.url'           => ['nullable', 'string', 'max:255'],
            'config.secret_header' => ['nullable', 'string', 'max:64'],
            'config.secret_value'  => ['nullable', 'string', 'max:255'],
            'config.event_mapping' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings            = $tenant->settings ?? [];
        $settings['gateway'] = $data;
        $tenant->settings    = $settings;
        $tenant->save();

        return $this->show($request);
    }
}
