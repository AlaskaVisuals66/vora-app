<?php

namespace App\Domain\Ticket\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Sector\Models\Sector;
use App\Domain\Ticket\Models\Ticket;
use App\Infra\Realtime\RealtimePresence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Decides which attendant should pick up a ticket.
 * Strategies: round_robin (default), least_busy.
 * All decisions go through a Redis lock to avoid double-assignment.
 */
class AttendantDistributor
{
    public function __construct(private readonly RealtimePresence $presence) {}

    public function assign(Ticket $ticket): ?User
    {
        $sector = Sector::query()->find($ticket->sector_id);
        if (! $sector) return null;

        $strategy = config('helpdesk.distribution.strategy', 'round_robin');
        $candidates = $this->candidates($sector);
        if (empty($candidates)) return null;

        $picked = $strategy === 'least_busy'
            ? $this->pickLeastBusy($candidates, $ticket->tenant_id)
            : $this->pickRoundRobin($candidates, $sector->id);

        if (! $picked) return null;

        return Cache::lock("ticket-assign:{$ticket->id}", config('helpdesk.distribution.lock_ttl'))
            ->block(2, function () use ($ticket, $picked) {
                $fresh = Ticket::query()->find($ticket->id);
                if ($fresh && in_array($fresh->status, ['queued','menu'], true)) {
                    $fresh->update([
                        'assigned_to' => $picked->id,
                        'assigned_at' => now(),
                        'status'      => 'open',
                    ]);
                    return $picked;
                }
                return null;
            });
    }

    /** @return User[] online attendants of a sector with free slots. */
    private function candidates(Sector $sector): array
    {
        $onlineIds = $this->presence->onlineUserIds($sector->tenant_id);
        if (empty($onlineIds)) return [];

        $maxConcurrent = (int) config('helpdesk.distribution.max_concurrent_per_attendant', 5);

        return $sector->attendants()
            ->whereIn('users.id', $onlineIds)
            ->where('users.is_active', true)
            ->where('users.status', 'online')
            ->withCount(['tickets as open_count' => fn ($q) => $q->whereIn('status', ['open','pending'])])
            ->get()
            ->filter(fn ($u) => (int) $u->open_count < $maxConcurrent)
            ->values()
            ->all();
    }

    private function pickRoundRobin(array $users, int $sectorId): ?User
    {
        $key = "rr:sector:{$sectorId}";
        $idx = (int) (Cache::increment($key) - 1);
        return $users[$idx % count($users)] ?? null;
    }

    private function pickLeastBusy(array $users, int $tenantId): ?User
    {
        usort($users, fn (User $a, User $b) => ($a->open_count <=> $b->open_count));
        return $users[0] ?? null;
    }
}
