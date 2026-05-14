<?php

namespace App\Jobs;

use App\Domain\Ticket\Services\ConversationOrchestrator;
use App\Infra\Evolution\WebhookEventDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingWhatsappEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public function backoff(): array { return [5, 15, 30, 60, 120]; }

    public function __construct(public array $payload) {}

    public function handle(ConversationOrchestrator $orchestrator): void
    {
        $evt = WebhookEventDTO::fromPayload($this->payload);

        match ($evt->event) {
            'messages.upsert', 'messages.update' => $orchestrator->handleInbound($evt),
            default => null, // future handlers (connection.update, qrcode.updated…)
        };
    }
}
