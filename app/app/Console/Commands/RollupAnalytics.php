<?php

namespace App\Console\Commands;

use App\Domain\Message\Models\Message;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RollupAnalytics extends Command
{
    protected $signature = 'helpdesk:rollup-analytics {--date= : Day to roll up (Y-m-d). Defaults to yesterday.}';
    protected $description = 'Aggregate per-tenant daily metrics into the analytics_daily table';

    public function handle(): int
    {
        $date  = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->subDay()->toDateString();
        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $tenantIds = Tenant::query()->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $opened   = Ticket::where('tenant_id', $tenantId)->whereBetween('created_at', [$start, $end])->count();
            $closed   = Ticket::where('tenant_id', $tenantId)->whereBetween('closed_at', [$start, $end])->count();
            $inbound  = Message::where('tenant_id', $tenantId)->where('direction', 'inbound')->whereBetween('created_at', [$start, $end])->count();
            $outbound = Message::where('tenant_id', $tenantId)->where('direction', 'outbound')->whereBetween('created_at', [$start, $end])->count();

            $avgFr = (int) round((float) Ticket::where('tenant_id', $tenantId)
                ->whereBetween('first_response_at', [$start, $end])
                ->whereNotNull('first_response_seconds')
                ->avg('first_response_seconds'));

            $avgRes = (int) round((float) Ticket::where('tenant_id', $tenantId)
                ->whereBetween('closed_at', [$start, $end])
                ->whereNotNull('resolution_seconds')
                ->avg('resolution_seconds'));

            DB::table('analytics_daily')->updateOrInsert(
                ['tenant_id' => $tenantId, 'sector_id' => null, 'user_id' => null, 'date' => $date],
                [
                    'tickets_opened'             => $opened,
                    'tickets_closed'             => $closed,
                    'messages_inbound'           => $inbound,
                    'messages_outbound'          => $outbound,
                    'avg_first_response_seconds' => $avgFr ?: null,
                    'avg_resolution_seconds'     => $avgRes ?: null,
                    'updated_at'                 => now(),
                ],
            );
        }

        $this->info("Rolled up analytics for {$date} ({$tenantIds->count()} tenant(s)).");
        return self::SUCCESS;
    }
}
