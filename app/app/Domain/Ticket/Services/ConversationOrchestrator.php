<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Events\MessageReceived;
use App\Events\TicketAssigned;
use App\Events\TicketQueued;
use App\Infra\Evolution\EvolutionApiClient;
use App\Infra\Evolution\WebhookEventDTO;
use App\Jobs\NotifyN8nEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Top-level orchestrator for inbound WhatsApp events.
 * - Resolves session/tenant
 * - Upserts client
 * - Picks active ticket or starts a new one (menu)
 * - Persists message
 * - Drives MenuEngine, then AttendantDistributor
 * - Broadcasts realtime events
 */
class ConversationOrchestrator
{
    public function __construct(
        private readonly MenuEngine $menu,
        private readonly AttendantDistributor $distributor,
        private readonly ProtocolGenerator $protocols,
        private readonly EvolutionApiClient $evolution,
    ) {}

    public function handleInbound(WebhookEventDTO $evt): void
    {
        if ($evt->fromMe || ! $evt->fromNumber) return;

        $session = WhatsappSession::where('instance_name', $evt->instance)->firstOrFail();
        $tenantId = $session->tenant_id;

        DB::transaction(function () use ($evt, $session, $tenantId) {
            $client = Client::firstOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $evt->fromNumber],
                ['name' => $evt->pushName, 'whatsapp_jid' => $evt->remoteJid]
            );

            $client->forceFill(['last_message_at' => now()])->save();

            $ticket = $client->activeTicket();
            $justCreated = false;

            if (! $ticket) {
                $ticket = Ticket::create([
                    'tenant_id'           => $tenantId,
                    'protocol'            => $this->protocols->next($tenantId),
                    'client_id'           => $client->id,
                    'whatsapp_session_id' => $session->id,
                    'status'              => 'menu',
                    'channel'             => 'whatsapp',
                ]);
                $welcome = $this->menu->start($ticket);
                $this->replyAndPersist($ticket, $session->instance_name, $evt->remoteJid, $welcome);
                $justCreated = true;
            }

            $message = Message::create([
                'tenant_id'   => $tenantId,
                'ticket_id'   => $ticket->id,
                'external_id' => $evt->messageId,
                'direction'   => 'inbound',
                'type'        => $evt->messageType,
                'body'        => $evt->body,
                'media'       => $evt->media,
                'metadata'    => ['pushName' => $evt->pushName],
                'status'      => 'delivered',
                'delivered_at'=> now(),
            ]);
            $ticket->increment('messages_count');
            $ticket->update(['last_message_at' => now()]);

            broadcast(new MessageReceived($message))->toOthers();

            $body = is_string($evt->body) ? trim($evt->body) : '';
            $isText = $evt->messageType === 'text' && $body !== '';

            // #menu — cliente pode voltar ao menu a qualquer momento (exceto se acabou de receber boas-vindas)
            if (! $justCreated && $isText && Str::lower($body) === '#menu' && in_array($ticket->status, ['queued','open','pending'], true)) {
                $ticket->forceFill(['sector_id' => null, 'assigned_to' => null, 'status' => 'menu'])->save();
                $welcome = $this->menu->start($ticket);
                $this->replyAndPersist($ticket, $session->instance_name, $evt->remoteJid, $welcome);
                return;
            }

            // Drive menu / route — skip if ticket was just created this request (welcome already sent)
            if (! $justCreated && $ticket->status === 'menu' && $isText) {
                $result = $this->menu->consume($ticket, $body);
                $this->replyAndPersist($ticket, $session->instance_name, $evt->remoteJid, $result['reply']);

                if ($result['done'] && $result['sector']) {
                    broadcast(new TicketQueued($ticket->refresh()));
                    $assignee = $this->distributor->assign($ticket);
                    if ($assignee) {
                        broadcast(new TicketAssigned($ticket->refresh()));
                        NotifyN8nEvent::dispatch('ticket.assigned', $ticket->id);
                    } else {
                        NotifyN8nEvent::dispatch('ticket.queued.no_attendant', $ticket->id);
                    }
                }
            } elseif ($ticket->status === 'queued' && ! $ticket->assigned_to && $isText) {
                // Cliente mandando mensagem enquanto aguarda atendente — avisa 1x por sessão de fila
                $state = $ticket->menu_state ?: [];
                if (empty($state['queue_notified'])) {
                    $reply = strtr(config('helpdesk.menu.no_attendant'), ['{position}' => '—']);
                    $this->replyAndPersist($ticket, $session->instance_name, $evt->remoteJid, $reply);
                    $state['queue_notified'] = true;
                    $ticket->menu_state = $state;
                    $ticket->save();
                }
            }
        });
    }

    public function reply(string $instance, string $jid, string $text): void
    {
        $this->evolution->sendText($instance, $jid, $text);
    }

    private function replyAndPersist(Ticket $ticket, string $instance, string $jid, string $text): void
    {
        $resp = null;
        try { $resp = $this->evolution->sendText($instance, $jid, $text); } catch (\Throwable $e) {}

        $message = Message::create([
            'tenant_id'   => $ticket->tenant_id,
            'ticket_id'   => $ticket->id,
            'external_id' => $resp['key']['id'] ?? null,
            'direction'   => 'outbound',
            'type'        => 'text',
            'body'        => $text,
            'metadata'    => ['source' => 'bot'],
            'status'      => 'sent',
            'sent_at'     => now(),
        ]);
        $ticket->increment('messages_count');
        $ticket->update(['last_message_at' => now()]);
        broadcast(new MessageReceived($message))->toOthers();
    }
}
