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
            // Scanned/multi-page question papers can run well over 100MB. This is the
            // application-level ceiling only — the actual limit a request can reach also
            // depends on the server's own php.ini (upload_max_filesize, post_max_size) and,
            // if there's a reverse proxy in front, its body-size limit (e.g. nginx's
            // client_max_body_size) — those must allow at least this size too.
            'file'            => 'required|mimes:pdf,doc,docx|max:307200',
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

    public function update(Request $request, string $tenantId, QuestionBankDocument $questionBankDocument)
    {
        abort_if($questionBankDocument->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'master_class_id' => 'required|integer',
            'subject'         => 'nullable|string|max:100',
            'academic_year'   => 'nullable|string|max:20',
            // File is optional on edit — omit it to keep the currently uploaded file.
            'file'            => 'nullable|mimes:pdf,doc,docx|max:307200',
        ]);

        if ($request->hasFile('file')) {
            $oldPath = $questionBankDocument->file_path;
            $data['file_path'] = $request->file('file')->store(
                'sahodaya/'.$this->sahodaya->id.'/question-bank',
                TenantStorage::uploadDisk()
            );
            Storage::disk(TenantStorage::uploadDisk())->delete($oldPath);
        }

        unset($data['file']);
        $questionBankDocument->update($data);

        return back()->with('success', 'Question bank document updated.');
    }

    public function destroy(string $tenantId, QuestionBankDocument $questionBankDocument)
    {
        abort_if($questionBankDocument->tenant_id !== $this->sahodaya->id, 403);

        Storage::disk(TenantStorage::uploadDisk())->delete($questionBankDocument->file_path);
        $questionBankDocument->delete();

        return back()->with('success', 'Document removed.');
    }
}
