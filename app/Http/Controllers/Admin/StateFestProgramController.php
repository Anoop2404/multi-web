<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestQualification;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\FestStateProgramPropagation;
use App\Models\StateDomain;
use App\Services\Events\FestEventFeeResolver;
use App\Services\Events\FestItemSyncService;
use App\Services\Events\FestStateProgramService;
use App\Support\FestClassGroupScheme;
use App\Support\FestConductLevels;
use App\Support\FestSportsAgeGroup;
use App\Support\StateScope;
use App\Support\TenantDomainSync;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StateFestProgramController extends Controller
{
    /**
     * Event types that get a generic results/winners rollup here. Kalolsavam and sports
     * have their own dedicated controllers (KalotsavStateController, SportsResultsController)
     * with the same underlying data shape — this covers the remaining program types, which
     * previously had setup/propagation tracking but no results view at all (Path_breaks.md 3.3).
     */
    private const RESULTS_EVENT_TYPES = ['kids_fest', 'teacher_fest', 'custom'];

    public function index()
    {
        $programs = StateScope::apply(FestStateProgram::query())
            ->withCount(['propagations', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return inertia('StatePrograms/Index', [
            'programs'   => $programs,
            'eventTypes' => $this->eventTypes(),
            'levelLabels'=> FestStateProgram::levelLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProgram($request);
        $data = $this->attachStateDomainConfig($request, $data);
        $data['created_by_user_id'] = $request->user()->id;
        $data['status'] = 'draft';
        $data['state_id'] = StateScope::shouldScope($request) ? StateScope::id($request) : null;

        $program = FestStateProgram::create($data);

        return redirect()->route('admin.state-programs.show', $program)
            ->with('success', 'State program created.');
    }

    public function show(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);

        $stateProgram->load(['propagations.sahodaya:id,name', 'items', 'stateDomain']);

        $allSahodayas = \App\Models\Tenant::query()
            ->sahodayas()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'subdomain'])
            ->map(function ($s) use ($stateProgram) {
                $prop = $stateProgram->propagations->firstWhere('sahodaya_id', $s->id);

                // STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 1, Item 3
                // Surface sahodaya_customized_at so the State Admin can see which
                // Sahodayas have locally overridden the state-seeded event fields.
                $customizedAt = null;
                if ($prop?->tenant_event_id) {
                    $customizedAt = \App\Support\TenancyDatabase::whenDatabaseReady($s, function () use ($prop) {
                        return \App\Models\FestEvent::where('id', $prop->tenant_event_id)
                            ->value('sahodaya_customized_at');
                    });
                }

                return [
                    'id'                    => $s->id,
                    'name'                  => $s->name,
                    'subdomain'             => $s->subdomain,
                    'deployed'              => filled($prop?->tenant_event_id),
                    'tenant_event_id'       => $prop?->tenant_event_id,
                    'is_enabled'            => (bool) ($prop?->is_enabled ?? true),
                    'sahodaya_customized_at' => $customizedAt,
                ];
            });


        return inertia('StatePrograms/Show', [
            'program'      => $stateProgram,
            'allSahodayas' => $allSahodayas,
            'eventTypes'   => $this->eventTypes(),
            'levelLabels'  => FestStateProgram::levelLabels(),
            'feeTypes'     => config('fest_fees.fee_models'),
            'levelDefaults' => config('fest_fees.level_defaults'),
            'classGroupLabels' => config('fest_class_group_schemes.schemes.cbse.groups'),
            'classGroupSchemeOptions' => FestClassGroupScheme::options(),
            'ageGroupLabels' => FestSportsAgeGroup::labels(),
            'defaultAgeGroupFees' => FestSportsAgeGroup::defaultFees(),
            'participationPresets' => config('fest_participation_presets'),
            'taxonomy'     => config('fest_item_taxonomy'),
            'stateDomains' => StateDomain::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'domain', 'api_base_url', 'api_client_id']),
            'defaultQualifierPolicy' => config('fest_conduct_presets.mcs_kalotsav.qualifier_policy', [
                'regional' => ['positions' => [1]],
                'district' => ['positions' => [1, 2]],
                'standard' => ['positions' => [1, 2]],
                'skip_item_flags' => ['mcs_only'],
            ]),
        ]);
    }

    public function results(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless(in_array($stateProgram->event_type, self::RESULTS_EVENT_TYPES, true), 404);

        $propagations = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->with('sahodaya')
            ->get();

        $clusterResults = $propagations->map(function (FestStateProgramPropagation $prop) {
            if (! $prop->tenant_event_id || ! $prop->sahodaya) {
                return [
                    'sahodaya' => $prop->sahodaya?->name,
                    'level'    => $prop->level_round,
                    'status'   => 'not_propagated',
                    'results'  => [],
                ];
            }

            $eventData = \App\Support\TenancyDatabase::whenDatabaseReady($prop->sahodaya, function () use ($prop) {
                $event = FestEvent::find($prop->tenant_event_id);
                if (! $event) {
                    return null;
                }

                return [
                    'id'                  => $event->id,
                    'title'               => $event->title,
                    'results_published'   => (bool) $event->results_published,
                    'registrations_count' => FestMark::where('event_id', $event->id)->count(),
                ];
            });

            return [
                'sahodaya'            => $prop->sahodaya?->name,
                'level'               => $prop->level_round,
                'event_id'            => $eventData['id'] ?? null,
                'event_title'         => $eventData['title'] ?? null,
                'results_published'   => $eventData['results_published'] ?? false,
                'registrations_count' => $eventData['registrations_count'] ?? 0,
            ];
        });

        return inertia('StatePrograms/Results', [
            'program'        => $stateProgram->only('id', 'title', 'academic_year', 'status', 'event_type'),
            'clusterResults' => $clusterResults,
        ]);
    }

    public function winners(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless(in_array($stateProgram->event_type, self::RESULTS_EVENT_TYPES, true), 404);

        return inertia('StatePrograms/Winners', [
            'program' => $stateProgram->only('id', 'title', 'academic_year', 'event_type'),
            'winners' => $this->collectWinnerRows($stateProgram),
        ]);
    }

    public function exportWinners(FestStateProgram $stateProgram): StreamedResponse
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_unless(in_array($stateProgram->event_type, self::RESULTS_EVENT_TYPES, true), 404);

        $rows = $this->collectWinnerRows($stateProgram);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Participant', 'Reg No', 'School', 'Item', 'Category', 'Grade', 'From Event', 'Next Level', 'Promoted At']);
            foreach ($rows as $w) {
                fputcsv($out, [
                    $w['participant'], $w['reg_no'], $w['school'], $w['item'], $w['category'],
                    $w['grade'], $w['from_event'], $w['next_level'], $w['promoted_at'],
                ]);
            }
            fclose($out);
        }, "state-program-winners-{$stateProgram->id}.csv");
    }

    /**
     * Qualifications/marks live in each Sahodaya's own tenant database, so this has to loop
     * per-Sahodaya via TenancyDatabase rather than running one whereIn('event_id', ...)
     * across ids that span multiple databases. Mirrors KalotsavStateController's identical
     * private method — duplicated rather than shared to avoid touching that already-verified
     * controller for this fix.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function collectWinnerRows(FestStateProgram $stateProgram): \Illuminate\Support\Collection
    {
        $propagations = FestStateProgramPropagation::where('state_program_id', $stateProgram->id)
            ->whereNotNull('tenant_event_id')
            ->with('sahodaya')
            ->get();

        $winners = collect();

        foreach ($propagations as $propagation) {
            if (! $propagation->sahodaya) {
                continue;
            }

            $rows = \App\Support\TenancyDatabase::whenDatabaseReady($propagation->sahodaya, function () use ($propagation) {
                $qualifications = FestQualification::where('event_id', $propagation->tenant_event_id)
                    ->with([
                        'participant.student',
                        'participant.teacher',
                        'participant.registration.school',
                        'item',
                        'event',
                        'nextLevelEvent',
                    ])
                    ->orderByDesc('promoted_at')
                    ->get();

                $marks = FestMark::where('event_id', $propagation->tenant_event_id)
                    ->whereIn('participant_id', $qualifications->pluck('participant_id'))
                    ->get()
                    ->keyBy(fn (FestMark $m) => "{$m->event_id}:{$m->item_id}:{$m->participant_id}");

                $base = TenantDomainSync::publicUrl($propagation->sahodaya);

                return $qualifications->map(function (FestQualification $q) use ($marks, $base) {
                    $mark = $marks->get("{$q->event_id}:{$q->item_id}:{$q->participant_id}");
                    $posterUrl = null;

                    if ($mark && in_array((int) $mark->position, [1, 2, 3], true) && $base && $q->event && $q->item) {
                        $posterUrl = rtrim($base, '/')."/fest/{$q->event_id}/items/{$q->item_id}/winners/{$mark->id}/poster.svg";
                    }

                    return [
                        'participant' => $q->participant?->student?->name ?? $q->participant?->teacher?->name,
                        'reg_no'      => $q->participant?->student?->reg_no,
                        'school'      => $q->participant?->registration?->school?->name,
                        'item'        => $q->item?->title,
                        'category'    => $q->item?->category,
                        'grade'       => $mark?->grade,
                        'from_event'  => $q->event?->title,
                        'next_level'  => $q->nextLevelEvent?->level_round,
                        'promoted_at' => $q->promoted_at?->toDateString(),
                        'poster_url'  => $posterUrl,
                    ];
                });
            }, collect());

            $winners = $winners->concat($rows);
        }

        return $winners->sortByDesc('promoted_at')->values();
    }

    public function sahodayaItems(FestStateProgram $stateProgram, \App\Models\Tenant $sahodaya)
    {
        StateScope::assertOwns($stateProgram->state_id);

        $items = \App\Support\TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($stateProgram, $sahodaya) {
            $event = \App\Models\FestEvent::where('tenant_id', $sahodaya->id)
                ->where('state_program_id', $stateProgram->id)
                ->first();

            if (! $event) {
                return [];
            }

            return \App\Models\FestEventItem::where('event_id', $event->id)
                ->orderBy('display_order')
                ->get(['id', 'title', 'item_code', 'category', 'class_group', 'is_enabled', 'state_program_item_id']);
        });

        return response()->json([
            'sahodaya' => ['id' => $sahodaya->id, 'name' => $sahodaya->name],
            'items'    => $items,
        ]);
    }

    public function toggleSahodaya(Request $request, FestStateProgram $stateProgram, \App\Models\Tenant $sahodaya)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $enabled = $request->boolean('enabled');

        // Not-yet-deployed Sahodayas have no propagation row yet; toggling one
        // records the disabled/enabled intent up front so it takes effect on deploy.
        $propagation = \App\Models\FestStateProgramPropagation::firstOrCreate(
            [
                'state_program_id' => $stateProgram->id,
                'sahodaya_id' => $sahodaya->id,
                'level_round' => 'sahodaya',
            ],
        );

        $propagation->update(['is_enabled' => $enabled]);

        $status = $enabled ? 'enabled' : 'disabled';

        return back()->with('success', "{$sahodaya->name} {$status} for this state program.");
    }

    public function toggleSahodayaItem(Request $request, FestStateProgram $stateProgram, \App\Models\Tenant $sahodaya, \App\Models\FestEventItem $item)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $enabled = $request->boolean('enabled');

        \App\Support\TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($item, $enabled) {
            $item->update(['is_enabled' => $enabled]);
        });

        return back()->with('success', 'Item visibility updated.');
    }

    public function bulkToggleSahodayaItems(Request $request, FestStateProgram $stateProgram, \App\Models\Tenant $sahodaya)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $enabled = $request->boolean('enabled');
        $itemIds = $request->input('item_ids', []);

        \App\Support\TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($stateProgram, $sahodaya, $itemIds, $enabled) {
            $event = \App\Models\FestEvent::where('tenant_id', $sahodaya->id)
                ->where('state_program_id', $stateProgram->id)
                ->first();

            if ($event) {
                $q = \App\Models\FestEventItem::where('event_id', $event->id);
                if (! empty($itemIds)) {
                    $q->whereIn('id', $itemIds);
                }
                $q->update(['is_enabled' => $enabled]);
            }
        });

        $statusStr = $enabled ? 'enabled' : 'hidden';
        return back()->with('success', "Selected items {$statusStr} for {$sahodaya->name}.");
    }

    public function update(Request $request, FestStateProgram $stateProgram, FestStateProgramService $service)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $data = $this->validateProgram($request);
        $data = $this->attachStateDomainConfig($request, $data);

        if ($stateProgram->status === 'published') {
            unset($data['event_type']);
        }

        $stateProgram->update($data);

        if ($stateProgram->status === 'published') {
            $result = $service->publish($stateProgram->fresh());
            if ($result['errors'] !== []) {
                return back()->with('warning', 'Program saved, but some deployments could not be updated: '.implode('; ', $result['errors']));
            }

            return back()->with('success', "State program updated and synced to {$result['updated']} existing Sahodaya event(s).");
        }

        return back()->with('success', 'State program updated.');
    }

    public function destroy(FestStateProgram $stateProgram)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_if($stateProgram->status === 'published', 422, 'Published programs cannot be deleted.');

        $stateProgram->delete();

        return redirect()->route('admin.state-programs.index')
            ->with('success', 'State program deleted.');
    }

    public function publish(FestStateProgram $stateProgram, FestStateProgramService $service)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $result = $service->publish($stateProgram);

        app(\App\Services\Audit\PlatformAuditLogger::class)->log(
            'state_program.published',
            "State program published: {$stateProgram->title}",
            $stateProgram,
            [
                'propagated' => $result['propagated'],
                'updated'    => $result['updated'],
                'skipped'    => $result['skipped'],
                'errors'     => $result['errors'],
            ],
        );

        $message = "Published to {$result['propagated']} Sahodaya event(s).";
        if ($result['updated'] > 0) {
            $message .= " {$result['updated']} existing event(s) re-synced.";
        }
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} already existed.";
        }
        if ($result['errors'] !== []) {
            return back()->with('warning', $message.' Some clusters failed: '.implode('; ', $result['errors']));
        }

        return back()->with('success', $message);
    }

    public function storeItem(Request $request, FestStateProgram $stateProgram, FestItemSyncService $syncService)
    {
        StateScope::assertOwns($stateProgram->state_id);
        $data = $this->validateItem($request);

        $data['state_program_id'] = $stateProgram->id;
        $data['display_order'] = ($stateProgram->items()->max('display_order') ?? 0) + 1;

        $stateProgram->items()->create($data);

        if ($stateProgram->status === 'published') {
            $synced = $syncService->syncProgramToAllPropagations($stateProgram->fresh('items'));
            return back()->with('success', "Item added and synced to {$synced} Sahodaya event item slot(s).");
        }

        return back()->with('success', 'State item added (optional — publish to push to Sahodayas).');
    }

    public function updateItem(Request $request, FestStateProgram $stateProgram, FestStateProgramItem $item, FestItemSyncService $syncService)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_if($item->state_program_id !== $stateProgram->id, 404);

        $data = $this->validateItem($request);

        $item->update($data);

        if ($stateProgram->status === 'published') {
            $synced = $syncService->syncProgramToAllPropagations($stateProgram->fresh('items'));
            return back()->with('success', "Item '{$item->title}' updated and synced to {$synced} Sahodaya event item slot(s).");
        }

        return back()->with('success', "Item '{$item->title}' updated.");
    }

    public function destroyItem(FestStateProgram $stateProgram, FestStateProgramItem $item, FestItemSyncService $syncService)
    {
        StateScope::assertOwns($stateProgram->state_id);
        abort_if($item->state_program_id !== $stateProgram->id, 404);

        $itemId = $item->id;
        $item->delete();

        $affected = $stateProgram->status === 'published'
            ? $syncService->removeProgramItemFromAllPropagations($stateProgram, $itemId)
            : 0;

        return back()->with('success', "State item removed; {$affected} propagated item copy/copies were removed or disabled.");
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request): array
    {
        return $request->validate([
            'title'              => 'required|string|max:255',
            'item_code'          => 'nullable|string|max:20',
            'category'           => 'nullable|string|max:60',
            'stage_type'         => 'nullable|in:on_stage,off_stage',
            'venue_type'         => 'nullable|in:indoor,outdoor',
            'competition_format' => 'nullable|in:individual,singles,doubles,mixed_doubles,team,relay,group,board_game',
            'sport_discipline'   => 'nullable|string|max:40',
            'participant_type'   => 'nullable|in:individual,pair,trio,group,team',
            'gender'             => 'nullable|in:male,female,mixed,open',
            'class_group'        => 'nullable|alpha_dash|max:60',
            'age_group'          => 'nullable|in:u8,u10,u11,u12,u14,u17,u19,open',
            'kids_band'          => 'nullable|in:pre_kg,lkg,ukg,class1,class2,open',
            'max_per_school'     => 'nullable|integer|min:1',
            'min_group_size'     => 'nullable|integer|min:1',
            'max_group_size'     => 'nullable|integer|min:1',
            'qualify_count'      => 'nullable|integer|min:1',
            'fee_amount'         => 'nullable|numeric|min:0',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateProgram(Request $request): array
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'status'             => 'nullable|in:draft,published,inactive',
            'event_type'         => ['required', \Illuminate\Validation\Rule::in(array_keys(config('fest_competition_types', [])))],
            'conduct_levels'     => 'required|array|min:1',
            'conduct_levels.*'   => Rule::in(['state', 'sahodaya', 'school']),
            'academic_year'      => 'nullable|string|max:20',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date|after_or_equal:registration_open',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date|after_or_equal:event_start',
            'venue'              => 'nullable|string|max:255',
            'fee_type'           => 'nullable|in:none,flat_school,per_participant,per_item',
            'fee_amount'         => 'nullable|numeric|min:0',
            'level_fees'         => 'nullable|array',
            'level_fees.*.fee_model' => ['nullable', Rule::in(array_keys(config('fest_fees.fee_models', [])))],
            'level_fees.state.individual_amount' => 'nullable|numeric|min:0',
            'level_fees.*.class_group_scheme' => 'nullable|in:cbse,sahodaya',
            'level_fees.*.first_item' => 'nullable|numeric|min:0',
            'level_fees.*.additional_item' => 'nullable|numeric|min:0',
            'level_fees.*.default_item_fee' => 'nullable|numeric|min:0',
            'level_fees.*.class_group_fees' => 'nullable|array',
            'level_fees.*.class_group_fees.*' => 'nullable|numeric|min:0',
            'level_fees.*.age_group_fees' => 'nullable|array',
            'level_fees.*.age_group_fees.*' => 'nullable|numeric|min:0',
            'level_fees.*.participant_type_fees' => 'nullable|array',
            'level_fees.*.participant_type_fees.group' => 'nullable|numeric|min:0',
            'level_fees.*.participant_type_fees.team' => 'nullable|numeric|min:0',
            'level_policies'     => 'nullable|array',
            'level_policies.*.preset_key' => 'nullable|string|max:60',
            'level_policies.*.max_onstage_per_student' => 'nullable|integer|min:0',
            'level_policies.*.max_offstage_per_student' => 'nullable|integer|min:0',
            'level_policies.*.max_group_per_student' => 'nullable|integer|min:0',
            'level_policies.*.max_total_per_student' => 'nullable|integer|min:0',
            'state_domain_id'    => 'nullable|uuid|exists:state_domains,id',
            'state_flow_mode'    => 'nullable|in:state_domain_event,read_only_aggregation',
            'qualifier_policy'   => 'nullable|array',
            'qualifier_policy.regional.positions' => 'nullable|array',
            'qualifier_policy.regional.positions.*' => 'integer|min:1|max:10',
            'qualifier_policy.district.positions' => 'nullable|array',
            'qualifier_policy.district.positions.*' => 'integer|min:1|max:10',
            'qualifier_policy.standard.positions' => 'nullable|array',
            'qualifier_policy.standard.positions.*' => 'integer|min:1|max:10',
            'qualifier_policy.skip_item_flags' => 'nullable|array',
            'qualifier_policy.skip_item_flags.*' => 'string|max:80',
            'state_domain'       => 'nullable|array',
            'state_domain.name'  => 'nullable|string|max:255',
            'state_domain.domain'=> 'nullable|string|max:255',
            'state_domain.api_base_url' => 'nullable|url|max:255',
            'state_domain.api_client_id' => 'nullable|string|max:64',
            'state_domain.api_client_secret' => 'nullable|string|max:255',
            'description'        => 'nullable|string',
        ]);

        if (isset($data['conduct_levels'], $data['event_type'])) {
            $data['conduct_levels'] = FestConductLevels::normalize($data['conduct_levels'], $data['event_type']);
            if ($data['conduct_levels'] === []) {
                $data['conduct_levels'] = FestConductLevels::defaultsFor($data['event_type']);
            }
        }

        if (isset($data['level_fees'], $data['conduct_levels'])) {
            $data['level_fees'] = app(FestEventFeeResolver::class)
                ->normalizeLevelFees($data['level_fees'], $data['conduct_levels']);
        }

        if (isset($data['level_policies'], $data['conduct_levels'])) {
            $normalized = [];
            foreach ($data['conduct_levels'] as $level) {
                if ($level === 'state') {
                    continue;
                }
                $row = $data['level_policies'][$level] ?? [];
                if (! empty($row['preset_key'])) {
                    $normalized[$level] = ['preset_key' => $row['preset_key']];
                } else {
                    $normalized[$level] = array_filter([
                        'max_onstage_per_student' => $row['max_onstage_per_student'] ?? null,
                        'max_offstage_per_student' => $row['max_offstage_per_student'] ?? null,
                        'max_group_per_student' => $row['max_group_per_student'] ?? null,
                        'max_total_per_student' => $row['max_total_per_student'] ?? null,
                    ], fn ($v) => $v !== null && $v !== '');
                }
            }
            $data['level_policies'] = $normalized;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function attachStateDomainConfig(Request $request, array $data): array
    {
        $domainData = $data['state_domain'] ?? [];
        unset($data['state_domain']);

        $data['state_flow_mode'] = $data['state_flow_mode'] ?? 'state_domain_event';

        if (! is_array($domainData) || $domainData === []) {
            return $data;
        }

        $hasInlineDomain = collect($domainData)
            ->except(['api_client_secret'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();

        if (! $hasInlineDomain && blank($domainData['api_client_secret'] ?? null)) {
            return $data;
        }

        $domain = ! empty($data['state_domain_id'])
            ? StateDomain::find($data['state_domain_id'])
            : null;

        $attributes = array_filter([
            'name'          => $domainData['name'] ?? null,
            'domain'        => $domainData['domain'] ?? null,
            'api_base_url'  => $domainData['api_base_url'] ?? null,
            'api_client_id' => $domainData['api_client_id'] ?? null,
            'status'        => 'active',
        ], fn ($value) => filled($value));

        if (! $domain) {
            abort_unless($hasInlineDomain, 422, 'Select an existing state domain or enter state domain details.');
            $domain = StateDomain::create(array_merge([
                'name'          => $attributes['name'] ?? $request->input('title').' State',
                'api_client_id' => $attributes['api_client_id'] ?? str()->uuid()->toString(),
                'status'        => 'active',
            ], $attributes));
        } elseif ($attributes !== []) {
            $domain->update($attributes);
        }

        if (filled($domainData['api_client_secret'] ?? null)) {
            $meta = $domain->meta ?? [];
            $meta['api_client_secret'] = Crypt::encryptString($domainData['api_client_secret']);
            $domain->update([
                'api_client_secret_hash' => Hash::make($domainData['api_client_secret']),
                'meta' => $meta,
            ]);
        }

        $data['state_domain_id'] = $domain->id;

        return $data;
    }

    private function eventTypes(): array
    {
        return [
            'kalolsavam'   => 'Kalolsavam',
            'sports'       => 'Sports Meet',
            'kids_fest'    => 'Kids Fest',
            'teacher_fest' => 'Teacher Fest',
            'custom'       => 'Custom',
        ];
    }
}
