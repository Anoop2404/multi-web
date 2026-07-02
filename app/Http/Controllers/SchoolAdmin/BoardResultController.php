<?php

namespace App\Http\Controllers\SchoolAdmin;

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
        return $this->inertia('School/BoardResults/Toppers', compact('boardResult'));
    }

    public function storeTopper(Request $request, string $tenantId, BoardResult $boardResult)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'percentage'        => 'required|numeric|min:0|max:100',
            'total_marks'       => 'nullable|integer|min:0',
            'marks_obtained'    => 'nullable|integer|min:0',
            'stream'            => 'nullable|string|max:100',
            'rank'              => 'nullable|integer|min:1',
            'is_perfect_scorer' => 'boolean',
            'photo'             => 'nullable|image|max:4096',
        ]);

        $data['board_result_id'] = $boardResult->id;
        $data['tenant_id']       = $this->school->id;
        $data = PersistDefaults::coalesce($data, [
            'rank' => 1,
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store(
                'board-results/' . $this->school->id . '/' . $boardResult->id,
                's3'
            );
        }

        Topper::create($data);
        return back()->with('success', 'Topper added.');
    }

    public function destroyTopper(string $tenantId, BoardResult $boardResult, Topper $topper)
    {
        abort_if($boardResult->tenant_id !== $this->school->id, 403);
        abort_if($topper->board_result_id !== $boardResult->id, 403);
        $topper->delete();
        return back()->with('success', 'Topper removed.');
    }
}
