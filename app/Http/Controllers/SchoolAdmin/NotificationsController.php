<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\InAppNotification;
use Illuminate\Http\Request;

class NotificationsController extends SchoolAdminController
{
    public function index()
    {
        $notifications = InAppNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->take(100)
            ->get();

        return $this->inertia('School/Notifications/Index', compact('notifications'));
    }

    public function markRead(Request $request)
    {
        $data = $request->validate([
            'notification_id' => 'nullable|exists:in_app_notifications,id',
            'all'             => 'nullable|boolean',
        ]);

        $query = InAppNotification::where('user_id', auth()->id())->whereNull('read_at');

        if (! empty($data['all'])) {
            $query->update(['read_at' => now()]);
        } elseif (! empty($data['notification_id'])) {
            $query->where('id', $data['notification_id'])->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notifications marked as read.');
    }
}
