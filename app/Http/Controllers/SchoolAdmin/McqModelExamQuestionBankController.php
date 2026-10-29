<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\ManagesMcqQuestionBanks;
use App\Models\McqQuestionBank;
use App\Services\Mcq\McqQuestionBankCopyService;
use App\Support\Mcq\McqQuestionBankScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** School-scoped question banks for in-school model exams. */
class McqModelExamQuestionBankController extends SchoolAdminController
{
    use ManagesMcqQuestionBanks;

    protected function bankSahodayaId(Request $request): string
    {
        abort_unless(filled($this->school->parent_id), 422, 'This school is not linked to a Sahodaya.');

        return $this->school->parent_id;
    }

    protected function bankSchoolId(Request $request): ?string
    {
        return $this->school->id;
    }

    protected function bankBaseUrl(Request $request): string
    {
        return "/school-admin/{$this->school->id}/model-exams/question-banks";
    }

    protected function renderBankPage(Request $request, string $page, array $props)
    {
        if ($page === 'index') {
            $sahodayaId = $this->bankSahodayaId($request);
            $associationBanks = McqQuestionBank::where('sahodaya_id', $sahodayaId)
                ->whereNull('school_id')
                ->where('status', 'active')
                ->with(['classCategory:id,label', 'masterClass:id,name', 'contributedBySchool:id,name'])
                ->withCount('questions')
                ->orderBy('title')
                ->limit(100)
                ->get()
                ->map(fn (McqQuestionBank $bank) => [
                    'id' => $bank->id,
                    'title' => $bank->title,
                    'subject' => $bank->subject,
                    'questions_count' => $bank->questions_count,
                    'scope_label' => McqQuestionBankScope::label($bank) ?? 'Any class',
                    'from_school' => $bank->contributedBySchool?->name,
                ]);
            $props['associationBanks'] = $associationBanks;
        }

        return $this->inertia(
            $page === 'index' ? 'School/ModelExams/QuestionBanks' : 'School/ModelExams/QuestionBankShow',
            $props,
        );
    }

    /** Copy a Sahodaya association Ready bank into this school's private library. */
    public function copyAssociationBank(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestionBankCopyService $copies): RedirectResponse
    {
        abort_unless($bank->sahodaya_id === $this->school->parent_id, 404);
        abort_unless($bank->school_id === null, 404);

        $copy = $copies->copyIntoSchool($bank, $this->school, (int) $request->user()->id);

        return redirect($this->bankBaseUrl($request).'/'.$copy->id)
            ->with('success', 'Copied into your school banks as a draft. Review questions, then mark Ready.');
    }

    /** Contribute a school Ready bank as a Sahodaya-owned association copy. */
    public function contributeBank(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestionBankCopyService $copies): RedirectResponse
    {
        abort_unless($bank->sahodaya_id === $this->school->parent_id, 404);
        abort_unless($bank->school_id === $this->school->id, 404);

        $copy = $copies->contributeToSahodaya($bank, $this->school, (int) $request->user()->id);

        return back()->with('success', "Contributed a Sahodaya copy as draft \"{$copy->title}\". Sahodaya can mark it Ready for association papers.");
    }
}
