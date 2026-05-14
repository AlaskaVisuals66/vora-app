<?php

namespace App\Domain\Ticket\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProtocolGenerator
{
    public function next(int $tenantId): string
    {
        $prefix = config('helpdesk.protocol.prefix', 'HD');
        $pad    = (int) config('helpdesk.protocol.pad', 6);
        $month  = Carbon::now()->format('Ym');
        $key    = sprintf('protocol:%d:%s', $tenantId, $month);

        $seq = (int) Cache::lock($key.':lock', 5)->get(function () use ($key) {
            $current = (int) Cache::get($key, 0);
            $next    = $current + 1;
            Cache::put($key, $next, now()->addDays(60));
            return $next;
        });

        return sprintf('%s-%s-%s', $prefix, $month, str_pad((string)$seq, $pad, '0', STR_PAD_LEFT));
    }
}
