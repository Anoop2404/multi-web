<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\{McqExam, McqPaperTemplate, McqQuestionBank};
use App\Services\Mcq\McqPaperTemplateService;
use Illuminate\Http\Request;

class McqPaperTemplateController extends SahodayaAdminController
{
    public function store(Request $request, string $tenantId, McqExam $exam, McqPaperTemplateService $service)
    {
        $this->authorizeExam($exam);
        $service->save($exam->tenant_id, $this->templateData($request));
        return back()->with('success', 'Paper template saved. Assign this version to classes below.');
    }

    public function update(Request $request, string $tenantId, McqExam $exam, McqPaperTemplate $template, McqPaperTemplateService $service)
    {
        $this->authorizeExam($exam);
        $service->save($exam->tenant_id, $this->templateData($request), $template);
        return back()->with('success', 'New template version saved. Existing class assignments retain their previous version until reassigned.');
    }

    public function archive(string $tenantId, McqExam $exam, McqPaperTemplate $template)
    {
        $this->authorizeExam($exam);
        abort_unless($template->tenant_id === $exam->tenant_id, 403);
        $template->update(['is_active' => false]);
        return back()->with('success', 'Template archived. Existing assignments and attempts are retained.');
    }

    public function assign(Request $request, string $tenantId, McqExam $exam, McqPaperTemplateService $service)
    {
        $this->authorizeExam($exam);
        $data = $request->validate([
            'fallback' => 'required|in:require,legacy',
            'assignments' => 'present|array|max:100',
            'assignments.*.template_id' => 'required|integer',
            'assignments.*.classes' => 'required|array|min:1|max:100',
            'assignments.*.classes.*' => 'required|string|max:100',
            'assignments.*.total_marks' => 'nullable|numeric|min:0.01|max:100000',
            'assignments.*.pass_mark' => 'nullable|numeric|min:0|max:100',
            'assignments.*.shuffle_questions' => 'required|in:none,all,sections',
            'assignments.*.shuffle_options' => 'required|boolean',
        ]);
        $service->assign($exam, $data);
        return back()->with('success', 'Class papers saved. Unassigned classes follow the fallback rule you selected.');
    }

    public function bank(string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        $this->authorizeExam($exam);
        abort_unless($bank->sahodaya_id === $exam->tenant_id, 404);
        return response()->json($bank->questions()->get()->map(fn ($q) => [
            'id' => $q->id, 'body_text' => $q->body_text, 'type' => $q->typeValue(),
            'marks' => $q->marksValue(), 'negative_mark' => $q->negativeMarkValue(), 'answer' => $q->answerKeyLabel(),
        ]));
    }

    private function authorizeExam(McqExam $exam): void
    {
        abort_unless($exam->tenant_id === $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
    }

    private function templateData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:160',
            'sections' => 'required|array|min:1|max:50',
            'sections.*.bank_id' => 'required|integer',
            'sections.*.title' => 'nullable|string|max:120',
            'sections.*.pick_mode' => 'required|in:all,first,random,manual',
            'sections.*.pick_count' => 'nullable|integer|min:1|max:1000',
            'sections.*.question_ids' => 'nullable|array|max:1000',
            'sections.*.question_ids.*' => 'integer|distinct',
            'sections.*.marks' => 'nullable|numeric|min:0.01|max:1000',
            'sections.*.negative_mark' => 'nullable|numeric|min:0|max:1000',
        ]);
    }
}
