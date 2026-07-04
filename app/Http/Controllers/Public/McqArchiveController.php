<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\McqExam;
use App\Support\TenantStorage;

class McqArchiveController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $papers = McqExam::where('tenant_id', $tenant->id)
            ->whereNotNull('question_paper_path')
            ->orderByDesc('scheduled_at')
            ->get(['id', 'title', 'exam_type', 'scheduled_at', 'question_paper_label', 'question_paper_path']);

        return $this->renderPublic('public.mcq.archive', $tenant, [
            'papers'  => $papers,
            'pageSeo' => ['title' => 'Question Papers — '.$tenant->name],
        ]);
    }

    public function download(int $examId)
    {
        $tenant = $this->resolveTenant();

        $exam = McqExam::where('tenant_id', $tenant->id)
            ->where('id', $examId)
            ->whereNotNull('question_paper_path')
            ->firstOrFail();

        return TenantStorage::downloadResponse($tenant, $exam->question_paper_path);
    }
}
