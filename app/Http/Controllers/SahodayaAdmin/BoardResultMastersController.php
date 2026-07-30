<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\ApiConfig;
use App\Models\BoardResultMarksConfig;
use App\Models\ExamStream;
use App\Models\Subject;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use App\Services\BoardResults\BoardResultMarksConfigService;
use App\Support\CbseSubjectCodes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardResultMastersController extends SahodayaAdminController
{
    public function index()
    {
        $streams = ExamStream::query()
            ->forSahodaya($this->sahodaya->id)
            ->orderByRaw('sahodaya_id is null')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $apiConfig = ApiConfig::forSahodaya($this->sahodaya->id);

        // Subject master (Category I/II/III) for the "add subject to stream" picker on this
        // page — lets an admin assign existing subjects instead of retyping labels, while
        // still allowing free-text entry for one-off school-specific subjects.
        $hasCategory = \Illuminate\Support\Facades\Schema::hasColumn('subjects', 'category');

        $subjectsQuery = Subject::query()
            ->forSahodaya($this->sahodaya->id)
            ->active()
            ->orderByRaw('sahodaya_id is null desc');

        if ($hasCategory) {
            $subjectsQuery->orderBy('category');
        }

        $subjects = $subjectsQuery->orderBy('sort_order')
            ->get($hasCategory ? ['id', 'code', 'label', 'category'] : ['id', 'code', 'label'])
            ->groupBy(fn (Subject $s) => ($hasCategory ? $s->category : null) ?? 'other')
            ->map(fn ($group) => $group->values())
            ->all();

        $allBoardSubjects = Subject::query()
            ->forSahodaya($this->sahodaya->id)
            ->orderBy('label')
            ->get();

        $marksConfigs = BoardResultMarksConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->get();

        return $this->inertia('Sahodaya/BoardResults/Masters', [
            'streams' => $streams,
            'subjectsByCategory' => $subjects,
            'allBoardSubjects' => $allBoardSubjects,
            'standardCbses' => \App\Support\BoardExamSubjects::standardBoardSubjects(),
            'apiConfig' => $apiConfig->only([
                'id', 'weight_pass_percent', 'weight_distinctions',
                'weight_highest_mark', 'weight_toppers', 'is_active',
            ]),
            'topperConfigs' => TopperCountConfig::query()
                ->where('sahodaya_id', $this->sahodaya->id)
                ->orderBy('class')
                ->get(),
            'classXTotalMarks' => $marksConfigs->firstWhere('class', 10)?->total_marks
                ?? BoardResultMarksConfig::DEFAULT_TOTAL_MARKS,
            'streamTotalMarks' => $marksConfigs->where('class', 12)
                ->whereNotNull('stream_id')
                ->pluck('total_marks', 'stream_id'),
        ]);
    }

    public function updateMarksConfig(Request $request, BoardResultMarksConfigService $marksConfig)
    {
        $data = $request->validate([
            'class_x_total_marks' => 'required|integer|min:1|max:5000',
            'streams' => 'nullable|array',
            'streams.*.stream_id' => 'required|integer',
            'streams.*.total_marks' => 'required|integer|min:1|max:5000',
        ]);

        $marksConfig->upsert($this->sahodaya->id, 10, null, $data['class_x_total_marks']);

        foreach ($data['streams'] ?? [] as $row) {
            $marksConfig->upsert($this->sahodaya->id, 12, (int) $row['stream_id'], (int) $row['total_marks']);
        }

        return back()->with('success', 'Marks settings saved — schools will use these locked totals for new entries.');
    }

    public function storeSubject(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'code' => 'nullable|string|max:40',
            'category' => 'nullable|string|max:40',
        ]);

        // Prefer the real official CBSE code for a known Class XII subject over an
        // arbitrary auto-derived slug, so schools see the code they actually recognize.
        $code = $data['code']
            ?? CbseSubjectCodes::forClass12Label($data['label'])
            ?? preg_replace('/[^A-Za-z0-9]/', '', $data['label']);
        $code = strtoupper(trim($code));

        $hasCategory = \Illuminate\Support\Facades\Schema::hasColumn('subjects', 'category');

        $attributes = [
            'sahodaya_id' => $this->sahodaya->id,
            'label' => trim($data['label']),
            'code' => $code,
            'is_active' => true,
        ];

        if ($hasCategory) {
            $attributes['category'] = $data['category'] ?? 'language';
        }

        Subject::create($attributes);

        return back()->with('success', 'Subject created successfully.');
    }

    public function updateSubject(Request $request, Subject $subject)
    {
        abort_if($subject->sahodaya_id !== $this->sahodaya->id && $subject->sahodaya_id !== null, 403);

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'code' => 'nullable|string|max:40',
            'category' => 'nullable|string|max:40',
            'is_active' => 'nullable|boolean',
        ]);

        $hasCategory = \Illuminate\Support\Facades\Schema::hasColumn('subjects', 'category');

        $updateData = [
            'label' => trim($data['label']),
            'code' => strtoupper(trim($data['code'] ?? $subject->code)),
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($hasCategory) {
            $updateData['category'] = $data['category'] ?? $subject->category;
        }

        $subject->update($updateData);

        return back()->with('success', 'Subject updated successfully.');
    }

    public function destroySubject(Subject $subject)
    {
        abort_if($subject->sahodaya_id !== $this->sahodaya->id && $subject->sahodaya_id !== null, 403);
        $subject->delete();

        return back()->with('success', 'Subject removed.');
    }

    public function seedStandardSubjects()
    {
        $standards = \App\Support\BoardExamSubjects::standardBoardSubjects();
        $count = 0;
        $hasCategory = \Illuminate\Support\Facades\Schema::hasColumn('subjects', 'category');

        foreach ($standards as $index => $label) {
            // Real CBSE code when we have one on file (Class XII), otherwise fall back
            // to the old auto-derived slug for anything not in that list.
            $code = CbseSubjectCodes::forClass12Label($label)
                ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $label), 0, 10));
            $defaults = [
                'code' => $code,
                'is_active' => true,
                'sort_order' => $index + 1,
            ];
            if ($hasCategory) {
                $defaults['category'] = 'language';
            }

            $created = Subject::firstOrCreate([
                'sahodaya_id' => $this->sahodaya->id,
                'label' => $label,
            ], $defaults);

            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }

        return back()->with('success', "Seeded {$count} standard CBSE subjects for this Sahodaya.");
    }

    public function storeStream(Request $request)
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('exam_streams', 'code')->where(fn ($q) => $q->where('sahodaya_id', $this->sahodaya->id)),
            ],
            'label' => 'required|string|max:120',
            'examination_type' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'default_subjects' => 'nullable|array',
            'default_subjects.*' => 'string|max:120',
        ]);

        $code = strtoupper(trim($data['code']));

        ExamStream::create([
            'sahodaya_id' => $this->sahodaya->id,
            'code' => $code,
            'label' => $data['label'],
            'examination_type' => $data['examination_type'] ?? 'board',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'default_subjects' => $data['default_subjects'] ?? [],
        ]);

        ExamStream::forgetCache($code, $this->sahodaya->id);

        return back()->with('success', 'Exam stream created for this Sahodaya.');
    }

    public function updateStream(Request $request, string $tenantId, ExamStream $stream)
    {
        abort_unless(
            $stream->sahodaya_id === null || $stream->sahodaya_id === $this->sahodaya->id,
            403
        );

        // Global rows are cloned into a Sahodaya override instead of mutating the shared default.
        if ($stream->sahodaya_id === null) {
            $data = $request->validate([
                'label' => 'required|string|max:120',
                'examination_type' => 'nullable|string|max:40',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'default_subjects' => 'nullable|array',
                'default_subjects.*' => 'string|max:120',
            ]);

            ExamStream::updateOrCreate(
                ['sahodaya_id' => $this->sahodaya->id, 'code' => $stream->code],
                [
                    'label' => $data['label'],
                    'examination_type' => $data['examination_type'] ?? $stream->examination_type,
                    'sort_order' => $data['sort_order'] ?? $stream->sort_order,
                    'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $stream->is_active,
                    'default_subjects' => $data['default_subjects'] ?? $stream->default_subjects,
                ]
            );

            ExamStream::forgetCache($stream->code, $this->sahodaya->id);
            ExamStream::forgetCache($stream->code, null);

            return back()->with('success', 'Sahodaya override saved for global stream.');
        }

        $data = $request->validate([
            'label' => 'required|string|max:120',
            'examination_type' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'default_subjects' => 'nullable|array',
            'default_subjects.*' => 'string|max:120',
        ]);

        $stream->update([
            'label' => $data['label'],
            'examination_type' => $data['examination_type'] ?? $stream->examination_type,
            'sort_order' => $data['sort_order'] ?? $stream->sort_order,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $stream->is_active,
            'default_subjects' => $data['default_subjects'] ?? $stream->default_subjects,
        ]);

        ExamStream::forgetCache($stream->code, $this->sahodaya->id);

        return back()->with('success', 'Exam stream updated.');
    }

    public function destroyStream(string $tenantId, ExamStream $stream)
    {
        abort_if($stream->sahodaya_id !== $this->sahodaya->id, 403, 'Global streams cannot be deleted here.');

        $code = $stream->code;

        if (Topper::query()->where('stream_id', $stream->id)->exists()
            || TopperCountConfig::query()->where('stream_id', $stream->id)->exists()) {
            $stream->update(['is_active' => false]);
            ExamStream::forgetCache($code, $this->sahodaya->id);

            return back()->with('success', 'Stream deactivated (still referenced by toppers/config).');
        }

        $stream->delete();
        ExamStream::forgetCache($code, $this->sahodaya->id);

        return back()->with('success', 'Exam stream removed.');
    }

    public function updateApiConfig(Request $request)
    {
        $data = $request->validate([
            'weight_pass_percent' => 'required|numeric|min:0|max:100',
            'weight_distinctions' => 'required|numeric|min:0|max:100',
            'weight_highest_mark' => 'required|numeric|min:0|max:100',
            'weight_toppers' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $sum = (float) $data['weight_pass_percent']
            + (float) $data['weight_distinctions']
            + (float) $data['weight_highest_mark']
            + (float) $data['weight_toppers'];

        if (abs($sum - 100) > 0.01) {
            return back()->withErrors([
                'weight_pass_percent' => 'API weights must sum to 100 (currently '.$sum.').',
            ]);
        }

        $config = ApiConfig::forSahodaya($this->sahodaya->id);
        $config->update([
            'weight_pass_percent' => $data['weight_pass_percent'],
            'weight_distinctions' => $data['weight_distinctions'],
            'weight_highest_mark' => $data['weight_highest_mark'],
            'weight_toppers' => $data['weight_toppers'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('success', 'Academic Performance Index weights saved.');
    }
}
