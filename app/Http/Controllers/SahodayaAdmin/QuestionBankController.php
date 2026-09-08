<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\QuestionBankDocument;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuestionBankController extends SahodayaAdminController
{
    public function index(EffectiveMasterDataResolver $masterData)
    {
        $documents = QuestionBankDocument::where('tenant_id', $this->sahodaya->id)
            ->with('masterClass')
            ->orderByDesc('created_at')
            ->get();

        $masterClasses = $masterData->masterClasses($this->sahodaya->id)
            ->map(fn ($class) => ['id' => $class->id, 'name' => $class->name]);

        return $this->inertia('Sahodaya/QuestionBank/Index', [
            'documents'     => $documents,
            'masterClasses' => $masterClasses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'master_class_id' => 'required|integer',
            'subject'         => 'nullable|string|max:100',
            'academic_year'   => 'nullable|string|max:20',
            'file'            => 'required|mimes:pdf,doc,docx|max:10240',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['file_path'] = $request->file('file')->store(
            'sahodaya/'.$this->sahodaya->id.'/question-bank',
            TenantStorage::uploadDisk()
        );

        unset($data['file']);
        QuestionBankDocument::create($data);

        return back()->with('success', 'Question bank document uploaded.');
    }

    public function destroy(string $tenantId, QuestionBankDocument $questionBankDocument)
    {
        abort_if($questionBankDocument->tenant_id !== $this->sahodaya->id, 403);

        Storage::disk(TenantStorage::uploadDisk())->delete($questionBankDocument->file_path);
        $questionBankDocument->delete();

        return back()->with('success', 'Document removed.');
    }
}
