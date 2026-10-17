<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\ManagesMcqQuestionBanks;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Question bank authoring for exam controllers in the exam portal. Exam staff can run
 * attendance and supervision but never see question banks or answer keys.
 */
class ExamQuestionBankController extends Controller
{
    use ManagesMcqQuestionBanks;

    protected function bankSahodayaId(Request $request): string
    {
        $user = $request->user();
        $tenantId = (string) $request->route('tenantId');

        // EnsureExamPortal already ties the route tenant to the user; checked again here so
        // answer keys stay scoped even if these routes ever move out of that middleware group.
        abort_unless(
            $user && ($user->isSuperAdmin()
                || ($user->tenant_id === $tenantId && $user->hasAnyRole(['exam_controller', 'sahodaya_admin']))),
            403,
            'Only exam controllers can manage question banks.',
        );

        return $tenantId;
    }

    protected function bankBaseUrl(Request $request): string
    {
        return '/portal/exam/'.$request->route('tenantId').'/question-banks';
    }

    protected function renderBankPage(Request $request, string $page, array $props)
    {
        return inertia($page === 'index' ? 'Portal/Exam/QuestionBanks' : 'Portal/Exam/QuestionBankShow', $props + [
            'sahodaya' => Tenant::findOrFail($request->route('tenantId'))->only('id', 'name'),
        ]);
    }
}
