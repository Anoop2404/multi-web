<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqQuestionBank;
use App\Support\FestClassGroupScheme;

class McqDashboardController extends SahodayaAdminController
{
    public function index()
    {
        $sahodayaId = $this->sahodaya->id;
        $activeStatuses = ['published', 'registration_open', 'ongoing'];

        $allExams = McqExam::where('tenant_id', $sahodayaId)->get();

        $stats = [
            'exams'         => $allExams->count(),
            'active'        => $allExams->whereIn('status', $activeStatuses)->count(),
            'registrations' => (int) McqExam::where('tenant_id', $sahodayaId)->withCount('registrations')->get()->sum('registrations_count'),
            'published'     => $allExams->where('results_published', true)->count(),
        ];

        $recentExams = McqExam::where('tenant_id', $sahodayaId)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (McqExam $exam) => [
                'id'                 => $exam->id,
                'title'              => $exam->title,
                'status'             => $exam->status,
                'status_label'       => \App\Support\Mcq\McqExamLevelLabels::statusLabel($exam->status),
                'level_label'        => \App\Support\Mcq\McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
                'scheduled_at_label' => $exam->scheduled_at?->format('j M Y, g:i A'),
                'has_fee'            => $exam->hasFee(),
                'fee_label'          => \App\Support\Mcq\McqExamLevelLabels::feeLabel($exam->fee_type, $exam->fee_amount),
            ]);

        $seriesCount = \App\Models\McqExamSeries::where('tenant_id', $sahodayaId)->count();

        return $this->inertia('Sahodaya/Mcq/Dashboard', compact('stats', 'recentExams', 'seriesCount'));
    }

    public function questionBanks()
    {
        $banks = McqQuestionBank::where('sahodaya_id', $this->sahodaya->id)
            ->with(['school:id,name', 'teacher:id,name,subject'])
            ->withCount(['questions', 'exams'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (McqQuestionBank $bank) => [
                'id'              => $bank->id,
                'title'           => $bank->title,
                'subject'         => $bank->subject,
                'class_group'     => $bank->class_group,
                'class_group_label' => FestClassGroupScheme::labelsForSahodaya($this->sahodaya->id)[$bank->class_group] ?? $bank->class_group,
                'status'          => $bank->status,
                'school_name'     => $bank->school?->name,
                'teacher_name'    => $bank->teacher?->name,
                'teacher_subject' => $bank->teacher?->subject,
                'questions_count' => $bank->questions_count,
                'exams_count'     => $bank->exams_count,
                'updated_at'      => $bank->updated_at?->format('j M Y'),
            ]);

        $stats = [
            'banks'     => $banks->count(),
            'questions' => $banks->sum('questions_count'),
            'linked'    => $banks->where('exams_count', '>', 0)->count(),
        ];

        return $this->inertia('Sahodaya/Mcq/QuestionBanksHub', compact('banks', 'stats'));
    }
}
