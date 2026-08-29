<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAnnouncement;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

/**
 * FRD-13 §9 maintenance mode + announcements. "Maintenance mode" is deliberately just
 * an announcement with type=maintenance rather than a second mechanism — see the
 * platform_announcements migration docblock for why artisan down was ruled out.
 */
class PlatformAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = PlatformAnnouncement::with('createdBy:id,name')
            ->latest('id')
            ->get();

        return inertia('Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function store(Request $request, PlatformAuditLogger $audit)
    {
        $data = $this->validated($request);
        $data['created_by_user_id'] = $request->user()->id;

        $announcement = PlatformAnnouncement::create($data);

        $audit->log('announcement.created', "Announcement created: {$announcement->title}", $announcement, ['announcement_id' => $announcement->id], category: 'platform');

        return back()->with('success', 'Announcement created.');
    }

    public function update(Request $request, PlatformAnnouncement $announcement, PlatformAuditLogger $audit)
    {
        $data = $this->validated($request);
        $announcement->update($data);

        $audit->log('announcement.updated', "Announcement updated: {$announcement->title}", $announcement, ['announcement_id' => $announcement->id, 'changes' => $announcement->getChanges()], category: 'platform');

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(PlatformAnnouncement $announcement, PlatformAuditLogger $audit)
    {
        $title = $announcement->title;
        $announcement->delete();

        $audit->log('announcement.deleted', "Announcement deleted: {$title}", null, ['announcement_id' => $announcement->id], category: 'platform');

        return back()->with('success', 'Announcement removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'type' => 'required|in:info,warning,critical,maintenance',
            'audience' => 'required|in:all,superadmin,state_admin,sahodaya_admin,school_admin',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
