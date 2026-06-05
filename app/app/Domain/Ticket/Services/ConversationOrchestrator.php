<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Channel\ChannelManager;
use App\Domain\Client\Models\Client;
use App\Domain\Message\Models\Message;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Events\MessageReceived;
use App\Events\TicketAssigned;
use App\Events\TicketQueued;
use App\Infra\Evolution\WebhookEventDTO;
use App\Jobs\NotifyN8nEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationOrchestrator
{
    public function __construct(
        private readonly MenuEngine $menu,
        private readonly AttendantDistributor $distributor,
        private readonly ProtocolGenerator $protocols,
        private readonly ChannelManager $channels,
    ) {}

    public function handleInbound(WebhookEventDTO $evt): void
    {
        if ($evt->fromMe || ! $evt->fromNumber) return;

        $session = WhatsappSession::where('instance_name', $evt->instance)->first();
        if (! $session) {
            $tenant = Tenant::first();
            if (! $tenant) {
                \Log::channel('webhooks')->error('orchestrator.no_tenant', ['instance' => $evt->instance]);
                return;
            }
            $session = WhatsappSession::create([
                'tenant_id'    => $tenant->id,
                'instance_name'=> $evt->instance,
                'display_name' => $evt->instance,
                'state'        => 'connected',
                'is_primary'   => ! WhatsappSession::where('tenant_id', $tenant->id)->where('is_primary', true)->exists(),
            ]);
            \Log::channel('webhooks')->info('orchestrator.session_autocreated', ['instance' => $evt->instance, 'session_id' => $session->id]);
        }
        $tenantId = $session->tenant_id;
        app()->instance('tenant.id', $tenantId);

        // Idempotency: Evolution can re-deliver the same message (retries, upsert).
        // If we've already ingested this inbound id, do nothing — never re-send.
        if ($evt->messageId && Message::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $evt->messageId)
                ->where('direction', 'inbound')
                ->exists()) {
            return;
        }

        // Phase 1 — all DB writes inside the transaction. We only COLLECT the
        // replies/side-effects here; nothing leaves the process until commit.
        $outcome = DB::transaction(function () use ($evt, $session, $tenantId) {
            // Lock the client row so two concurrent inbound messages can't each
            // create a separate "active" ticket for the same conversation.
            $client = Client::firstOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $evt->fromNumber],
                ['name' => $evt->pushName, 'whatsapp_jid' => $evt->remoteJid, 'channel_type' => 'whatsapp']
            );
            $client = Client::query()->whereKey($client->id)->lockForUpdate()->first() ?? $client;
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
                    'channel_type'        => 'whatsapp',
                ]);
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

            $body   = is_string($evt->body) ? trim($evt->body) : '';
            $isText = $evt->messageType === 'text' && $body !== '';

            $replies = [];   // text to send the customer after commit
            $assign  = false;
            $ai      = null;

            if ($justCreated) {
                $replies[] = $this->menu->start($ticket);
            }

            $isMenuReset = ! $justCreated && $isText
                && Str::lower($body) === '#menu'
                && in_array($ticket->status, ['queued','open','pending'], true);

            if ($isMenuReset) {
                $ticket->forceFill(['sector_id' => null, 'assigned_to' => null, 'status' => 'menu'])->save();
                $replies[] = $this->menu->start($ticket);
            } elseif (! $justCreated && $ticket->status === 'menu' && $isText) {
                $result   = $this->menu->consume($ticket, $body);
                $replies[] = $result['reply'];
                $assign    = $result['done'] && $result['sector'];
            } elseif ($ticket->status === 'queued' && ! $ticket->assigned_to && $isText) {
                $state = $ticket->menu_state ?: [];
                if (empty($state['queue_notified'])) {
                    $replies[] = strtr(config('helpdesk.menu.no_attendant'), ['{position}' => '—']);
                    $state['queue_notified'] = true;
                    $ticket->menu_state = $state;
                    $ticket->save();
                }
            }

            if (in_array($ticket->status, ['open', 'pending'], true) && $ticket->sector_id && $isText) {
                $aiSettings = $ticket->sector?->ai_settings ?? [];
                if (! empty($aiSettings['ai_enabled']) && ! empty($aiSettings['n8n_webhook_path'])) {
                    $ai = [
                        'path'    => $aiSettings['n8n_webhook_path'],
                        'payload' => [
                            'tenant_id' => $tenantId,
                            'ticket_id' => $ticket->id,
                            'sector_id' => $ticket->sector_id,
                            'prompt'    => $aiSettings['ai_prompt'] ?? '',
                            'message'   => $message->body,
                            'client'    => ['phone' => $client->phone, 'name' => $client->name],
                        ],
                    ];
                }
            }

            return compact('message', 'ticket', 'replies', 'assign', 'ai');
        });

        // Phase 2 — after commit. Safe to talk to the network / queue now; a
        // rollback above means none of this runs, so replies are never sent twice.
        broadcast(new MessageReceived($outcome['message']))->toOthers();

        $channel = $this->channels->resolve('evolution', $session->instance_name);
        $jid     = $evt->remoteJid;
        foreach ($outcome['replies'] as $text) {
            $this->replyAndPersist($outcome['ticket'], $channel, $jid, $text);
        }

        if ($outcome['assign']) {
            $ticket = $outcome['ticket']->refresh();
            broadcast(new TicketQueued($ticket));
            $assignee = $this->distributor->assign($ticket);
            if ($assignee) {
                broadcast(new TicketAssigned($ticket->refresh()));
                NotifyN8nEvent::dispatch('ticket.assigned', $ticket->id);
            } else {
                NotifyN8nEvent::dispatch('ticket.queued.no_attendant', $ticket->id);
            }
        }

        if ($outcome['ai']) {
            \App\Jobs\TriggerAiWebhook::dispatch($outcome['ai']['path'], $outcome['ai']['payload']);
        }
    }

    public function handleWebChatInbound(int $tenantId, string $phone, string $name, string $text, string $messageType = 'text'): void
    {
        app()->instance('tenant.id', $tenantId);

        DB::transaction(function () use ($tenantId, $phone, $name, $text, $messageType) {
            $client = Client::firstOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $phone],
                ['name' => $name, 'channel_type' => 'web_chat', 'channel_identifier' => $phone]
            );

            $client->forceFill(['last_message_at' => now()])->save();

            $ticket = $client->activeTicket();
            $justCreated = false;

            if (! $ticket) {
                $ticket = Ticket::create([
                    'tenant_id'    => $tenantId,
                    'protocol'     => $this->protocols->next($tenantId),
                    'client_id'    => $client->id,
                    'status'       => 'menu',
                    'channel'      => 'web_chat',
                    'channel_type' => 'web_chat',
                ]);
                $justCreated = true;
            }

            $message = Message::create([
                'tenant_id'   => $tenantId,
                'ticket_id'   => $ticket->id,
                'direction'   => 'inbound',
                'type'        => $messageType,
                'body'        => $text,
                'status'      => 'delivered',
                'delivered_at'=> now(),
            ]);
            $ticket->increment('messages_count');
            $ticket->update(['last_message_at' => now()]);

            broadcast(new MessageReceived($message))->toOthers();

            if ($justCreated) {
                $welcome = $this->menu->start($ticket);
                $this->replyAndPersist($ticket, null, null, $welcome);
                return;
            }

            $body = trim($text);
            $isText = $messageType === 'text' && $body !== '';

            if ($isText && Str::lower($body) === '#menu' && in_array($ticket->status, ['queued','open','pending'], true)) {
                $ticket->forceFill(['sector_id' => null, 'assigned_to' => null, 'status' => 'menu'])->save();
                $welcome = $this->menu->start($ticket);
                $this->replyAndPersist($ticket, null, null, $welcome);
                return;
            }

            if ($ticket->status === 'menu' && $isText) {
                $result = $this->menu->consume($ticket, $body);
                $this->replyAndPersist($ticket, null, null, $result['reply']);

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
                $state = $ticket->menu_state ?: [];
                if (empty($state['queue_notified'])) {
                    $reply = strtr(config('helpdesk.menu.no_attendant'), ['{position}' => '—']);
                    $this->replyAndPersist($ticket, null, null, $reply);
                    $state['queue_notified'] = true;
                    $ticket->menu_state = $state;
                    $ticket->save();
                }
            }

            if (in_array($ticket->status, ['open', 'pending'], true) && $ticket->sector_id && $isText) {
                $sector     = $ticket->sector;
                $aiSettings = $sector?->ai_settings ?? [];
                if (! empty($aiSettings['ai_enabled']) && ! empty($aiSettings['n8n_webhook_path'])) {
                    \App\Jobs\TriggerAiWebhook::dispatch($aiSettings['n8n_webhook_path'], [
                        'tenant_id' => $tenantId, 'ticket_id' => $ticket->id, 'sector_id' => $ticket->sector_id,
                        'prompt' => $aiSettings['ai_prompt'] ?? '', 'message' => $message->body,
                        'client' => ['phone' => $client->phone, 'name' => $client->name],
                    ]);
                }
            }
        });
    }

    public function reply(?string $instance, ?string $jid, string $text): void
    {
        if ($instance && $jid) {
            $channel = $this->channels->resolve('evolution', $instance);
            if ($channel) {
                $channel->sendText($jid, $text);
            }
        }
    }

    private function replyAndPersist($ticket, $channel, ?string $jid, string $text): void
    {
        $resp   = null;
        $failed = false;
        if ($channel && $jid) {
            try {
                $resp = $channel->sendText($jid, $text);
            } catch (\Throwable $e) {
                $failed = true;
                \Log::channel('evolution')->error('orchestrator.sendText failed', [
                    'ticket_id' => $ticket->id,
                    'class'     => get_class($e),
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        $message = Message::create([
            'tenant_id'      => $ticket->tenant_id,
            'ticket_id'      => $ticket->id,
            'external_id'    => $resp['key']['id'] ?? null,
            'direction'      => 'outbound',
            'type'           => 'text',
            'body'           => $text,
            'metadata'       => ['source' => 'bot'],
            'status'         => $failed ? 'failed' : 'sent',
            'failure_reason' => $failed ? 'sendText threw' : null,
            'sent_at'        => $failed ? null : now(),
        ]);
        $ticket->increment('messages_count');
        $ticket->update(['last_message_at' => now()]);
        broadcast(new MessageReceived($message))->toOthers();
    }
}
