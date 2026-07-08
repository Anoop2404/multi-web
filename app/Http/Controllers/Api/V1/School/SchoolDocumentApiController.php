<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Controllers\Api\ApiController;
use App\Models\SchoolDocument;
use App\Models\SchoolDocumentType;
use App\Models\Tenant;
use App\Services\School\SchoolDocumentTypeSeeder;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class SchoolDocumentApiController extends ApiController
{
    public function index(Request $request, string $tenantId, SchoolDocumentTypeSeeder $seeder)
    {
        $school = Tenant::where('id', $tenantId)->where('type', 'school')->firstOrFail();
        abort_unless($request->user()?->tenant_id === $school->id, 403);

        if ($school->parent_id) {
            $seeder->seedForSahodaya($school->parent_id);
        }

        $types = SchoolDocumentType::where('sahodaya_id', $school->parent_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $documents = SchoolDocument::where('school_id', $school->id)
            ->with('documentType')
            ->get();

        return $this->ok(compact('types', 'documents'));
    }

    public function store(Request $request, string $tenantId)
    {
        $school = Tenant::where('id', $tenantId)->where('type', 'school')->firstOrFail();
        abort_unless($request->user()?->tenant_id === $school->id, 403);

        $request->validate([
            'document_type_id' => 'required|exists:school_document_types,id',
            'file'             => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $disk = TenantStorage::uploadDisk();
        $path = TenantStorage::storeUploadedFile(
            $request->file('file'),
            "school-documents/{$school->id}",
            $disk,
        );

        $doc = SchoolDocument::updateOrCreate(
            ['school_id' => $school->id, 'document_type_id' => $request->integer('document_type_id')],
            [
                'file_path'           => $path,
                'storage_disk'        => $disk,
                'file_name'           => $request->file('file')->getClientOriginalName(),
                'status'              => 'pending',
                'uploaded_by_user_id' => $request->user()->id,
            ],
        );

        return $this->ok($doc->load('documentType'), 201);
    }
}
