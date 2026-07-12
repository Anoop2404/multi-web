<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Prevents scheduled reminder commands (training, board-result upload, MCQ exam, etc.)
 * from sending duplicate reminders when the scheduler runs more than once for the same
 * entity on the same day (manual re-run, overlapping cron entries, retried jobs).
 *
 * Each reminder occurrence is deduped per calendar day using a caller-supplied
 * composite key (command name + reminder slug/type + entity id + recipient id, etc.).
 */
class ReminderDedupGuard
{
    /**
     * Atomically claims today's send-slot for the given reminder key.
     *
     * Returns true the first time it's called for a given key combination on a given
     * day (caller should proceed to send), and false on any subsequent call for the
     * same combination the same day (caller should skip — already sent today).
     */
    public static function claim(string|int ...$parts): bool
    {
        $key = 'reminder-dedup:'.implode(':', array_map('strval', $parts)).':'.now()->toDateString();

        return Cache::add($key, true, now()->endOfDay());
    }
}
