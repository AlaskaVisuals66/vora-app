<?php

namespace App\Jobs;

use App\Domain\Ticket\Services\ConversationOrchestrator;
use App\Infra\Evolution\WebhookEventDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIncomingWhatsappEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public function backoff(): array { return [5, 15, 30, 60, 120]; }

    public function __construct(public array $payload) {}

    /** Dead-letter visibility: log when an inbound event is permanently dropped. */
    public function failed(?Throwable $e): void
    {
        Log::channel('webhooks')->error('inbound.permanently_failed', [
            'event'     => $this->payload['event'] ?? null,
            'instance'  => $this->payload['instance'] ?? null,
            'messageId' => $this->payload['data']['key']['id'] ?? null,
            'error'     => $e?->getMessage(),
        ]);
    }

    public function handle(ConversationOrchestrator $orchestrator): void
    {
        $evt = WebhookEventDTO::fromPayload($this->payload);

        match ($evt->event) {
            // Only NEW messages are inbound. messages.update is a delivery/read
            // receipt (no data.message) — processing it created duplicate empty
            // Message rows and re-triggered the menu. Ignore it here.
            'messages.upsert' => $orchestrator->handleInbound($evt),
            default => null, // future handlers (connection.update, qrcode.updated…)
        };
    }
}
