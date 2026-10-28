<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Models\McqGradeMaster;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Mcq\McqCertificateService;
use App\Services\Mcq\McqPaperService;
use App\Services\Mcq\McqResultPublishService;
use App\Services\Mcq\McqSchoolModelExamService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamPayload;
use App\Support\Mcq\McqResultPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * School-owned model exams (conductor_level=school): create, paper, slim enroll, publish, results.
 * Talent Search Sahodaya flows stay on McqController / McqRegistrationController.
 */
class McqModelExamController extends SchoolAdminController
{
    public function __construct(
        Request $request,
        private McqSchoolModelExamService $modelExams,
    ) {
        parent::__construct($request);
    }

    public function index()
    {
        abort_unless(filled($this->school->parent_id), 422, 'This school is not linked to a Sahodaya.');

        $exams = McqExam::where('tenant_id', $this->school->parent_id)
            ->where('conductor_level', 'school')
            ->where('school_id', $this->school->id)
            ->withCount(['registrations' => fn ($q) => $q->active()])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (McqExam $exam) => array_merge($exam->toArray(), [
                'eligibility_summary' => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $this->school->parent_id),
            ]));

        $masterData = app(EffectiveMasterDataResolver::class);

        return $this->inertia('School/ModelExams/Index', [
            'exams' => $exams,
            'stats' => [
                'exams'         => $exams->count(),
                'active'        => $exams->whereIn('status', ['published', 'ongoing'])->count(),
                'registrations' => (int) $exams->sum('registrations_count'),
                'published'     => $exams->where('results_published', true)->count(),
            ],
            'classCategories' => $masterData->classCategories($this->school->parent_id)->values(),
            'masterClasses'   => $masterData->masterClasses($this->school->parent_id)->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'class_category_id' => $c->class_category_id,
                'class_category_label' => $c->classCategory?->label,
            ])->values(),
            'gradeMasters' => McqGradeMaster::where('tenant_id', $this->school->parent_id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('title')
                ->get(['id', 'title', 'is_default']),
            'banksCount' => $this->modelExams->schoolBanksQuery($this->school)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'code'                   => 'nullable|string|max:64',
            'exam_type'              => 'nullable|in:practice,assessment,competitive',
            'scheduled_at'           => 'nullable|date',
            'registration_opens_at'  => 'nullable|date',
            'registration_closes_at' => 'nullable|date|after_or_equal:registration_opens_at',
            'duration_minutes'       => 'nullable|integer|min:5|max:480',
            'pass_mark'              => 'nullable|integer|min:0',
            'eligibility_config'     => 'nullable|array',
            'delivery_mode'          => 'nullable|in:offline,online',
            'grade_master_id'        => 'nullable|integer|exists:mcq_grade_masters,id',
            'shuffle_questions'      => 'nullable|boolean',
            'shuffle_options'        => 'nullable|boolean',
            'multi_marking'          => 'nullable|in:all_or_nothing,partial',
        ]);

        if (! empty($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
            $exists = McqExam::where('tenant_id', $this->school->parent_id)
                ->where('code', $data['code'])
                ->exists();
            if ($exists) {
                return back()->withErrors(['code' => 'An exam with this code already exists.']);
            }
        } else {
            $data['code'] = null;
        }

        if (! empty($data['grade_master_id'])) {
            $master = McqGradeMaster::find($data['grade_master_id']);
            if (! $master || $master->tenant_id !== $this->school->parent_id) {
                return back()->withErrors(['grade_master_id' => 'Invalid grade master.']);
            }
        }

        $exam = $this->modelExams->create($this->school, $data, $request->user()?->id);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.created', "School model exam created: {$exam->title}", [
            'school_id' => $this->school->id,
        ]);

        return redirect("/school-admin/{$this->school->id}/model-exams/{$exam->id}")
            ->with('success', 'Model exam created.');
    }

    public function show(string $tenantId, McqExam $exam, McqPaperService $papers)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->active()
            ->with(['student.schoolClass:id,name', 'mark'])
            ->orderBy('id')
            ->get();

        $masterData = app(EffectiveMasterDataResolver::class);

        return $this->inertia('School/ModelExams/Show', [
            'exam' => array_merge($exam->toArray(), [
                'eligibility_summary' => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $this->school->parent_id),
                'eligibility_config'  => McqExamEligibilityConfig::normalize($exam->eligibility_config),
                'has_fee'             => $exam->hasFee(),
                'paper'               => $papers->summary($exam),
                'settings'            => [
                    'shuffle_questions' => (bool) ($exam->settings_json['shuffle_questions'] ?? true),
                    'shuffle_options'   => (bool) ($exam->settings_json['shuffle_options'] ?? true),
                    'multi_marking'     => $exam->multiMarkingMode(),
                ],
                'result_release' => \App\Support\Mcq\McqResultReleaseSettings::forInertia($exam),
            ]),
            'certificateTemplates' => \App\Models\McqCertificateTemplate::where('tenant_id', $this->school->parent_id)
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'title', 'is_default']),
            'gradeBands' => app(\App\Services\Mcq\McqGradeService::class)->bandsForExam($exam),
            'registrationCounts' => [
                'total'     => $registrations->count(),
                'started'   => $registrations->whereNotNull('started_at')->count(),
                'submitted' => $registrations->where('status', 'submitted')->count(),
                'marked'    => $registrations->filter(fn ($r) => $r->mark?->score !== null)->count(),
            ],
            'registrations' => $registrations->take(100)->map(fn (McqRegistration $reg) => [
                'id'         => $reg->id,
                'status'     => $reg->status,
                'started_at' => $reg->started_at?->toIso8601String(),
                'submitted_at' => $reg->submitted_at?->toIso8601String(),
                'student'    => $reg->student ? [
                    'id'         => $reg->student->id,
                    'name'       => $reg->student->name,
                    'class_name' => $reg->student->schoolClass?->name,
                ] : null,
                'mark' => $exam->results_published ? McqResultPresenter::forRegistration($reg, $reg->mark) : null,
            ]),
            'classCategories' => $masterData->classCategories($this->school->parent_id)->values(),
            'masterClasses'   => $masterData->masterClasses($this->school->parent_id)->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'class_category_id' => $c->class_category_id,
                'class_category_label' => $c->classCategory?->label,
            ])->values(),
            'gradeMasters' => McqGradeMaster::where('tenant_id', $this->school->parent_id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('title')
                ->get(['id', 'title', 'is_default']),
            'schoolClasses' => SchoolClass::where('tenant_id', $this->school->id)->orderBy('name')->get(['id', 'name', 'class_category_id']),
        ]);
    }

    public function update(Request $request, string $tenantId, McqExam $exam)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);

        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'code'                   => 'nullable|string|max:64',
            'exam_type'              => 'nullable|in:practice,assessment,competitive',
            'status'                 => 'required|in:draft,published,ongoing,completed,cancelled',
            'scheduled_at'           => 'nullable|date',
            'registration_opens_at'  => 'nullable|date',
            'registration_closes_at' => 'nullable|date|after_or_equal:registration_opens_at',
            'duration_minutes'       => 'nullable|integer|min:5|max:480',
            'pass_mark'              => 'nullable|integer|min:0',
            'eligibility_config'     => 'nullable|array',
            'delivery_mode'          => 'nullable|in:offline,online',
            'grade_master_id'        => 'nullable|integer|exists:mcq_grade_masters,id',
            'shuffle_questions'      => 'nullable|boolean',
            'shuffle_options'        => 'nullable|boolean',
            'multi_marking'          => 'nullable|in:all_or_nothing,partial',
            ...\App\Support\Mcq\McqResultReleaseSettings::validationRules(),
        ]);

        if (! empty($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
            $exists = McqExam::where('tenant_id', $this->school->parent_id)
                ->where('code', $data['code'])
                ->where('id', '!=', $exam->id)
                ->exists();
            if ($exists) {
                return back()->withErrors(['code' => 'An exam with this code already exists.']);
            }
        } else {
            $data['code'] = null;
        }

        $data = McqExamPayload::applyDefaults($data);
        if ($error = McqExamPayload::eligibilityError($data)) {
            return back()->withErrors(['eligibility_config' => $error]);
        }

        // School model exams stay fee-free and hall-ticket free.
        $data['fee_type'] = 'none';
        $data['fee_amount'] = null;
        $data['conductor_level'] = 'school';
        $data['school_id'] = $this->school->id;

        $releasePatch = \App\Support\Mcq\McqResultReleaseSettings::normalizeInput($data, $exam);
        if (array_key_exists('certificate_template_id', $data)) {
            $templateId = $data['certificate_template_id'] ?: null;
            if ($templateId) {
                $ok = \App\Models\McqCertificateTemplate::where('tenant_id', $this->school->parent_id)
                    ->whereKey($templateId)->where('is_active', true)->exists();
                if (! $ok) {
                    return back()->withErrors(['certificate_template_id' => 'Invalid certificate template.']);
                }
            }
            $data['certificate_template_id'] = $templateId;
        }

        $data['settings_json'] = array_merge($exam->settings_json ?? [], [
            'requires_hall_ticket' => false,
            'product_mode'         => 'school_model_exam',
            'shuffle_questions'    => (bool) ($data['shuffle_questions'] ?? $exam->settings_json['shuffle_questions'] ?? true),
            'shuffle_options'      => (bool) ($data['shuffle_options'] ?? $exam->settings_json['shuffle_options'] ?? true),
            'multi_marking'        => $data['multi_marking'] ?? $exam->multiMarkingMode(),
            'result_release'       => $releasePatch['result_release'],
            'certificates'         => $releasePatch['certificates'],
        ]);
        unset(
            $data['shuffle_questions'], $data['shuffle_options'], $data['multi_marking'],
            $data['result_visibility'], $data['results_auto_release_at'],
            $data['certificates_enabled'], $data['certificates_auto_issue'],
            $data['certificate_eligibility'], $data['certificate_eligible_grades'],
        );

        $exam->update($data);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.updated', "School model exam updated: {$exam->title}", [
            'school_id' => $this->school->id,
            'status'    => $exam->status,
        ]);

        return back()->with('success', 'Exam saved.');
    }

    public function publish(Request $request, string $tenantId, McqExam $exam)
    {
        $autoEnroll = $request->boolean('auto_enroll', true);
        $this->modelExams->publish($exam, $this->school, $autoEnroll);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.exam.published', "School model exam published: {$exam->title}", [
            'school_id'   => $this->school->id,
            'auto_enroll' => $autoEnroll,
        ]);

        return back()->with('success', $autoEnroll
            ? 'Exam published and eligible students enrolled.'
            : 'Exam published.');
    }

    public function enrollEligible(string $tenantId, McqExam $exam)
    {
        $result = $this->modelExams->enrollEligible($exam, $this->school);

        return back()->with('success', "Enrolled {$result['created']} student(s) ({$result['skipped']} already enrolled; {$result['total_eligible']} eligible).");
    }

    public function paper(string $tenantId, McqExam $exam, McqPaperService $papers)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);

        $linkedIds = $exam->questionBanks()->pluck('mcq_question_banks.id');
        $availableQuery = $this->modelExams->schoolBanksQuery($this->school)
            ->where('status', 'active')
            ->whereNotIn('id', $linkedIds);
        \App\Support\Mcq\McqQuestionBankScope::constrainQueryToExam($availableQuery, $exam);
        $available = $availableQuery
            ->with(['classCategory:id,label', 'masterClass:id,name'])
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        return $this->inertia('School/ModelExams/Paper', [
            'exam'      => array_merge($exam->only('id', 'title', 'delivery_mode', 'results_published', 'total_questions', 'status'), [
                'eligibility_filter' => \App\Support\Mcq\McqQuestionBankScope::examFilterMeta($exam),
            ]),
            'paper'     => $papers->summary($exam),
            'available' => $available->map(fn (McqQuestionBank $bank) => [
                'id' => $bank->id,
                'title' => $bank->title,
                'subject' => $bank->subject,
                'class_group' => $bank->class_group,
                'questions_count' => $bank->questions_count,
                'scope_label' => \App\Support\Mcq\McqQuestionBankScope::label($bank) ?? 'Any class',
            ]),
            'settings'  => [
                'shuffle_questions' => (bool) ($exam->settings_json['shuffle_questions'] ?? true),
                'shuffle_options'   => (bool) ($exam->settings_json['shuffle_options'] ?? true),
                'multi_marking'     => $exam->multiMarkingMode(),
            ],
            'banksUrl'  => "/school-admin/{$this->school->id}/model-exams/question-banks",
        ]);
    }

    public function attachBank(Request $request, string $tenantId, McqExam $exam)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $data = $request->validate(['bank_id' => 'required|integer']);
        $bank = $this->modelExams->schoolBanksQuery($this->school)->findOrFail($data['bank_id']);
        if ($bank->status !== 'active') {
            return back()->with('error', 'Only banks marked Ready can be added to a paper.');
        }
        if (! \App\Support\Mcq\McqQuestionBankScope::matchesExam($bank, $exam)) {
            return back()->with('error', 'That bank’s class/category does not match this exam’s eligibility.');
        }

        $nextOrder = ((int) DB::table('mcq_exam_question_banks')->where('exam_id', $exam->id)->max('display_order')) + 1;
        $exam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => $nextOrder]]);

        return back()->with('success', "Added \"{$bank->title}\" to the paper.");
    }

    public function updateSection(Request $request, string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        $this->modelExams->assertBankOwnedBySchool($bank, $this->school);
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }
        abort_unless($exam->questionBanks()->whereKey($bank->id)->exists(), 404);

        $data = $request->validate([
            'section_title' => 'nullable|string|max:120',
            'pick_mode'     => 'required|in:'.implode(',', McqPaperService::PICK_MODES),
            'pick_count'    => 'nullable|required_unless:pick_mode,all|integer|min:1|max:1000',
        ]);
        if ($data['pick_mode'] === 'all') {
            $data['pick_count'] = null;
        }

        $exam->questionBanks()->updateExistingPivot($bank->id, $data);

        return back()->with('success', 'Section saved.');
    }

    public function detachBank(string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        $this->modelExams->assertBankOwnedBySchool($bank, $this->school);
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }
        $exam->questionBanks()->detach($bank->id);

        return back()->with('success', 'Section removed from the paper.');
    }

    public function moveSection(Request $request, string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        $this->modelExams->assertBankOwnedBySchool($bank, $this->school);
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $ids = $exam->questionBanks()->pluck('mcq_question_banks.id')->values()->all();
        $index = array_search($bank->id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            DB::transaction(function () use ($exam, $ids) {
                foreach ($ids as $position => $id) {
                    $exam->questionBanks()->updateExistingPivot($id, ['display_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    public function updatePaperSettings(Request $request, string $tenantId, McqExam $exam)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $data = $request->validate([
            'shuffle_questions' => 'required|boolean',
            'shuffle_options'   => 'sometimes|boolean',
            'multi_marking'     => 'required|in:all_or_nothing,partial',
        ]);

        $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], [
            'shuffle_questions' => (bool) $data['shuffle_questions'],
            'shuffle_options'   => (bool) ($data['shuffle_options'] ?? $exam->settings_json['shuffle_options'] ?? true),
            'multi_marking'     => $data['multi_marking'],
        ])]);

        return back()->with('success', 'Paper settings saved.');
    }

    public function publishResults(string $tenantId, McqExam $exam, McqResultPublishService $publisher)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);

        $result = $publisher->publish($exam, 'School model exam');
        $msg = "Results published. {$result['ranked']} student(s) ranked.";
        if ($result['certificates_issued'] > 0) {
            $msg .= " {$result['certificates_issued']} certificate(s) issued.";
        }

        return back()->with('success', $msg);
    }

    public function unpublishResults(Request $request, string $tenantId, McqExam $exam, McqResultPublishService $publisher)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        $publisher->unpublish($exam, 'School model exam');

        return back()->with('success', 'Results hidden. Publish again when ready.');
    }

    public function generateCertificates(string $tenantId, McqExam $exam, McqCertificateService $certificates)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        abort_unless($exam->results_published, 422, 'Release results before generating certificates.');
        abort_unless(\App\Support\Mcq\McqResultReleaseSettings::certificatesEnabled($exam), 422, 'Enable certificates in result settings first.');

        $count = $certificates->issueBulk($exam);

        app(PlatformAuditLogger::class)->mcq($exam, 'mcq.certificates.generated', "Generated {$count} school model exam certificate(s) for {$exam->title}", [
            'school_id' => $this->school->id,
        ]);

        return back()->with('success', "{$count} certificate(s) generated.");
    }

    public function previewCertificate(string $tenantId, McqExam $exam)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        $service = app(McqCertificateService::class);

        return view('mcq.certificate', [
            'registration' => null,
            'certificate'  => (object) ['verification_uuid' => 'SAMPLE-DEMO-0000'],
            'sahodaya'     => \App\Models\Tenant::find($this->school->parent_id),
            'fields'       => $service->previewSampleFields($exam, \App\Models\Tenant::find($this->school->parent_id)),
            'design'       => $service->previewDesign($exam),
        ]);
    }

    public function printCertificate(string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        $this->modelExams->assertOwnedBySchool($exam, $this->school);
        abort_if($registration->exam_id !== $exam->id || $registration->school_id !== $this->school->id, 404);

        $certificate = app(McqCertificateService::class)->issue($registration->load(['exam', 'student', 'school', 'mark']));
        $sahodaya = \App\Models\Tenant::find($this->school->parent_id);

        return view('mcq.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $sahodaya,
            'fields'       => app(McqCertificateService::class)->fieldValues($registration, $sahodaya),
            'design'       => $certificate->design_snapshot_json ?? [],
        ]);
    }

    private function rejectIfPaperLocked(McqExam $exam)
    {
        return McqRegistration::where('exam_id', $exam->id)->whereNotNull('started_at')->exists()
            ? back()->with('error', 'Students have started this exam, so its paper can no longer be changed.')
            : null;
    }
}
