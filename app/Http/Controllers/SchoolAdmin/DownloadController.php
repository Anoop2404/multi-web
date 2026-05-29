<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Download;
use Illuminate\Http\Request;

class DownloadController extends SchoolAdminController
{
    public function index()
    {
        $downloads = Download::where('tenant_id', $this->school->id)
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->get();

        return $this->inertia('School/Downloads/Index', compact('downloads'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'category'      => 'required|in:booklist,calendar,circular,question_paper,annual_report,form,minutes,other',
            'academic_year' => 'nullable|string|max:20',
            'is_active'     => 'boolean',
            'file'          => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('downloads/' . $this->school->id, 's3');

        Download::create([
            'tenant_id'     => $this->school->id,
            'title'         => $data['title'],
            'category'      => $data['category'],
            'academic_year' => $data['academic_year'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
        ]);

        return back()->with('success', 'File uploaded.');
    }

    public function destroy(string $tenantId, Download $download)
    {
        abort_if($download->tenant_id !== $this->school->id, 403);
        $download->delete();
        return back()->with('success', 'Download removed.');
    }
}
