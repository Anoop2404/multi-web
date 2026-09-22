<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Download;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class DownloadController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $downloads = Download::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('academic_year', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'hidden', fn ($query) => $query->where('is_active', false))
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return $this->inertia('School/Downloads/Index', [
            'downloads' => $downloads,
            'filters' => compact('search', 'category', 'status'),
        ]);
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
        $path = $file->store('downloads/' . $this->school->id, \App\Support\TenantStorage::uploadDisk());

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
