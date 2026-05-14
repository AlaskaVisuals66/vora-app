<?php

namespace App\Console\Commands;

use App\Domain\Ticket\Models\Ticket;
use Illuminate\Console\Command;

class AutoCloseIdleTickets extends Command
{
    protected $signature = 'helpdesk:auto-close-idle {--hours=24}';
    protected $description = 'Close tickets idle for more than N hours so the next inbound message restarts the welcome menu';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $count = Ticket::query()
            ->whereIn('status', ['menu','queued','open','pending'])
            ->where(function ($q) use ($cutoff) {
                $q->where('last_message_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('last_message_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

        $this->info("Closed {$count} idle ticket(s) (>{$hours}h).");
        return self::SUCCESS;
    }
}
