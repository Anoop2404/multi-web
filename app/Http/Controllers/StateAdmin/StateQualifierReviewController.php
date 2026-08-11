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
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateQualifierReviewController extends Controller
{
    public function index()
    {
        // 'draft' intakes are external-Sahodaya submissions still being assembled by the
        // coordinator (schools adding their own students, §2.1) — not visible to State until
        // the coordinator finalizes and submits, same as a Sahodaya tenant reviewing before
        // clicking "Submit qualifiers to State."
        $intakes = StateQualifierIntake::withCount('entries')
            ->where('status', '!=', 'draft')
            ->orderByDesc('created_at')
            ->paginate(20);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $intakes->getCollection()->each(fn (StateQualifierIntake $intake) => $intake->setAttribute(
            'review_url',
            route("{$routePrefix}.qualifiers.show", $intake, false),
        ));

        return Inertia::render('StateAdmin/Qualifiers/Index', [
            'intakes' => $intakes,
        ]);
    }

    public function show(StateQualifierIntake $intake)
    {
        $intake->load(['entries' => fn ($q) => $q->orderBy('item_code')->orderBy('position')]);
        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';

        return Inertia::render('StateAdmin/Qualifiers/Show', [
            'intake' => $intake,
            'actionUrls' => [
                'approve' => route("{$routePrefix}.qualifiers.approve", $intake, false),
                'reviewEntryBase' => route("{$routePrefix}.qualifiers.show", $intake, false).'/entries',
            ],
        ]);
    }

    public function approve(
        Request $request,
        StateQualifierIntake $intake,
        StateQualifierIntakeService $service,
        StateQualifierMaterializationService $materializer,
        StateRemittanceService $remittances,
    )
    {
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
        $data = $request->validate(['status' => 'required|in:approved,rejected']);
        $service->reviewEntry($intake, $entry, $data['status']);

        return back()->with('success', "Qualifier entry {$data['status']}.");
    }
}
