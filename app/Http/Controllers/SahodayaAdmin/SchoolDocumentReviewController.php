<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\School\SchoolDocumentTypeSeeder;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolDocumentReviewController extends SahodayaAdminController
{
    public function index(Request $request, SchoolDocumentTypeSeeder $seeder)
    {
        $seeder->seedForSahodaya($this->sahodaya->id);

        $status = $request->string('status')->toString() ?: 'pending';

        $documents = SchoolDocument::whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $this->sahodaya->id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['documentType', 'school'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $schoolNames = Tenant::whereIn('id', $documents->pluck('school_id')->unique())
            ->pluck('name', 'id');

        return $this->inertia('Sahodaya/Documents/Review', [
            'documents'   => $documents,
            'schoolNames' => $schoolNames,
            'filters'     => ['status' => $status],
        ]);
    }

    public function approve(Request $request, SchoolDocument $document)
    {
        $this->assertDocumentInScope($document);

        $document->update([
            'status'              => 'approved',
            'rejection_reason'    => null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at'         => now(),
        ]);

        return back()->with('success', 'Document approved.');
    }

    public function reject(Request $request, SchoolDocument $document)
    {
        $this->assertDocumentInScope($document);

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $document->update([
            'status'              => 'rejected',
            'rejection_reason'    => $data['rejection_reason'],
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at'         => now(),
        ]);

        $this->notifySchoolRejection($document);

        return back()->with('success', 'Document rejected and school notified.');
    }

    public function download(SchoolDocument $document)
    {
        $this->assertDocumentInScope($document);

        return TenantStorage::downloadPrivate($document->file_path, $document->storage_disk, $document->file_name ?? 'document');
    }

    private function assertDocumentInScope(SchoolDocument $document): void
    {
        abort_unless(
            $document->documentType?->sahodaya_id === $this->sahodaya->id,
            404,
        );
    }

    private function notifySchoolRejection(SchoolDocument $document): void
    {
        $document->load('documentType');
        $school = Tenant::find($document->school_id);
        if (! $school) {
            return;
        }

        $admin = User::query()
            ->where('tenant_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'school_admin'))
            ->first();
        if (! $admin) {
            return;
        }

        $title = 'Document rejected';
        $body = "Your {$document->documentType->name} was rejected: {$document->rejection_reason}";

        app(NotificationService::class)->notify(
            $admin,
            $title,
            $body,
            "/school-admin/{$school->id}/documents",
            ['in_app', 'email'],
        );
    }
}
