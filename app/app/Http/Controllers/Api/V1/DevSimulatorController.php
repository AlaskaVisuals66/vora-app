<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Domain\Ticket\Services\ConversationOrchestrator;
use App\Http\Controllers\Controller;
use App\Infra\Evolution\EvolutionApiClient;
use App\Infra\Evolution\WebhookEventDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DevSimulatorController extends Controller
{
    private const TEST_PHONE = '5500000000000';
    private const TEST_NAME  = 'Cliente Teste';

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|max:500']);

        $session = WhatsappSession::where('is_primary', true)->first()
            ?? WhatsappSession::first();

        if (! $session) {
            return response()->json(['message' => 'Nenhuma sessão WhatsApp configurada.'], 422);
        }

        $jid   = self::TEST_PHONE . '@s.whatsapp.net';
        $msgId = 'SIM' . Str::upper(Str::random(16));

        // Bind a no-op Evolution client so sendText returns instantly without HTTP calls
        app()->bind(EvolutionApiClient::class, fn () => new class extends EvolutionApiClient {
            public function sendText(string $instance, string $jid, string $text, ?string $quotedId = null): array
            {
                return ['key' => ['id' => 'SIM-' . Str::upper(Str::random(8))]];
            }
        });

        $evt = WebhookEventDTO::fromPayload([
            'event'    => 'messages.upsert',
            'instance' => $session->instance_name,
            'data'     => [
                'key'         => ['remoteJid' => $jid, 'fromMe' => false, 'id' => $msgId],
                'message'     => ['conversation' => $data['message']],
                'pushName'    => self::TEST_NAME,
                'messageType' => 'conversation',
            ],
        ]);

        app(ConversationOrchestrator::class)->handleInbound($evt);

        return $this->load($session->tenant_id);
    }

    public function get(Request $request): JsonResponse
    {
        $session  = WhatsappSession::where('is_primary', true)->first() ?? WhatsappSession::first();
        return $this->load($session?->tenant_id);
    }

    public function reset(): JsonResponse
    {
        $session  = WhatsappSession::where('is_primary', true)->first() ?? WhatsappSession::first();
        $tenantId = $session?->tenant_id;

        if ($tenantId) {
            $client = Client::where('tenant_id', $tenantId)->where('phone', self::TEST_PHONE)->first();
            if ($client) {
                Ticket::where('client_id', $client->id)
                    ->whereNotIn('status', ['closed'])
                    ->update(['status' => 'closed', 'closed_at' => now()]);
            }
        }

        return response()->json(['data' => ['ticket' => null, 'messages' => []]]);
    }

    private function load(?int $tenantId): JsonResponse
    {
        if (! $tenantId) {
            return response()->json(['data' => ['ticket' => null, 'messages' => []]]);
        }

        $client = Client::where('tenant_id', $tenantId)->where('phone', self::TEST_PHONE)->first();
        $ticket = $client
            ? ($client->activeTicket() ?? Ticket::where('client_id', $client->id)->latest()->first())
            : null;

        $messages = $ticket
            ? Message::where('ticket_id', $ticket->id)
                ->orderBy('sent_at')->orderBy('id')
                ->get()
            : collect();

        return response()->json([
            'data' => [
                'ticket'   => $ticket ? [
                    'id'       => $ticket->id,
                    'protocol' => $ticket->protocol,
                    'status'   => $ticket->status,
                    'sector'   => $ticket->sector?->name,
                ] : null,
                'messages' => $messages->map(fn ($m) => [
                    'id'         => $m->id,
                    'direction'  => $m->direction,
                    'body'       => $m->body,
                    'created_at' => $m->created_at?->toISOString(),
                    'is_bot'     => ($m->metadata['source'] ?? null) === 'bot',
                ]),
            ],
        ]);
    }
}
