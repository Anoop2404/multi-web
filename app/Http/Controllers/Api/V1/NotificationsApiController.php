<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Models\UserFcmToken;
use Illuminate\Http\Request;

class NotificationsApiController extends Controller
{
    public function index(Request $request)
    {
        $items = InAppNotification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function markRead(Request $request, InAppNotification $notification)
    {
        abort_if($notification->user_id !== $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification]);
    }

    public function storeFcmToken(Request $request)
    {
        $data = $request->validate([
            'token'       => 'required|string|max:500',
            'device_type' => 'nullable|string|max:50',
        ]);

        UserFcmToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $data['token']],
            ['device_type' => $data['device_type'] ?? null]
        );

        return response()->json(['ok' => true]);
    }
}
