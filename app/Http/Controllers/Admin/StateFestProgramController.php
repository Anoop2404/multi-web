<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\StateDomain;
use App\Services\Events\FestEventFeeResolver;
use App\Services\Events\FestItemSyncService;
use App\Services\Events\FestStateProgramService;
use App\Support\FestClassGroupScheme;
use App\Support\FestConductLevels;
use App\Support\FestSportsAgeGroup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StateFestProgramController extends Controller
{
    public function index()
    {
        $programs = FestStateProgram::query()
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

        $program = FestStateProgram::create($data);

        return redirect()->route('admin.state-programs.show', $program)
            ->with('success', 'State program created.');
    }

    public function show(FestStateProgram $stateProgram)
    {
        $stateProgram->load(['propagations.sahodaya:id,name', 'items', 'stateDomain']);

        return inertia('StatePrograms/Show', [
            'program'    => $stateProgram,
            'eventTypes' => $this->eventTypes(),
            'levelLabels'=> FestStateProgram::levelLabels(),
            'feeTypes'   => config('fest_fees.fee_models'),
            'levelDefaults' => config('fest_fees.level_defaults'),
            'classGroupLabels' => config('fest_class_group_schemes.schemes.cbse.groups'),
            'classGroupSchemeOptions' => FestClassGroupScheme::options(),
            'ageGroupLabels' => FestSportsAgeGroup::labels(),
            'defaultAgeGroupFees' => FestSportsAgeGroup::defaultFees(),
            'participationPresets' => config('fest_participation_presets'),
            'taxonomy'   => config('fest_item_taxonomy'),
            'stateDomains' => StateDomain::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'domain', 'api_base_url', 'api_client_id']),
            'defaultQualifierPolicy' => config('fest_conduct_presets.mcs_kalotsav.qualifier_policy', [
                'regional' => ['positions' => [1]],
                'district' => ['positions' => [1, 2]],
                'skip_item_flags' => ['mcs_only'],
            ]),
        ]);
    }

    public function update(Request $request, FestStateProgram $stateProgram)
    {
        if ($stateProgram->status === 'published') {
            $data = $this->validateProgram($request);
            unset($data['title'], $data['event_type'], $data['conduct_levels']);
            $data = $this->attachStateDomainConfig($request, $data);
        } else {
            $data = $this->validateProgram($request);
            $data = $this->attachStateDomainConfig($request, $data);
        }

        $stateProgram->update($data);

        return back()->with('success', 'State program updated.');
    }

    public function destroy(FestStateProgram $stateProgram)
    {
        abort_if($stateProgram->status === 'published', 422, 'Published programs cannot be deleted.');

        $stateProgram->delete();

        return redirect()->route('admin.state-programs.index')
            ->with('success', 'State program deleted.');
    }

    public function publish(FestStateProgram $stateProgram, FestStateProgramService $service)
    {
        $result = $service->publish($stateProgram);

        app(\App\Services\Audit\PlatformAuditLogger::class)->log(
            'state_program.published',
            "State program published: {$stateProgram->title}",
            $stateProgram,
            [
                'propagated' => $result['propagated'],
                'skipped'    => $result['skipped'],
                'errors'     => $result['errors'],
            ],
        );

        $message = "Published to {$result['propagated']} Sahodaya event(s).";
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

    public function destroyItem(FestStateProgram $stateProgram, FestStateProgramItem $item)
    {
        abort_if($item->state_program_id !== $stateProgram->id, 404);

        $item->delete();

        return back()->with('success', 'State item removed. Re-publish or add replacements to update Sahodayas.');
    }

    /** @return array<string, mixed> */
    private function validateItem(Request $request): array
    {
        return $request->validate([
            'title'              => 'required|string|max:255',
            'item_code'          => 'nullable|string|max:20',
            'category'           => 'nullable|in:music,dance,drama,literary,sports,general',
            'stage_type'         => 'nullable|in:on_stage,off_stage',
            'venue_type'         => 'nullable|in:indoor,outdoor',
            'competition_format' => 'nullable|in:individual,singles,doubles,mixed_doubles,team,relay,group,board_game',
            'sport_discipline'   => 'nullable|string|max:40',
            'participant_type'   => 'nullable|in:individual,group,team',
            'gender'             => 'nullable|in:male,female,mixed,open',
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
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
            'level_fees.*.fee_model' => 'nullable|in:none,cksc_tiered,item_catalog,flat_school,per_item',
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
            'state_domain_id'    => 'nullable|uuid|exists:state_domains,id',
            'state_flow_mode'    => 'nullable|in:state_domain_event,read_only_aggregation',
            'qualifier_policy'   => 'nullable|array',
            'qualifier_policy.regional.positions' => 'nullable|array',
            'qualifier_policy.regional.positions.*' => 'integer|min:1|max:10',
            'qualifier_policy.district.positions' => 'nullable|array',
            'qualifier_policy.district.positions.*' => 'integer|min:1|max:10',
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
