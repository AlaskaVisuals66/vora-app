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
            'data' => WhatsappSession::query()->where('tenant_id', $request->user()->tenant_id)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'instance_name' => ['required','string','max:64','unique:whatsapp_sessions,instance_name'],
            'display_name'  => ['nullable','string','max:191'],
            'is_primary'    => ['boolean'],
        ]);

        $session = WhatsappSession::create([
            'tenant_id'     => $request->user()->tenant_id,
            'instance_name' => $data['instance_name'],
            'display_name'  => $data['display_name'] ?? $data['instance_name'],
            'state'         => 'qr_pending',
            'is_primary'    => (bool) ($data['is_primary'] ?? false),
            'webhook_events'=> ['MESSAGES_UPSERT','MESSAGES_UPDATE','CONNECTION_UPDATE','QRCODE_UPDATED'],
        ]);

        $this->evolution->createInstance($session->instance_name);
        $this->evolution->setWebhook(
            $session->instance_name,
            url('/api/v1/webhooks/evolution'),
            $session->webhook_events ?? []
        );

        return response()->json(['data' => $session], 201);
    }

    public function qr(WhatsappSession $session): JsonResponse
    {
        return response()->json([
            'state'   => $session->state,
            'qr_code' => $session->qr_code,
        ]);
    }

    public function reconnect(WhatsappSession $session): JsonResponse
    {
        $this->evolution->connect($session->instance_name);
        return response()->json(['ok' => true]);
    }

    public function destroy(WhatsappSession $session): JsonResponse
    {
        $this->evolution->deleteInstance($session->instance_name);
        $session->delete();
        return response()->json(['ok' => true]);
    }
}
