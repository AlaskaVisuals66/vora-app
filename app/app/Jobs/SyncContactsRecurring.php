<?php

namespace App\Jobs;

use App\Domain\Ticket\Models\WhatsappSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Keeps the contacts list in sync with WhatsApp: re-imports every number's
 * contacts once an hour, so contacts added in WhatsApp show up here too.
 *
 * Self-rescheduling on the Horizon queue (no system cron needed). It re-queues
 * the NEXT run before doing any work, and never retries, so the hourly chain
 * survives a failed run and never forks.
 */
class SyncContactsRecurring implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        // Queue the next hourly run first, so the loop keeps going even if a
        // per-instance import below fails.
        self::dispatch()->delay(now()->addHour());

        foreach (WhatsappSession::all() as $session) {
            ImportInstanceContacts::dispatch($session->id);
        }
    }
}
