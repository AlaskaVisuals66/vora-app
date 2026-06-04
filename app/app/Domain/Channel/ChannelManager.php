<?php

namespace App\Domain\Channel;

use App\Domain\Channel\Channels\EvolutionChannel;
use App\Domain\Channel\Channels\WebChatChannel;
use App\Domain\Channel\Contracts\MessagingChannel;
use App\Domain\Channel\Models\Channel;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\WhatsappSession;
use App\Infra\Evolution\EvolutionApiClient;
use Illuminate\Support\Facades\App;

class ChannelManager
{
    private static ?array $resolved = [];

    public function forTicket(Ticket $ticket): ?MessagingChannel
    {
        $channelType = $ticket->channel;

        if ($channelType === 'whatsapp' && $ticket->whatsapp_session_id) {
            $session = WhatsappSession::find($ticket->whatsapp_session_id);
            if ($session) {
                return $this->resolve('evolution', $session->instance_name);
            }
        }

        $channel = Channel::where('tenant_id', $ticket->tenant_id)
            ->where('type', $channelType)
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return null;
        }

        return $this->resolve($channel->type, $channel->identifier);
    }

    public function forTenant(int $tenantId, string $channelType = 'web_chat'): ?MessagingChannel
    {
        if ($channelType === 'whatsapp' || $channelType === 'evolution') {
            $session = WhatsappSession::where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->first();

            if ($session) {
                return $this->resolve('evolution', $session->instance_name);
            }

            $session = WhatsappSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                return $this->resolve('evolution', $session->instance_name);
            }

            return null;
        }

        $channel = Channel::where('tenant_id', $tenantId)
            ->where('type', $channelType)
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return null;
        }

        return $this->resolve($channel->type, $channel->identifier);
    }

    public function resolve(string $type, ?string $identifier = null): ?MessagingChannel
    {
        $cacheKey = $type . ':' . ($identifier ?? 'default');

        if (isset(self::$resolved[$cacheKey])) {
            return self::$resolved[$cacheKey];
        }

        $channel = match ($type) {
            'evolution', 'whatsapp' => new EvolutionChannel(
                App::make(EvolutionApiClient::class),
                $identifier ?? 'helpdesk-main',
            ),
            'web_chat' => new WebChatChannel(),
            default => null,
        };

        if ($channel) {
            self::$resolved[$cacheKey] = $channel;
        }

        return $channel;
    }

    public function getClientIdentifier(Ticket $ticket): ?string
    {
        $client = $ticket->client;
        if (!$client) {
            return null;
        }

        $channelType = $ticket->channel;

        if ($channelType === 'whatsapp') {
            return $client->whatsapp_jid;
        }

        return $client->phone;
    }

    public static function reset(): void
    {
        self::$resolved = [];
    }
}
