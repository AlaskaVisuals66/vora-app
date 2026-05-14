<?php

namespace App\Domain\Message\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Message\Models\Message;
use App\Domain\Ticket\Models\Ticket;
use App\Events\MessageSent;
use App\Infra\Evolution\EvolutionApiClient;

class OutboundMessageService
{
    public function __construct(private readonly EvolutionApiClient $evolution) {}

    public function sendText(Ticket $ticket, User $sender, string $text): Message
    {
        $message = Message::create([
            'tenant_id'      => $ticket->tenant_id,
            'ticket_id'      => $ticket->id,
            'sender_user_id' => $sender->id,
            'direction'      => 'outbound',
            'type'           => 'text',
            'body'           => $text,
            'status'         => 'queued',
        ]);

        $session = $ticket->whatsapp_session_id
            ? \App\Domain\Ticket\Models\WhatsappSession::find($ticket->whatsapp_session_id)
            : null;

        $client = $ticket->client;
        if ($session && $client?->whatsapp_jid) {
            $resp = $this->evolution->sendText($session->instance_name, $client->whatsapp_jid, $text);
            $message->update([
                'external_id' => $resp['key']['id'] ?? null,
                'status'      => 'sent',
                'sent_at'     => now(),
            ]);
        } else {
            $message->update(['status' => 'failed', 'failure_reason' => 'no_session_or_jid']);
        }

        $ticket->increment('messages_count');
        $ticket->update(['last_message_at' => now()]);

        if ($ticket->first_response_at === null) {
            $first = (int) now()->diffInSeconds($ticket->queued_at ?? $ticket->created_at);
            $ticket->update(['first_response_at' => now(), 'first_response_seconds' => $first]);
        }

        broadcast(new MessageSent($message))->toOthers();
        return $message;
    }
}
