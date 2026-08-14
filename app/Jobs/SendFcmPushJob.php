<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Wraps FcmPushService::sendToUser() in a queued job so a blocking curl call (up to a
 * 10s timeout, per device token) to FCM doesn't stall the HTTP request or console
 * command that triggered it. NotificationService::notify() previously called
 * sendToUser() inline, and notify() is itself invoked from inside per-recipient
 * loops (bulk admin notify, board-result certification, student-edit notifications,
 * schedule reminders) — a slow/unresponsive FCM endpoint would serialize and stall
 * the whole loop.
 *
 * Note: this only becomes genuinely async once QUEUE_CONNECTION is something other
 * than "sync" (see docs/SCALE_AND_PAGINATION_PLAN.md §8a and
 * PERFORMANCE_FIX_PLAN_2026_08_13.md Phase 0) — with the sync driver, dispatch()
 * still runs this inline, exactly as before. The fix is still correct to make now:
 * once the queue driver is switched, this starts running async with no further
 * code change needed.
 */
class SendFcmPushJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
    ) {}

    public function handle(FcmPushService $fcm): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $fcm->sendToUser($user, $this->title, $this->body, $this->actionUrl);
    }
}
