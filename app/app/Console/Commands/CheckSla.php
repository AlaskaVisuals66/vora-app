<?php

namespace App\Console\Commands;

use App\Domain\Ticket\Models\Ticket;
use App\Jobs\NotifyN8nEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckSla extends Command
{
    protected $signature = 'helpdesk:check-sla';
    protected $description = 'Detect first-response SLA breaches and notify n8n (once per ticket/day)';

    public function handle(): int
    {
        $frSeconds = (int) config('helpdesk.sla.first_response_seconds', 180);
        $cutoff    = now()->subSeconds($frSeconds);
        $count     = 0;

        Ticket::query()
            ->whereIn('status', ['queued', 'open', 'pending'])
            ->whereNull('first_response_at')
            ->where(function ($q) use ($cutoff) {
                $q->where('queued_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('queued_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->chunkById(200, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    // Dedupe: only notify once per ticket per day.
                    if (Cache::add("sla:first_response:{$ticket->id}", 1, now()->addDay())) {
                        NotifyN8nEvent::dispatch('sla.breach', $ticket->id);
                        $count++;
                    }
                }
            });

        $this->info("SLA: {$count} first-response breach(es) notified.");
        return self::SUCCESS;
    }
}
