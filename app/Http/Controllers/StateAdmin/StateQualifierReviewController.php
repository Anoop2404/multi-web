<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateQualifierReviewController extends Controller
{
    public function index()
    {
        $intakes = StateQualifierIntake::withCount('entries')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('StateAdmin/Qualifiers/Index', [
            'intakes' => $intakes,
        ]);
    }

    public function show(StateQualifierIntake $intake)
    {
        $intake->load(['entries' => fn ($q) => $q->orderBy('item_code')->orderBy('position')]);

        return Inertia::render('StateAdmin/Qualifiers/Show', [
            'intake' => $intake,
        ]);
    }

    public function approve(
        Request $request,
        StateQualifierIntake $intake,
        StateQualifierIntakeService $service,
        StateQualifierMaterializationService $materializer,
    )
    {
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $service->approve($intake, $request->user()?->id, $data['notes'] ?? null);
        $result = $materializer->materializeApprovedIntake($intake->fresh());

        return back()->with(
            'success',
            "Qualifier intake approved and {$result['registrations']} new state registration(s) created."
        );
    }
}
