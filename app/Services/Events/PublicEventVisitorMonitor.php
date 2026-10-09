<?php

namespace App\Services\Events;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class PublicEventVisitorMonitor
{
    public static function cache(): Repository
    {
        // Use the underlying shared store so Superadmin can read all tenant counts.
        return new Repository(Cache::store()->getStore());
    }

    public static function register(string $key, string $tenantId, int $eventId): void
    {
        $cache = self::cache();
        $cache->lock('fest-visitor-monitor:lock', 10)->block(3, function () use ($cache, $key, $tenantId, $eventId) {
            $index = array_filter($cache->get('fest-visitor-monitor:index', []), fn ($row) => $row['seen'] > time() - 300);
            $index[$key] = ['tenant_id' => $tenantId, 'event_id' => $eventId, 'seen' => time()];
            $cache->put('fest-visitor-monitor:index', $index, 310);
        });
    }

    public static function snapshot(): array
    {
        $cache = self::cache();
        $rows = [];
        $unique = [];
        foreach ($cache->get('fest-visitor-monitor:index', []) as $key => $event) {
            $active = array_filter($cache->get($key, []), fn ($seen) => $seen > time() - 300);
            if (! $active) continue;
            foreach ($active as $id => $seen) $unique[$event['tenant_id'].':'.$id] = true;
            $rows[] = $event + ['active' => count($active), 'limit' => 200];
        }

        return ['active_visitors' => count(array_filter($cache->get('public-active-visitors', []), fn ($seen) => $seen > time() - 300)), 'active_tv_screens' => count(array_filter($cache->get('tv-active-visitors', []), fn ($seen) => $seen > time() - 300)), 'events' => $rows, 'updated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, h:i:s A').' IST'];
    }
}
