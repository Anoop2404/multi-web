<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use App\Services\State\StateRemittanceService;
use App\Models\FestStateProgram;
use App\Models\Tenant;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateQualifierReviewController extends Controller
{
    public function index()
    {
        $intakes = StateScope::apply(StateQualifierIntake::withCount('entries'))
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->paginate(20);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $intakes->getCollection()->each(fn (StateQualifierIntake $intake) => $intake->setAttribute(
            'review_url',
            route("{$routePrefix}.qualifiers.show", $intake, false),
        ));

        $statePrograms = StateScope::apply(FestStateProgram::orderByDesc('created_at'))->get(['id', 'title', 'event_type']);
        $sahodayas = Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('StateAdmin/Qualifiers/Index', [
            'intakes' => $intakes,
            'statePrograms' => $statePrograms,
            'sahodayas' => $sahodayas,
            'actionUrls' => [
                'storeIntake' => route("{$routePrefix}.qualifiers.store-intake", [], false),
            ],
        ]);
    }

    public function storeIntake(Request $request, StateQualifierIntakeService $service)
    {
        $data = $request->validate([
            'state_program_id' => 'required|uuid|exists:fest_state_programs,id',
            'source_tenant_id' => 'required|string|max:255',
            'idempotency_key'  => 'nullable|string|max:64',
            'entries'          => 'required|array|min:1',
            'entries.*.student_name' => 'required|string|max:255',
            'entries.*.school_name'  => 'required|string|max:255',
            'entries.*.item_code'    => 'nullable|string|max:20',
            'entries.*.item_name'    => 'nullable|string|max:255',
            'entries.*.position'     => 'required|integer|min:1',
            'entries.*.grade'        => 'nullable|string|max:10',
        ]);

        StateScope::assertOwns(FestStateProgram::find($data['state_program_id'])?->state_id);

        $key = $data['idempotency_key'] ?? 'manual-'.str()->uuid()->toString();

        $intake = $service->receive($key, [
            'state_program_id' => $data['state_program_id'],
            'source_event_id'  => 'manual-state-entry',
            'entries'          => array_map(function ($entry) {
                return [
                    'school_id'    => str()->slug($entry['school_name']),
                    'school_name'  => $entry['school_name'],
                    'item_id'      => str()->uuid()->toString(),
                    'item_code'    => $entry['item_code'] ?? 'AUTO',
                    'item_name'    => $entry['item_name'] ?? $entry['item_code'] ?? 'General Item',
                    'student_name' => $entry['student_name'],
                    'position'     => (int) $entry['position'],
                    'grade'        => $entry['grade'] ?? 'A',
                ];
            }, $data['entries']),
        ], $data['source_tenant_id']);

        return back()->with('success', 'Qualifier intake created with '.count($data['entries']).' entry/entries.');
    }

    public function show(StateQualifierIntake $intake)
    {
        StateScope::assertOwns($intake->state_id);
        $intake->load(['entries' => fn ($q) => $q->orderBy('item_code')->orderBy('position')]);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';

        $stateProgram = FestStateProgram::find($intake->state_program_id);
        $sahodaya = Tenant::query()->where('type', 'sahodaya')->find($intake->source_tenant_id);

        return Inertia::render('StateAdmin/Qualifiers/Show', [
            'intake' => $intake,
            'stateProgram' => $stateProgram,
            'sahodaya' => $sahodaya,
            'actionUrls' => [
                'approve' => route("{$routePrefix}.qualifiers.approve", $intake, false),
                'reviewEntryBase' => route("{$routePrefix}.qualifiers.show", $intake, false).'/entries',
                'storeEntry' => route("{$routePrefix}.qualifiers.entries.store", $intake, false),
            ],
        ]);
    }

    public function storeEntry(Request $request, StateQualifierIntake $intake)
    {
        StateScope::assertOwns($intake->state_id);
        $data = $request->validate([
            'student_name' => 'required|string|max:255',
            'school_name'  => 'required|string|max:255',
            'item_code'    => 'nullable|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'position'     => 'required|integer|min:1',
            'grade'        => 'nullable|string|max:10',
            'status'       => 'nullable|in:pending,approved,rejected',
        ]);

        $intake->entries()->create([
            'student_name' => $data['student_name'],
            'school_name'  => $data['school_name'],
            'school_id'    => str()->slug($data['school_name']),
            'item_id'      => str()->uuid()->toString(),
            'item_code'    => $data['item_code'] ?? 'AUTO',
            'item_name'    => $data['item_name'] ?? $data['item_code'] ?? 'General Item',
            'position'     => (int) $data['position'],
            'grade'        => $data['grade'] ?? 'A',
            'status'       => $data['status'] ?? 'approved',
        ]);

        return back()->with('success', 'Qualifier entry added to intake.');
    }

    public function updateEntry(Request $request, StateQualifierIntake $intake, StateQualifierEntry $entry)
    {
        StateScope::assertOwns($intake->state_id);
        abort_if($entry->intake_id !== $intake->id, 404);

        $data = $request->validate([
            'student_name' => 'required|string|max:255',
            'school_name'  => 'required|string|max:255',
            'item_code'    => 'nullable|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'position'     => 'required|integer|min:1',
            'grade'        => 'nullable|string|max:10',
            'status'       => 'nullable|in:pending,approved,rejected',
        ]);

        $entry->update($data);

        return back()->with('success', 'Qualifier entry updated.');
    }

    public function destroyEntry(StateQualifierIntake $intake, StateQualifierEntry $entry)
    {
        StateScope::assertOwns($intake->state_id);
        abort_if($entry->intake_id !== $intake->id, 404);
        $entry->delete();

        return back()->with('success', 'Qualifier entry removed.');
    }

    public function approve(
        Request $request,
        StateQualifierIntake $intake,
        StateQualifierIntakeService $service,
        StateQualifierMaterializationService $materializer,
        StateRemittanceService $remittances,
    ) {
        StateScope::assertOwns($intake->state_id);
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $intake = $service->approve($intake, $request->user()?->id, $data['notes'] ?? null);
        if ($intake->status === 'rejected') {
            return back()->with('success', 'Qualifier intake finalized with no approved entries. No State registrations or remittance demand were created.');
        }

        $result = $materializer->materializeApprovedIntake($intake);

        $program = FestStateProgram::find($intake->state_program_id);
        $sahodaya = Tenant::query()->where('type', 'sahodaya')->find($intake->source_tenant_id);
        if ($program && $sahodaya && $intake->entries()->where('status', 'approved')->exists()) {
            $remittances->calculateDemandFromApprovedQualifiers($program, $sahodaya);
        }

        return back()->with(
            'success',
            "Qualifier intake approved and {$result['registrations']} new state registration(s) created."
        );
    }

    public function reviewEntry(
        Request $request,
        StateQualifierIntake $intake,
        StateQualifierEntry $entry,
        StateQualifierIntakeService $service,
    ) {
        StateScope::assertOwns($intake->state_id);
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        $service->reviewEntry($intake, $entry, $data['status']);

        return back()->with('success', "Qualifier entry {$data['status']}.");
    }
}
