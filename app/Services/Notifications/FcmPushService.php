<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    /** Send push notification to all devices registered for a user. */
    public function sendToUser(User $user, string $title, string $body, ?string $actionUrl = null): void
    {
        $tokens = UserFcmToken::where('user_id', $user->id)->pluck('token');

        if ($tokens->isEmpty()) {
            return;
        }

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $actionUrl);
        }
    }

    public function send(string $token, string $title, string $body, ?string $actionUrl = null): void
    {
        $serverKey = config('services.fcm.server_key');

        if (! $serverKey) {
            Log::info('FCM push skipped (no server key)', ['title' => $title, 'body' => $body, 'token' => $this->maskToken($token)]);

            return;
        }

        $payload = [
            'to'           => $token,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'data' => array_filter([
                'action_url' => $actionUrl,
            ]),
        ];

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: key='.$serverKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::warning('FCM push failed', ['error' => $error, 'token' => $this->maskToken($token)]);
        } else {
            Log::debug('FCM push sent', ['response' => $response, 'token' => $this->maskToken($token)]);
        }
    }

    /**
     * Finding 5 fix (SECURITY_CODE_AUDIT_2026_08_11.md, 2026-08-15): FCM tokens are
     * semi-sensitive per-device identifiers — log a truncated form instead of the full
     * value, so log access alone can't be used to send spoofed pushes to a device.
     */
    private function maskToken(string $token): string
    {
        return substr($token, 0, 12).'…';
    }
}
