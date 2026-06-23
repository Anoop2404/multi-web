<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('fest-scoreboard.{tenantId}.{eventId}', function ($user, string $tenantId, int $eventId) {
    return $user !== null;
});
