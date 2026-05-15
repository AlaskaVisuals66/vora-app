<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsappEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function evolution(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::channel('webhooks')->info('evolution.webhook', ['event' => $payload['event'] ?? null]);

        $event    = (string) ($payload['event'] ?? '');
        $instance = (string) ($payload['instance'] ?? '');

        // Connection / QR events update the session row inline; messages go to a queue
        if ($event === 'connection.update' && $instance) {
            $state = strtolower((string) ($payload['data']['state'] ?? 'disconnected'));
            $map = [
                'open'    => 'connected',
                'close'   => 'disconnected',
                'connecting' => 'connecting',
            ];
            $session = WhatsappSession::where('instance_name', $instance)->first();
            if (! $session) {
                Log::channel('webhooks')->warning('evolution.unknown_instance', ['instance' => $instance]);
                return response()->json(['ok' => true]);
            }

            $mappedState = $map[$state] ?? null;
            if (! $mappedState) {
                return response()->json(['ok' => true]);
            }

            $session->forceFill([
                'state'         => $mappedState,
                'connected_at'  => $mappedState === 'connected' ? ($session->connected_at ?: now()) : null,
                'last_event_at' => now(),
                'qr_code'       => $mappedState === 'connected' ? null : $session->qr_code,
            ])->save();

            return response()->json(['ok' => true]);
        }

        if ($event === 'qrcode.updated' && $instance) {
            $session = WhatsappSession::where('instance_name', $instance)->first();
            if (! $session) {
                Log::channel('webhooks')->warning('evolution.unknown_qr_instance', ['instance' => $instance]);
                return response()->json(['ok' => true]);
            }

            $session->forceFill([
                'state'         => 'qr_pending',
                'qr_code'       => $payload['data']['qrcode']['base64']
                    ?? $payload['data']['qrcode']
                    ?? $payload['data']['base64']
                    ?? $payload['data']['code']
                    ?? null,
                'last_event_at' => now(),
            ])->save();

            return response()->json(['ok' => true]);
        }

        // Messages → async pipeline
        ProcessIncomingWhatsappEvent::dispatch($payload)->onQueue('webhooks');
        return response()->json(['ok' => true], 202);
    }

    public function n8n(Request $request): JsonResponse
    {
        // Inbound webhook FROM n8n (e.g., automation results) – validated by shared secret
        $secret = $request->header('X-Helpdesk-Secret');
        if (! hash_equals((string) config('services.n8n.api_token'), (string) $secret)) {
            return response()->json(['message' => 'unauthorized'], 401);
        }
        Log::channel('webhooks')->info('n8n.webhook', $request->all());
        return response()->json(['ok' => true]);
    }
}
