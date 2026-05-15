<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappSessionController extends Controller
{
    public function __construct(private readonly EvolutionApiClient $evolution) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => WhatsappSession::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('is_primary')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $tenant   = $request->user()->tenant;
        app()->instance('tenant.id', $tenantId);

        $maxSessions = 1;
        $current     = WhatsappSession::where('tenant_id', $tenantId)->count();
        $evolutionConfig = $this->evolutionConfig($tenant->settings ?? []);
        $webhookEvents = $this->webhookEvents($evolutionConfig['webhook_events'] ?? null);

        if ($current >= $maxSessions) {
            return response()->json(['message' => "Limite de {$maxSessions} sessões atingido."], 422);
        }

        $data = $request->validate([
            'instance_name' => ['required','string','max:64','unique:whatsapp_sessions,instance_name'],
            'display_name'  => ['nullable','string','max:191'],
            'is_primary'    => ['boolean'],
        ]);

        $session = WhatsappSession::create([
            'tenant_id'      => $tenantId,
            'instance_name'  => $data['instance_name'],
            'display_name'   => $data['display_name'] ?? $data['instance_name'],
            'state'          => 'qr_pending',
            'is_primary'     => true,
            'webhook_events' => $webhookEvents,
        ]);

        $createResponse = $this->evolution->createInstance($session->instance_name);
        if ($qrCode = $this->extractQrCode($createResponse)) {
            $session->forceFill(['qr_code' => $qrCode, 'state' => 'qr_pending'])->save();
        }

        $this->evolution->setWebhook(
            $session->instance_name,
            $evolutionConfig['webhook_url'] ?? url('/api/v1/webhooks/evolution'),
            $session->webhook_events ?? []
        );

        return response()->json(['data' => $session->fresh()], 201);
    }

    public function qr(WhatsappSession $session): JsonResponse
    {
        abort_unless($session->tenant_id === request()->user()->tenant_id, 404);
        app()->instance('tenant.id', $session->tenant_id);

        // Only call Evolution if we have no QR yet and are not connected.
        if ($session->state !== 'connected' && blank($session->qr_code)) {
            try {
                $response = $this->evolution->connect($session->instance_name);
                if ($qrCode = $this->extractQrCode($response)) {
                    $session->forceFill(['qr_code' => $qrCode, 'state' => 'qr_pending'])->save();
                }
            } catch (\Throwable) {}
        }

        // Only call Evolution status API if no webhook event arrived in the last 8 seconds.
        // Webhooks handle state in real-time; polling is just a fallback.
        $staleSince = $session->last_event_at ?? $session->updated_at;
        if (! $staleSince || now()->diffInSeconds($staleSince) > 8) {
            $this->syncStatus($session);
        }

        $session->refresh();

        return response()->json([
            'data' => [
                'id' => $session->id,
                'state' => $session->state,
                'qr_code' => $session->qr_code,
                'connected_at' => $session->connected_at,
            ],
        ]);
    }

    public function reconnect(WhatsappSession $session): JsonResponse
    {
        abort_unless($session->tenant_id === request()->user()->tenant_id, 404);
        app()->instance('tenant.id', $session->tenant_id);

        $tenant = $session->tenant;
        $evolutionConfig = $this->evolutionConfig($tenant->settings ?? []);
        $response = null;

        try {
            $response = $this->evolution->connect($session->instance_name);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'instance does not exist')) {
                throw $e;
            }

            $response = $this->evolution->createInstance($session->instance_name);
            $this->evolution->setWebhook(
                $session->instance_name,
                $evolutionConfig['webhook_url'] ?? url('/api/v1/webhooks/evolution'),
                $session->webhook_events ?? $this->webhookEvents($evolutionConfig['webhook_events'] ?? null)
            );
        }

        if ($qrCode = $this->extractQrCode($response)) {
            $session->forceFill(['qr_code' => $qrCode, 'state' => 'qr_pending'])->save();
        }

        // Always pull current state from Evolution so the UI reflects reality
        $this->syncStatus($session);

        return response()->json(['data' => $session->fresh()]);
    }

    public function destroy(WhatsappSession $session): JsonResponse
    {
        abort_unless($session->tenant_id === request()->user()->tenant_id, 404);
        abort_unless(request()->user()->isAdmin(), 403);
        app()->instance('tenant.id', $session->tenant_id);

        $this->evolution->deleteInstance($session->instance_name);
        $session->delete();
        return response()->json(['ok' => true]);
    }

    private function evolutionConfig(array $settings): array
    {
        $gateway = is_array($settings['gateway'] ?? null) ? $settings['gateway'] : [];
        if (($gateway['type'] ?? 'evolution') !== 'evolution') {
            return [];
        }

        return is_array($gateway['config'] ?? null) ? $gateway['config'] : [];
    }

    private function webhookEvents(?string $events): array
    {
        $events = $events ?: 'MESSAGES_UPSERT, MESSAGES_UPDATE, CONNECTION_UPDATE, QRCODE_UPDATED';

        return array_values(array_filter(array_map(
            fn ($event) => trim($event),
            preg_split('/[\r\n,]+/', $events) ?: []
        )));
    }

    private function extractQrCode(array $payload): ?string
    {
        $direct = $payload['qrcode']['base64']
            ?? $payload['qrcode']['code']
            ?? $payload['qrcode']
            ?? $payload['base64']
            ?? $payload['code']
            ?? null;

        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        foreach ($payload as $value) {
            if (is_array($value) && $qrCode = $this->extractQrCode($value)) {
                return $qrCode;
            }
        }

        return null;
    }

    private function syncStatus(WhatsappSession $session): void
    {
        try {
            $response = $this->evolution->status($session->instance_name);
        } catch (\Throwable) {
            return;
        }

        $state = $this->extractState($response);
        if (! $state) {
            return;
        }

        $map = [
            'open' => 'connected',
            'connected' => 'connected',
            'connecting' => 'connecting',
            'close' => 'disconnected',
            'disconnected' => 'disconnected',
        ];

        $nextState = $map[strtolower($state)] ?? null;
        if (! $nextState) {
            return;
        }

        $session->forceFill([
            'state' => $nextState,
            'connected_at' => $nextState === 'connected' ? ($session->connected_at ?: now()) : null,
            'qr_code' => $nextState === 'connected' ? null : $session->qr_code,
            'last_event_at' => now(),
        ])->save();
    }

    private function extractState(array $payload): ?string
    {
        $direct = $payload['state']
            ?? $payload['instance']['state']
            ?? $payload['instance']['connectionStatus']
            ?? null;

        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        foreach ($payload as $value) {
            if (is_array($value) && $state = $this->extractState($value)) {
                return $state;
            }
        }

        return null;
    }
}
