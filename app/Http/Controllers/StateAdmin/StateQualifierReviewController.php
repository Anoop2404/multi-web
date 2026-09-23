<?php

namespace App\Http\Controllers\StateAdmin;

use App\Http\Controllers\Controller;
use App\Models\State\StateQualifierEntry;
use App\Models\State\StateQualifierIntake;
use App\Services\State\StateQualifierIntakeService;
use App\Services\State\StateQualifierMaterializationService;
use App\Services\State\StateRemittanceService;
use App\Models\ExternalSahodaya;
use App\Models\FestStateProgram;
use App\Models\Tenant;
use App\Support\StateScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StateQualifierReviewController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'source'   => 'nullable|in:all,tenant,external',
            'status'   => 'nullable|string|max:20',
            'program'  => 'nullable|uuid',
            'district' => 'nullable|string|max:120',
            'search'   => 'nullable|string|max:120',
        ]);

        $source = $filters['source'] ?? 'all';
        $district = trim((string) ($filters['district'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = StateScope::apply(StateQualifierIntake::withCount('entries'))
            ->where('status', '!=', 'draft')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['program'] ?? null, fn ($q, $program) => $q->where('state_program_id', $program))
            // The two intake paths are distinguished by a prefix on source_tenant_id: an outside
            // Sahodaya's intake is "external:{uuid}" (ExternalIntakeService::openDraftIntake), a
            // managed Sahodaya's is the bare tenant uuid.
            ->when($source === 'external', fn ($q) => $q->where('source_tenant_id', 'like', 'external:%'))
            ->when($source === 'tenant', fn ($q) => $q->where('source_tenant_id', 'not like', 'external:%'))
            ->orderByDesc('created_at');

        // District and free-text search resolve against central tables (tenants /
        // external_sahodayas) that the state connection cannot join to, so they are applied as an
        // id whitelist rather than SQL on the intake query.
        if ($district !== '' || $search !== '') {
            $query->whereIn('source_tenant_id', $this->sourceIdsMatching($district, $search));
        }

        $intakes = $query->paginate(20)->withQueryString();

        $routePrefix = request()->routeIs('state.portal.*') ? 'state.portal' : 'admin.state';
        $this->decorateIntakes($intakes->getCollection(), $routePrefix);

        $statePrograms = StateScope::apply(FestStateProgram::orderByDesc('created_at'))->get(['id', 'title', 'event_type']);
        $sahodayas = Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('StateAdmin/Qualifiers/Index', [
            'intakes' => $intakes,
            'statePrograms' => $statePrograms,
            'sahodayas' => $sahodayas,
            'filters' => [
                'source'   => $source,
                'status'   => $filters['status'] ?? null,
                'program'  => $filters['program'] ?? null,
                'district' => $district !== '' ? $district : null,
                'search'   => $search !== '' ? $search : null,
            ],
            'districts' => ExternalSahodaya::query()
                ->whereNotNull('district')
                ->distinct()
                ->orderBy('district')
                ->pluck('district')
                ->values(),
            'actionUrls' => [
                'storeIntake' => route("{$routePrefix}.qualifiers.store-intake", [], false),
                'index'       => route("{$routePrefix}.qualifiers.index", [], false),
            ],
        ]);
    }

    /**
     * source_tenant_id values whose Sahodaya matches the district and/or free-text filter, in both
     * the tenant and the outside-Sahodaya tables.
     *
     * @return list<string>
     */
    private function sourceIdsMatching(string $district, string $search): array
    {
        $ids = [];

        $externals = ExternalSahodaya::query()
            ->when($district !== '', fn ($q) => $q->whereRaw('lower(district) = ?', [strtolower($district)]))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->pluck('id');

        foreach ($externals as $id) {
            $ids[] = "external:{$id}";
        }

        // A district filter has no meaning for tenants — tenants carry no district — so a district
        // search deliberately returns external Sahodayas only.
        if ($district === '') {
            $tenants = Tenant::query()
                ->where('type', 'sahodaya')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->pluck('id');

            foreach ($tenants as $id) {
                $ids[] = $id;
            }
        }

        // An empty whitelist must match nothing rather than everything.
        return $ids ?: ['__no_match__'];
    }

    /**
     * Names the Sahodaya behind each intake and which pipe it came through. Resolved in bulk: the
     * intakes live on the state connection and their sources on the central one, so this cannot be
     * a join, and doing it per row would be a query per intake.
     */
    private function decorateIntakes($intakes, string $routePrefix): void
    {
        $externalIds = [];
        $tenantIds = [];

        foreach ($intakes as $intake) {
            $src = (string) $intake->source_tenant_id;
            if (str_starts_with($src, 'external:')) {
                $externalIds[] = substr($src, 9);
            } else {
                $tenantIds[] = $src;
            }
        }

        $externals = ExternalSahodaya::whereIn('id', array_filter($externalIds))->get()->keyBy('id');
        $tenants = Tenant::whereIn('id', array_filter($tenantIds))->get(['id', 'name'])->keyBy('id');
        $programs = FestStateProgram::whereIn('id', $intakes->pluck('state_program_id')->filter()->unique())
            ->pluck('title', 'id');

        $entryCounts = StateQualifierEntry::whereIn('intake_id', $intakes->pluck('id'))
            ->selectRaw('intake_id, status, count(*) as c')
            ->groupBy('intake_id', 'status')
            ->get()
            ->groupBy('intake_id');

        foreach ($intakes as $intake) {
            $src = (string) $intake->source_tenant_id;
            $isExternal = str_starts_with($src, 'external:');
            $model = $isExternal ? $externals->get(substr($src, 9)) : $tenants->get($src);
            $byStatus = ($entryCounts->get($intake->id) ?? collect())->pluck('c', 'status');

            $intake->setAttribute('review_url', route("{$routePrefix}.qualifiers.show", $intake, false));
            $intake->setAttribute('source_kind', $isExternal ? 'external' : 'tenant');
            // Falling back to the raw id rather than "Unknown" keeps an unresolvable intake
            // actionable instead of anonymous.
            $intake->setAttribute('source_name', $model?->name ?? $src);
            $intake->setAttribute('district', $isExternal ? $model?->district : null);
            $intake->setAttribute('source_promoted', $isExternal ? (bool) $model?->tenant_id : true);
            $intake->setAttribute('program_title', $programs[$intake->state_program_id] ?? null);
            $intake->setAttribute('approved_count', (int) ($byStatus['approved'] ?? 0));
            $intake->setAttribute('pending_count', (int) ($byStatus['pending'] ?? 0));
        }
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
            'roll_number'  => 'nullable|string|max:32',
            'item_code'    => 'nullable|string|max:20',
            'item_name'    => 'nullable|string|max:255',
            'position'     => 'required|integer|min:1',
            'grade'        => 'nullable|string|max:10',
            'status'       => 'nullable|in:pending,approved,rejected',
        ]);

        $intake->entries()->create([
            'student_name' => $data['student_name'],
            'school_name'  => $data['school_name'],
            'roll_number'  => $data['roll_number'] ?? null,
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
            'roll_number'  => 'nullable|string|max:32',
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
            // Fixed per-Sahodaya fee by default; calculateDemandFor() honours a program that
            // deliberately opts into per-item billing instead.
            $remittances->calculateDemandFor($program, $sahodaya);
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
