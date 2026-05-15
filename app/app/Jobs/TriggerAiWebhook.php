<?php

namespace App\Jobs;

use App\Infra\N8n\N8nClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerAiWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $webhookPath,
        public readonly array  $payload,
    ) {}

    public function handle(N8nClient $n8n): void
    {
        $n8n->trigger($this->webhookPath, $this->payload);
    }
}
