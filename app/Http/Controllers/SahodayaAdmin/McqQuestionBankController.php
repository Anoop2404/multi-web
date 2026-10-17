<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Concerns\ManagesMcqQuestionBanks;
use Illuminate\Http\Request;

/** Question bank authoring in the Sahodaya admin panel (Talent Search → Question banks). */
class McqQuestionBankController extends SahodayaAdminController
{
    use ManagesMcqQuestionBanks;

    public function __construct(Request $request)
    {
        parent::__construct($request);

        // Banks hold answer keys, so staff need the MCQ permission even to view them.
        $this->assertStaffCan('mcq.manage');
    }

    protected function bankSahodayaId(Request $request): string
    {
        return $this->sahodaya->id;
    }

    protected function bankBaseUrl(Request $request): string
    {
        return "/sahodaya-admin/{$this->sahodaya->id}/mcq/question-banks";
    }

    protected function renderBankPage(Request $request, string $page, array $props)
    {
        return $this->inertia($page === 'index' ? 'Sahodaya/Mcq/QuestionBanksHub' : 'Sahodaya/Mcq/QuestionBankShow', $props);
    }
}
