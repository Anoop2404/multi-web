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

        $documents = $documents->map(function ($document) use ($masterClasses) {
            $document->setAttribute('class_name', $masterClasses->get($document->master_class_id)?->name);

            return $document;
        })->values();

        return $this->renderPublic('public.question-bank.index', $tenant, [
            'documents' => $documents,
            'pageSeo' => [
                'title'       => 'Question Bank — '.$tenant->name,
                'description' => 'Question bank downloads from '.$tenant->name,
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
