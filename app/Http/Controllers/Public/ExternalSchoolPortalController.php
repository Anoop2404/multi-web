<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ExternalSchool;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Services\State\ExternalIntakeService;
use Illuminate\Http\Request;

/**
 * Code-gated school portal — a school under an outside Sahodaya enters its own qualified
 * students directly. See docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 */
class ExternalSchoolPortalController extends Controller
{
    public function show(string $code)
    {
        $school = $this->resolve($code);

        return view('external.school-portal', [
            'school'  => $school->load('sahodaya.program'),
            'items'   => FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
                ->orderBy('display_order')
                ->get(['id', 'item_code', 'title', 'class_group']),
            'entries' => StateQualifierEntry::where('school_id', $school->id)
                ->orderBy('item_code')
                ->get(),
        ]);
    }

    public function store(Request $request, string $code, ExternalIntakeService $service)
    {
        $school = $this->resolve($code);

        $data = $request->validate([
            'item_code'    => 'required|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'student_name' => 'required|string|max:255',
            'class_name'   => 'nullable|string|max:40',
            'position'     => 'nullable|integer|min:1|max:3',
            'grade'        => 'nullable|string|max:8',
        ]);

        // Item selection must come from the state catalog dropdown, not free text — keeps
        // item_code matching clean downstream (§2.2's "item catalog mismatch" open question).
        $item = FestStateProgramItem::where('state_program_id', $school->sahodaya->state_program_id)
            ->where('item_code', $data['item_code'])
            ->first();

        abort_unless($item, 422, 'Select an item from the list.');

        $service->addEntry($school, $data + [
            'item_id'       => $item->id,
            'item_name'     => $item->title,
            'qualify_count' => $item->qualify_count,
        ]);

        return back()->with('success', "Added {$data['student_name']} for {$item->title}.");
    }

    public function destroy(string $code, StateQualifierEntry $entry, ExternalIntakeService $service)
    {
        $school = $this->resolve($code);

        $service->removeEntry($school, $entry);

        return back()->with('success', 'Entry removed.');
    }

    private function resolve(string $code): ExternalSchool
    {
        $school = ExternalSchool::where('access_code', strtoupper($code))->first();

        abort_if(! $school, 404, 'Access code not recognized.');
        abort_unless($school->isActive(), 403, 'This access code has been disabled. Contact your Sahodaya coordinator.');
        abort_unless($school->sahodaya?->isActive(), 403, 'Your Sahodaya\'s access has been disabled. Contact the State Kalolsavam office.');

        return $school;
    }
}
