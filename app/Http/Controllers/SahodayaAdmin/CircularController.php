<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Circular;
use App\Support\TenantStorage;
use App\Models\CircularAcknowledgement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class CircularController extends SahodayaAdminController
{
    public function index()
    {
        $circulars = Circular::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('issued_date')
            ->get()
            ->map(function (Circular $c) {
                $ackCount = CircularAcknowledgement::where('circular_id', $c->id)->count();
                $schoolCount = Tenant::where('parent_id', $this->sahodaya->id)
                    ->where('type', 'school')
                    ->count();

                $c->setAttribute('ack_count', $ackCount);
                $c->setAttribute('school_count', $schoolCount);

                return $c;
            });

        return $this->inertia('Sahodaya/Circulars/Index', compact('circulars'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'circular_number' => 'nullable|string|max:100',
            'category'        => 'nullable|string|max:100',
            'issued_date'     => 'nullable|date',
            'academic_year'   => 'nullable|string|max:20',
            'file'            => 'required|mimes:pdf,doc,docx|max:10240',
        ]);

        $data['tenant_id']  = $this->sahodaya->id;
        $data['file_path']  = $request->file('file')->store('sahodaya/' . $this->sahodaya->id . '/circulars', \App\Support\TenantStorage::uploadDisk());

        unset($data['file']);
        $circular = Circular::create($data);

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $notifier = app(NotificationService::class);
        $schoolAdmins = User::role('school_admin')->whereIn('tenant_id', $schoolIds)->get();
        foreach ($schoolAdmins as $admin) {
            $notifier->notifyFromTemplate(
                $admin,
                'circular.published',
                ['circular_title' => $circular->title],
                "/school-admin/{$admin->tenant_id}/circulars"
            );
        }

        return back()->with('success', 'Circular uploaded.');
    }

    public function destroy(string $tenantId, Circular $circular)
    {
        abort_if($circular->tenant_id !== $this->sahodaya->id, 403);
        $circular->delete();
        return back()->with('success', 'Circular removed.');
    }
}
