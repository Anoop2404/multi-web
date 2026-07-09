<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SchoolDocument;
use App\Models\SchoolDocumentType;
use App\Services\School\SchoolDocumentTypeSeeder;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolDocumentController extends SchoolAdminController
{
    public function index(SchoolDocumentTypeSeeder $seeder)
    {
        $sahodayaId = $this->school->parent_id;
        if ($sahodayaId) {
            $seeder->seedForSahodaya($sahodayaId);
        }

        $types = SchoolDocumentType::where('sahodaya_id', $sahodayaId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $documents = SchoolDocument::where('school_id', $this->school->id)
            ->with('documentType')
            ->orderByDesc('created_at')
            ->get()
            ->keyBy('document_type_id');

        return $this->inertia('School/Documents/Index', [
            'types'     => $types,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request)
    {
        $sahodayaId = $this->school->parent_id;
        abort_unless($sahodayaId, 403);

        $request->validate([
            'document_type_id' => 'required|exists:school_document_types,id',
            'file'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'valid_from'       => 'nullable|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
        ]);

        $type = SchoolDocumentType::where('sahodaya_id', $sahodayaId)
            ->where('id', $request->integer('document_type_id'))
            ->firstOrFail();

        $file = $request->file('file');
        $disk = TenantStorage::uploadDisk();
        $path = TenantStorage::storeUploadedFile($file, "school-documents/{$this->school->id}", $disk);

        $validFrom = $request->date('valid_from') ?? now()->toDateString();
        $validTo = $request->date('valid_to');
        if (! $validTo && $type->validity_months) {
            $validTo = \Carbon\Carbon::parse($validFrom)->addMonths($type->validity_months)->toDateString();
        }

        SchoolDocument::updateOrCreate(
            [
                'school_id'        => $this->school->id,
                'document_type_id' => $type->id,
            ],
            [
                'file_path'             => $path,
                'storage_disk'        => $disk,
                'file_name'             => $file->getClientOriginalName(),
                'valid_from'            => $validFrom,
                'valid_to'              => $validTo,
                'status'                => 'pending',
                'rejection_reason'      => null,
                'uploaded_by_user_id'   => $request->user()->id,
                'reviewed_by_user_id'   => null,
                'reviewed_at'           => null,
            ],
        );

        return back()->with('success', 'Document uploaded and submitted for review.');
    }

    public function download(string $tenantId, SchoolDocument $document)
    {
        abort_unless($document->school_id === $this->school->id, 404);

        return TenantStorage::downloadPrivate($document->file_path, $document->storage_disk, $document->file_name ?? 'document');
    }
}
