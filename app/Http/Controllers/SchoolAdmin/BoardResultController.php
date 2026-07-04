<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Support\BoardExamSubjects;
use App\Support\PersistDefaults;
use App\Models\BoardResult;
use App\Models\Topper;
use Illuminate\Http\Request;

class BoardResultController extends SchoolAdminController
{
    public function index()
    {
        $results = BoardResult::where('tenant_id', $this->school->id)
            ->with('toppers')
            ->orderByDesc('academic_year')
            ->orderByDesc('class')
            ->get();

        return $this->inertia('School/BoardResults/Index', compact('results'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class'           => 'required|integer|in:10,12',
            'academic_year'   => 'required|string|max:20',
            'total_appeared'  => 'required|integer|min:0',
            'pass_count'      => 'required|integer|min:0',
            'pass_percent'    => 'required|numeric|min:0|max:100',
            'distinctions'    => 'nullable|integer|min:0',
            'first_class'     => 'nullable|integer|min:0',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data = PersistDefaults::coalesce($data, [
            'distinctions' => 0,
            'first_class'  => 0,
        ]);

        $result = BoardResult::updateOrCreate(
            ['tenant_id' => $this->school->id, 'class' => $data['class'], 'academic_year' => $data['academic_year']],
            $data
        );

        return redirect("/school-admin/{$this->school->id}/board-results/{$result->id}/toppers")
            ->with('success', 'Board result saved. Now add toppers.');
    }

    public function destroy(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $boardResult->delete();
        return back()->with('success', 'Board result removed.');
    }

    // ── Toppers ──────────────────────────────────────────────────────────────

    public function toppers(string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        $boardResult->load('toppers');

        $isClass12 = (int) $boardResult->class === 12;

        return $this->inertia('School/BoardResults/Toppers', [
            'boardResult'        => $boardResult,
            'isClass12'          => $isClass12,
            'streamOptions'      => $isClass12 ? BoardExamSubjects::class12StreamLabels() : [],
            'subjectsByStream'   => $isClass12 ? collect(BoardExamSubjects::class12StreamLabels())
                ->mapWithKeys(fn ($label, $key) => [$key => BoardExamSubjects::subjectsForStream($key)])
                ->all() : [],
            'subjectWiseLeaders' => $isClass12
                ? BoardExamSubjects::subjectWiseLeaders($boardResult->toppers)
                : [],
        ]);
    }

    public function updateTopper(Request $request, string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);

        $data = $this->validateTopper($request, (int) $boardResult->class === 12);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store(
                'board-results/'.$this->school->id.'/'.$boardResult->id,
                's3'
            );
        }

        $topper->update($data);

        return back()->with('success', 'Topper updated.');
    }

    public function storeTopper(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);

        $isClass12 = (int) $boardResult->class === 12;
        $data = $this->validateTopper($request, $isClass12);

        $data['board_result_id'] = $boardResult->id;
        $data['tenant_id'] = $this->school->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store(
                'board-results/'.$this->school->id.'/'.$boardResult->id,
                's3'
            );
        }

        Topper::create($data);

        return back()->with('success', 'Topper added.');
    }

    /** @return array<string, mixed> */
    private function validateTopper(Request $request, bool $isClass12): array
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'percentage'        => 'required|numeric|min:0|max:100',
            'total_marks'       => 'nullable|integer|min:0',
            'marks_obtained'    => 'nullable|integer|min:0',
            'stream'            => 'nullable|string|max:100',
            'rank'              => 'nullable|integer|min:1',
            'is_perfect_scorer' => 'boolean',
            'photo'             => 'nullable|image|max:4096',
        ];

        if ($isClass12) {
            $rules['stream_key'] = 'nullable|string|max:50';
            $rules['subject_marks'] = 'nullable|array';
            $rules['subject_marks.*'] = 'nullable|numeric|min:0|max:100';
        }

        $data = $request->validate($rules);
        $data = PersistDefaults::coalesce($data, ['rank' => 1]);

        if ($isClass12) {
            $streamKey = BoardExamSubjects::normalizeStream($data['stream_key'] ?? $data['stream'] ?? null);
            if ($streamKey) {
                $labels = BoardExamSubjects::class12StreamLabels();
                $data['stream'] = $labels[$streamKey] ?? $data['stream'] ?? null;
            }

            $data['subject_marks'] = BoardExamSubjects::normalizeSubjectMarks($data['subject_marks'] ?? []);
            unset($data['stream_key']);
        } else {
            unset($data['subject_marks']);
        }

        return $data;
    }

    public function destroyTopper(string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);
        $topper->delete();
        return back()->with('success', 'Topper removed.');
    }
}
