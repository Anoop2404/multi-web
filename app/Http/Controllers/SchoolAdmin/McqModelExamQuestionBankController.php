<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\ManagesMcqQuestionBanks;
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
        return $this->inertia(
            $page === 'index' ? 'School/ModelExams/QuestionBanks' : 'School/ModelExams/QuestionBankShow',
            $props,
        );
    }
}
