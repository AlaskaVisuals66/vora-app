<?php

namespace App\Http\Controllers\Api\V1\WebChat;

use App\Domain\Channel\Models\Channel;
use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Services\ConversationOrchestrator;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WebChatController extends Controller
{
    public function __construct(
        private readonly ConversationOrchestrator $orchestrator,
    ) {}

    public function config(Request $request): JsonResponse
    {
        $slug = $request->query('tenant', 'default');
        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant || !$tenant->active) {
            return response()->json(['message' => 'Tenant não encontrado'], 404);
        }

        $channel = Channel::where('tenant_id', $tenant->id)
            ->where('type', 'web_chat')
            ->where('is_active', true)
            ->first();

        $settings = $tenant->settings ?? [];

        return response()->json([
            'tenant_id' => $tenant->id,
            'name' => $settings['webchat_name'] ?? $tenant->name,
            'greeting' => $settings['webchat_greeting'] ?? 'Olá! Como podemos ajudar?',
            'primary_color' => $settings['webchat_color'] ?? '#6366f1',
            'enabled' => $channel !== null,
            'widget_position' => $settings['webchat_position'] ?? 'right',
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'phone'     => ['required', 'string', 'max:32'],
            'name'      => ['nullable', 'string', 'max:191'],
            'message'   => ['required', 'string', 'max:5000'],
        ]);

        $key = 'webchat:' . sha1($data['phone'] . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['message' => 'Muitas mensagens. Aguarde um instante.'], 429);
        }
        RateLimiter::hit($key, 60);

        $this->orchestrator->handleWebChatInbound(
            tenantId: $data['tenant_id'],
            phone: $data['phone'],
            name: $data['name'] ?? 'Visitante',
            text: $data['message'],
        );

        return response()->json(['ok' => true]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'phone'     => ['required', 'string', 'max:32'],
        ]);

        $client = Client::where('tenant_id', $data['tenant_id'])
            ->where('phone', $data['phone'])
            ->first();

        if (!$client) {
            return response()->json(['data' => []]);
        }

        $ticket = $client->activeTicket();
        if (!$ticket) {
            $ticket = $client->tickets()->latest('id')->first();
        }
        if (!$ticket) {
            return response()->json(['data' => []]);
        }

        $messages = Message::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        $canSend = in_array($ticket->status, ['menu', 'queued', 'open', 'pending']);

        return response()->json([
            'data' => MessageResource::collection($messages),
            'ticket_status' => $ticket->status,
            'can_send' => $canSend,
            'ticket_id' => $ticket->id,
        ]);
    }
}
