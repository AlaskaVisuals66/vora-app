<?php

namespace App\Jobs;

use App\Infra\N8n\N8nClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyN8nEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(public string $event, public mixed $payload) {}

    public function handle(N8nClient $n8n): void
    {
        $path = match ($this->event) {
            'ticket.assigned'              => 'ticket-assigned',
            'ticket.queued.no_attendant'   => 'ticket-queued-no-attendant',
            'ticket.transferred'           => 'ticket-transferred',
            'ticket.closed'                => 'ticket-closed',
            'sla.breach'                   => 'sla-breach',
            default                        => 'helpdesk-event',
        };

        $n8n->trigger($path, [
            'event' => $this->event,
            'payload' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ]);
    }
}
