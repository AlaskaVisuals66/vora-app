<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                    'address'   => $settings['address'] ?? null,
                    'timezone'  => $settings['timezone'] ?? config('app.timezone'),
                    'logo_url'  => isset($settings['logo']) ? Storage::url($settings['logo']) : null,
                ],
                'integrations' => [
                    'evolution' => [
                        'url'         => (string) config('services.evolution.url'),
                        'api_key_set' => filled(config('services.evolution.api_key')),
                        'webhook_url' => url('/api/v1/webhooks/evolution'),
                    ],
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'name'     => ['required','string','max:160'],
            'document' => ['nullable','string','max:32'],
            'whatsapp' => ['nullable','string','max:32'],
            'email'    => ['nullable','email','max:160'],
            'address'  => ['nullable','string','max:255'],
            'timezone' => ['nullable','string','max:64'],
        ]);

        $tenant->name     = $data['name'];
        $tenant->document = $data['document'] ?? null;

        $settings = $tenant->settings ?? [];
        foreach (['whatsapp','email','address','timezone'] as $key) {
            $settings[$key] = $data[$key] ?? null;
        }
        $tenant->settings = $settings;
        $tenant->save();

        return $this->show($request);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required','image','mimes:jpeg,png,jpg,webp','max:2048'],
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
}
