<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ticket\Models\WhatsappSession;
use App\Http\Controllers\Controller;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $tenantId = $request->user()->tenant_id;
        $tenant   = $request->user()->tenant;

        $maxSessions = (int) ($tenant->settings['max_sessions'] ?? 3);
        $current     = WhatsappSession::where('tenant_id', $tenantId)->count();

        if ($current >= $maxSessions) {
            return response()->json(['message' => "Limite de {$maxSessions} sessões atingido."], 422);
        }

        $data = $request->validate([
            'instance_name' => ['required','string','max:64','unique:whatsapp_sessions,instance_name'],
            'display_name'  => ['nullable','string','max:191'],
            'is_primary'    => ['boolean'],
        ]);

        $wantsPrimary = (bool) ($data['is_primary'] ?? false);

        if ($wantsPrimary && ! $request->user()->isAdmin()) {
            $wantsPrimary = false;
        }

        $session = DB::transaction(function () use ($data, $tenantId, $wantsPrimary) {
            if ($wantsPrimary) {
                WhatsappSession::where('tenant_id', $tenantId)->update(['is_primary' => false]);
            }

            return WhatsappSession::create([
                'tenant_id'      => $tenantId,
                'instance_name'  => $data['instance_name'],
                'display_name'   => $data['display_name'] ?? $data['instance_name'],
                'state'          => 'qr_pending',
                'is_primary'     => $wantsPrimary,
                'webhook_events' => ['MESSAGES_UPSERT','MESSAGES_UPDATE','CONNECTION_UPDATE','QRCODE_UPDATED'],
            ]);
        });

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
        abort_unless($session->tenant_id === request()->user()->tenant_id, 404);
        abort_unless(request()->user()->isAdmin(), 403);

        $this->evolution->deleteInstance($session->instance_name);
        $session->delete();
        return response()->json(['ok' => true]);
    }
}
