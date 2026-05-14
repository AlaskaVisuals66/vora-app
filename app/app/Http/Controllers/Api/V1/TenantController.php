<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'data' => [
                'tenant' => [
                    'name'     => $tenant->name,
                    'document' => $tenant->document,
                    'plan'     => $tenant->plan,
                    'timezone' => $tenant->settings['timezone'] ?? config('app.timezone'),
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
            'timezone' => ['nullable','string','max:64'],
        ]);

        $tenant->name     = $data['name'];
        $tenant->document = $data['document'] ?? null;

        $settings = $tenant->settings ?? [];
        if (! empty($data['timezone'])) {
            $settings['timezone'] = $data['timezone'];
        }
        $tenant->settings = $settings;
        $tenant->save();

        return $this->show($request);
    }
}
