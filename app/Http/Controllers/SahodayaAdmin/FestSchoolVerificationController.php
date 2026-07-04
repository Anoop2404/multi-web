<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestSchoolVerification;
use App\Models\Tenant;
use App\Support\FestPageActivity;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestSchoolVerificationController extends SahodayaAdminController
{
    public function verify(Request $request, string $tenantId, FestEvent $event, string $schoolId)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        Tenant::where('parent_id', $this->sahodaya->id)
            ->where('id', $schoolId)
            ->where('type', 'school')
            ->firstOrFail();

        $data = $request->validate([
            'documents_verified' => 'required|boolean',
            'notes'                => 'nullable|string|max:2000',
        ]);

        FestSchoolVerification::updateOrCreate(
            ['event_id' => $event->id, 'school_id' => $schoolId],
            [
                'documents_verified'  => (bool) $data['documents_verified'],
                'notes'               => $data['notes'] ?? null,
                'verified_by_user_id' => $request->user()?->id,
                'verified_at'         => ($data['documents_verified'] ?? false) ? now() : null,
            ]
        );

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.verification.updated',
            'School document verification updated',
            ['school_id' => $schoolId, 'verified' => (bool) $data['documents_verified']],
        );

        return back()->with('success', 'Verification status saved.');
    }
}
