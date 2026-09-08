<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\MasterClass;
use App\Models\QuestionBankDocument;
use App\Support\TenantStorage;

class QuestionBankController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $documents = QuestionBankDocument::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();

        $masterClasses = MasterClass::whereIn('id', $documents->pluck('master_class_id')->filter()->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        $grouped = $documents
            ->groupBy('master_class_id')
            ->map(fn ($docs, $classId) => [
                'class_name' => $masterClasses->get($classId)?->name ?? 'Other',
                'documents'  => $docs->values(),
            ])
            ->sortBy(fn ($group) => $group['class_name'])
            ->values();

        return $this->renderPublic('public.question-bank.index', $tenant, [
            'groups' => $grouped,
            'pageSeo' => [
                'title'       => 'Question Bank — '.$tenant->name,
                'description' => 'Class-wise question bank downloads from '.$tenant->name,
                'og_type'     => 'website',
            ],
        ]);
    }

    public function view(QuestionBankDocument $questionBankDocument)
    {
        return $this->serve($questionBankDocument, inline: true);
    }

    public function download(QuestionBankDocument $questionBankDocument)
    {
        return $this->serve($questionBankDocument, inline: false);
    }

    private function serve(QuestionBankDocument $questionBankDocument, bool $inline)
    {
        $tenant = $this->resolveTenant();

        abort_if($questionBankDocument->tenant_id !== $tenant->id, 404);

        $questionBankDocument->increment('download_count');

        $filename = $inline ? null : basename($questionBankDocument->file_path);

        return TenantStorage::downloadPrivate($questionBankDocument->file_path, null, $filename, $inline);
    }
}
