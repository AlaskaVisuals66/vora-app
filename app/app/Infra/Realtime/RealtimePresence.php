<?php

namespace App\Infra\Realtime;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks online attendants per tenant. Stores [user_id => last_seen_unix] in Cache.
 * Originally backed by Redis sorted sets; downgraded to Cache so the app runs
 * without a Redis instance in development.
 */
class RealtimePresence
{
    public const TTL_SECONDS = 60;

    private function key(int $tenantId): string
    {
        return "presence:tenant:{$tenantId}";
    }

    public function ping(int $tenantId, int $userId): void
    {
        $map = Cache::get($this->key($tenantId), []);
        $map[$userId] = time();
        Cache::put($this->key($tenantId), $map, now()->addMinutes(self::TTL_SECONDS));
    }

    public function leave(int $tenantId, int $userId): void
    {
        $map = Cache::get($this->key($tenantId), []);
        unset($map[$userId]);
        Cache::put($this->key($tenantId), $map, now()->addMinutes(self::TTL_SECONDS));
    }

    /** @return int[] user ids active in the last TTL_SECONDS */
    public function onlineUserIds(int $tenantId): array
    {
        $threshold = time() - self::TTL_SECONDS;
        $map = Cache::get($this->key($tenantId), []);
        return array_values(array_map('intval', array_keys(array_filter(
            $map,
            fn ($ts) => $ts >= $threshold
        ))));
    }
}
