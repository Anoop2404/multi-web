<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqExam;
use App\Models\McqExamSeries;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqSeriesPromotionService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamPayload;
use App\Support\Mcq\McqExamLevelLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class McqExamSeriesController extends SahodayaAdminController
{
    public function index()
    {
        $series = McqExamSeries::where('tenant_id', $this->sahodaya->id)
            ->with(['exams' => fn ($q) => $q->orderBy('exam_level')])
            ->orderByDesc('id')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Series/Index', [
            'series' => $series,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
        ]);

        McqExamSeries::create([
            'tenant_id'        => $this->sahodaya->id,
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'academic_year_id' => AcademicYear::activeRecord()?->id,
            'status'           => 'draft',
        ]);

        return back()->with('success', 'Talent Search series created.');
    }

    public function show(string $tenantId, McqExamSeries $series)
    {
        abort_if($series->tenant_id !== $this->sahodaya->id, 404);

        $series->load(['exams' => fn ($q) => $q->orderBy('exam_level')]);

        $exams = $series->exams->map(fn (McqExam $exam) => array_merge($exam->toArray(), [
            'level_label'         => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
            'eligibility_summary' => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $this->sahodaya->id),
            'eligibility_mode_label' => McqExamLevelLabels::eligibilityModeLabel(
                $exam->eligibility_mode,
                $exam->cutoff_score !== null ? (float) $exam->cutoff_score : null,
                $exam->top_rank_count,
            ),
            'exam_url' => "/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}",
            'promotion' => (int) ($exam->exam_level ?? 1) > 1
                ? app(McqSeriesPromotionService::class)->promotionSummary($exam)
                : null,
        ]));

        $parentExams = McqExam::where('tenant_id', $this->sahodaya->id)
            ->where('status', 'completed')
            ->where('results_published', true)
            ->orderByDesc('scheduled_at')
            ->get(['id', 'title', 'exam_level', 'series_id']);

        $masterData = app(EffectiveMasterDataResolver::class);

        return $this->inertia('Sahodaya/Mcq/Series/Show', [
            'series'          => array_merge($series->toArray(), ['exams' => $exams]),
            'parentExams'     => $parentExams,
            'classCategories' => $masterData->classCategories($this->sahodaya->id)->values(),
            'masterClasses'   => $masterData->masterClasses($this->sahodaya->id)->map(fn ($c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'class_category_id'   => $c->class_category_id,
                'class_category_label'=> $c->classCategory?->label,
            ])->values(),
        ]);
    }

    public function storeLevel(Request $request, string $tenantId, McqExamSeries $series, McqEligibilityService $eligibility)
    {
        abort_if($series->tenant_id !== $this->sahodaya->id, 404);

        $data = $this->validateLevel($request);

        if ((int) $data['exam_level'] > 1 && empty($data['parent_exam_id'])) {
            return back()->withErrors(['parent_exam_id' => 'Parent exam is required for level 2+.']);
        }

        if ($error = McqExamPayload::eligibilityError($data)) {
            return back()->withErrors(['eligibility_config' => $error]);
        }

        $exam = $this->createSeriesExam($series, $data);

        $previewCount = $eligibility->previewEligibleCount($exam);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}")
            ->with('success', "Level {$exam->exam_level} exam created. {$previewCount} students currently eligible for promotion.");
    }

    public function storeLevelsBulk(Request $request, string $tenantId, McqExamSeries $series)
    {
        abort_if($series->tenant_id !== $this->sahodaya->id, 404);

        $data = $request->validate([
            'levels'                         => 'required|array|min:1|max:5',
            'levels.*.exam_level'            => 'required|integer|min:1|max:10',
            'levels.*.title'                 => 'required|string|max:255',
            'levels.*.scheduled_at'          => 'nullable|date',
            'levels.*.duration_minutes'      => 'nullable|integer|min:5|max:480',
            'levels.*.fee_amount'            => 'nullable|numeric|min:0',
            'levels.*.school_discount_amount'=> 'nullable|numeric|min:0',
            'levels.*.eligibility_mode'      => 'nullable|in:open,cutoff_marks,top_rank,manual',
            'levels.*.cutoff_score'          => 'nullable|numeric|min:0',
            'levels.*.top_rank_count'        => 'nullable|integer|min:1',
            'levels.*.eligibility_config'    => 'nullable|array',
            'levels.*.delivery_mode'         => 'nullable|in:offline,online',
            'shared_eligibility_config'      => 'nullable|array',
        ]);

        $created = DB::transaction(function () use ($series, $data) {
            $sharedConfig = McqExamEligibilityConfig::normalize($data['shared_eligibility_config'] ?? null);
            $parentId = null;
            $exams = [];

            foreach (collect($data['levels'])->sortBy('exam_level')->values() as $levelData) {
                $level = (int) $levelData['exam_level'];
                $payload = array_merge($levelData, [
                    'exam_level'         => $level,
                    'parent_exam_id'     => $level > 1 ? $parentId : null,
                    'eligibility_config' => $levelData['eligibility_config'] ?? $sharedConfig,
                    'eligibility_mode'   => $level > 1
                        ? ($levelData['eligibility_mode'] ?? 'cutoff_marks')
                        : 'open',
                ]);

                if ($level > 1 && empty($payload['parent_exam_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'levels' => 'Level 1 must be created before higher levels in the same batch.',
                    ]);
                }

                $payload = McqExamPayload::applyDefaults($payload);

                if ($error = McqExamPayload::eligibilityError($payload)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'shared_eligibility_config' => $error,
                    ]);
                }

                $exam = $this->createSeriesExam($series, $payload);
                $parentId = $exam->id;
                $exams[] = $exam;
            }

            return $exams;
        });

        $first = $created[0];

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$first->id}")
            ->with('success', count($created).' level exam(s) created. Open each level from the series page.');
    }

    public function lockPromotion(string $tenantId, McqExamSeries $series, McqExam $exam, McqSeriesPromotionService $promotion)
    {
        abort_if($series->tenant_id !== $this->sahodaya->id, 404);
        abort_if($exam->series_id !== $series->id, 404);

        $promotion->lockPromotionList($exam, request()->user()?->id);

        return back()->with('success', 'Promotion list locked. Only listed students can register for '.$exam->title.'.');
    }

    /** @return array<string, mixed> */
    private function validateLevel(Request $request): array
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'exam_level'         => 'required|integer|min:1|max:10',
            'parent_exam_id'     => 'nullable|exists:mcq_exams,id',
            'eligibility_mode'   => 'required|in:open,cutoff_marks,top_rank,manual',
            'cutoff_score'       => 'nullable|numeric|min:0',
            'top_rank_count'     => 'nullable|integer|min:1',
            'scheduled_at'       => 'nullable|date',
            'duration_minutes'   => 'nullable|integer|min:5|max:480',
            'fee_amount'         => 'nullable|numeric|min:0',
            'school_discount_amount' => 'nullable|numeric|min:0',
            'eligibility_config' => 'nullable|array',
            'delivery_mode'      => 'nullable|in:offline,online',
        ]);

        return McqExamPayload::applyDefaults($data);
    }

    /** @param  array<string, mixed>  $data */
    private function createSeriesExam(McqExamSeries $series, array $data): McqExam
    {
        $fee = (float) ($data['fee_amount'] ?? 0);
        $discount = (float) ($data['school_discount_amount'] ?? 0);
        if ($discount > $fee && $fee > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'school_discount_amount' => 'School discount cannot exceed the student fee.',
            ]);
        }

        return McqExam::create([
            'tenant_id'          => $this->sahodaya->id,
            'series_id'          => $series->id,
            'academic_year_id'   => $series->academic_year_id,
            'title'              => $data['title'],
            'exam_level'         => (int) $data['exam_level'],
            'parent_exam_id'     => $data['parent_exam_id'] ?? null,
            'eligibility_mode'   => $data['eligibility_mode'] ?? 'open',
            'cutoff_score'       => $data['cutoff_score'] ?? null,
            'top_rank_count'     => $data['top_rank_count'] ?? null,
            'scheduled_at'       => $data['scheduled_at'] ?? null,
            'duration_minutes'   => McqExamPayload::durationMinutes($data['duration_minutes'] ?? null),
            'fee_amount'         => $fee > 0 ? $fee : null,
            'school_discount_amount' => $fee > 0 && $discount > 0 ? $discount : null,
            'fee_type'           => $fee > 0 ? 'flat' : 'none',
            'delivery_mode'      => $data['delivery_mode'] ?? 'offline',
            'eligibility_config' => McqExamEligibilityConfig::normalize($data['eligibility_config'] ?? null),
            'status'             => 'draft',
            'exam_type'          => 'competitive',
            'conductor_level'    => 'sahodaya',
            'next_hall_ticket_no'=> 100,
        ]);
    }
}
