<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolDocumentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolDocumentTypeController extends SahodayaAdminController
{
    public function index()
    {
        $types = SchoolDocumentType::where('sahodaya_id', $this->sahodaya->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->inertia('Sahodaya/Documents/Types', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'             => [
                'required', 'string', 'max:40', 'alpha_dash',
                Rule::unique('school_document_types', 'code')->where('sahodaya_id', $this->sahodaya->id),
            ],
            'name'             => 'required|string|max:255',
            'is_required'      => 'boolean',
            'validity_months'  => 'nullable|integer|min:1|max:120',
            'sort_order'       => 'nullable|integer|min:0|max:999',
        ]);

        SchoolDocumentType::create([
            'sahodaya_id'     => $this->sahodaya->id,
            'code'            => $data['code'],
            'name'            => $data['name'],
            'is_required'     => $request->boolean('is_required'),
            'validity_months' => $data['validity_months'] ?? null,
            'sort_order'      => $data['sort_order'] ?? 0,
            'is_active'       => true,
        ]);

        return back()->with('success', 'Document type created.');
    }

    public function update(Request $request, SchoolDocumentType $documentType)
    {
        abort_unless($documentType->sahodaya_id === $this->sahodaya->id, 404);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'is_required'      => 'boolean',
            'validity_months'  => 'nullable|integer|min:1|max:120',
            'sort_order'       => 'nullable|integer|min:0|max:999',
            'is_active'        => 'boolean',
        ]);

        $documentType->update([
            'name'            => $data['name'],
            'is_required'     => $request->boolean('is_required'),
            'validity_months' => $data['validity_months'] ?? null,
            'sort_order'      => $data['sort_order'] ?? $documentType->sort_order,
            'is_active'       => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Document type updated.');
    }

    public function destroy(SchoolDocumentType $documentType)
    {
        abort_unless($documentType->sahodaya_id === $this->sahodaya->id, 404);

        if ($documentType->documents()->exists()) {
            return back()->with('error', 'Cannot delete a type that has uploaded documents.');
        }

        $documentType->delete();

        return back()->with('success', 'Document type removed.');
    }
}
