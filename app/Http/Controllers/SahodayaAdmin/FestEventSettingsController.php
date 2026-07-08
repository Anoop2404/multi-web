<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCombinationRule;
use App\Models\FestEventItem;
use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\FestPointRule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\FestVolunteer;
use App\Models\FestSchoolVerification;
use App\Models\Tenant;
use App\Support\Fest\FestEventSettingsPayload;
use App\Support\FestPageActivity;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use App\Services\Events\EventContext;
use App\Services\Events\FestCloneService;
use App\Services\Events\FestEventFeeResolver;
use App\Services\Events\FestJudgeGateService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestLifecycleService;
use App\Services\Events\FestMandatoryItemService;
use App\Support\TenantStorage;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestEventSettingsController extends SahodayaAdminController
{
    public function settings(string $tenantId, FestEvent $event, ?string $tab = null)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $allowed = ['lifecycle', 'locks', 'venues', 'combo', 'grades', 'points', 'participation', 'eligibility', 'fees', 'registration', 'numbering', 'volunteers', 'records', 'clone'];
        $initialTab = ($tab && in_array($tab, $allowed, true)) ? $tab : 'lifecycle';

        if ($initialTab === 'eligibility' && $event->event_type !== 'sports') {
            $initialTab = 'lifecycle';
        }

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        $schoolNames = $schools->pluck('name', 'id');

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $classGroupScheme = FestClassGroupScheme::resolveForEvent($event, $schedule);

        $itemHeads = \App\Models\FestItemHead::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'reg_start', 'reg_end', 'competition_start', 'competition_end', 'default_item_fee', 'extra_item_fee']);

        $ledgerAccount = app(\App\Services\Ledger\LedgerAccountSetupService::class)
            ->festLedgerMeta($event, $this->sahodaya->id);

        return $this->inertia('Sahodaya/Events/Settings', [
            'event'        => $event->load(['items.head']),
            'itemHeads'    => $itemHeads,
            'ledgerAccount'=> [
                'code'       => $ledgerAccount['code'],
                'name'       => $ledgerAccount['name'],
                'head_id'    => $ledgerAccount['head_id'],
                'ledger_url' => $ledgerAccount['ledger_url'],
            ],
            'feeSchedule'  => $schedule,
            'numberingSettings' => app(\App\Services\Events\FestNumberingService::class)->settings($event),
            'feeModels'    => config('fest_fees.fee_models'),
            'classGroupScheme' => $classGroupScheme,
            'classGroupSchemeOptions' => FestClassGroupScheme::options(),
            'classGroupLabels' => FestClassGroupScheme::labels($classGroupScheme, $event),
            'defaultClassGroupFees' => FestClassGroupScheme::defaultFees($classGroupScheme, $event),
            'defaultParticipantTypeFees' => config('fest_fees.default_participant_type_fees'),
            'ageGroupLabels' => FestSportsAgeGroup::labels($this->sahodaya->id),
            'defaultAgeGroupFees' => FestSportsAgeGroup::defaultFees($this->sahodaya->id),
            'venues'       => FestVenue::where('event_id', $event->id)->orderBy('name')->get(),
            'stages'       => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'comboRules'   => FestCombinationRule::where('event_id', $event->id)->get()->map(fn ($r) => [
                ...$r->toArray(),
                'school_name' => $r->school_id ? ($schoolNames[$r->school_id] ?? $r->school_id) : null,
            ]),
            'gradeConfigs' => FestGradeConfig::where('event_id', $event->id)->with('item')->get(),
            'pointRules'   => FestPointRule::where('event_id', $event->id)->orderBy('grade')->orderBy('position')->get(),
            'rankPoints'   => app(FestRankPointService::class)->listForEvent($event),
            'groupRankPoints' => app(FestRankPointService::class)->listForEvent($event, true),
            'volunteers'   => FestVolunteer::where('event_id', $event->id)->orderBy('name')->get(),
            'schools'      => $schools,
            'judgeGate'    => app(FestJudgeGateService::class)->status($event),
            'lifecycle'    => FestLifecycleService::for($event)->checklist(),
            'suggestedStatus' => FestLifecycleService::for($event)->suggestedStatus(),
            'classGroups'  => FestClassGroupScheme::labels(null, $event),
            'initialTab'   => $initialTab,
            'participationPolicy' => \App\Models\FestParticipationPolicy::where('event_id', $event->id)->whereNull('class_group')->first(),
            'participationPresets' => app(\App\Services\Events\FestParticipationPolicyService::class)->presetOptions(),
            'ageRuleSummary' => $event->event_type === 'sports' ? FestSportsAgeGroup::ageRuleSummary($event) : null,
            'suggestedAgeCutoff' => $event->event_type === 'sports'
                ? FestSportsAgeGroup::cutoffDate($event)->format('Y-m-d')
                : null,
            'defaultCutoffLabel' => $this->defaultCutoffLabel($event),
            'ageGroupHelp' => $event->event_type === 'sports' ? $this->ageGroupHelp($event) : [],
            'schoolVerifications' => $this->schoolVerificationRows($event, $schools),
            'mandatoryGaps' => app(FestMandatoryItemService::class)->schoolsWithMissing($event),
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::settingsTab($initialTab)),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function schoolVerificationRows(FestEvent $event, $schools): array
    {
        $records = FestSchoolVerification::where('event_id', $event->id)
            ->get()
            ->keyBy('school_id');

        return $schools->map(function (Tenant $school) use ($records) {
            $record = $records->get($school->id);

            return [
                'school_id'           => $school->id,
                'school_name'         => $school->name,
                'documents_verified'  => (bool) ($record?->documents_verified ?? false),
                'verified_at'         => $record?->verified_at?->toIso8601String(),
                'notes'               => $record?->notes ?? null,
            ];
        })->values()->all();
    }

    public function updateLifecycleSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'verification_day' => 'nullable|date',
            'manual_pdf'       => 'nullable|file|mimes:pdf|max:10240',
            'remove_manual'    => 'nullable|boolean',
        ]);

        $updates = [
            'verification_day' => filled($data['verification_day'] ?? null) ? $data['verification_day'] : null,
        ];

        if ($request->boolean('remove_manual')) {
            $updates['manual_pdf_path'] = null;
        } elseif ($request->hasFile('manual_pdf')) {
            $updates['manual_pdf_path'] = TenantStorage::storeUploadedFile(
                $request->file('manual_pdf'),
                "fest-manuals/{$event->id}"
            );
        }

        $event->update($updates);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.settings.lifecycle_saved',
            'Lifecycle settings saved',
            [
                'verification_day' => $event->verification_day?->format('Y-m-d'),
                'manual_pdf'       => filled($event->manual_pdf_path),
            ],
        );

        return back()->with('success', 'Lifecycle settings saved.');
    }

    public function updateEligibilitySettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->event_type !== 'sports', 422, 'Age cutoff applies to sports meets only.');

        $data = $request->validate([
            'sports_age_cutoff_date' => 'nullable|date',
        ]);

        $event->update([
            'sports_age_cutoff_date' => filled($data['sports_age_cutoff_date'] ?? null)
                ? $data['sports_age_cutoff_date']
                : null,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('eligibility'),
            'fest.settings.age_cutoff_saved',
            'Sports age reference date saved',
            ['sports_age_cutoff_date' => $event->sports_age_cutoff_date?->format('Y-m-d')],
        );

        return back()->with('success', 'Age cutoff settings saved.');
    }

    /** @return list<array{key: string, label: string, under: int, minBirth: string}> */
    private function ageGroupHelp(FestEvent $event): array
    {
        $labels = FestSportsAgeGroup::labels($event->tenant_id);
        $groups = FestSportsAgeGroup::orderedAgeGroups($event->tenant_id);

        return collect($groups)->map(function (string $key) use ($event, $labels) {
            $under = FestSportsAgeGroup::underAge($key, $event->tenant_id);
            $minBirth = FestSportsAgeGroup::birthDateOnOrAfter($key, $event);

            return [
                'key'      => $key,
                'label'    => $labels[$key] ?? strtoupper($key),
                'under'    => $under,
                'minBirth' => $minBirth?->format('j M Y') ?? '—',
            ];
        })->all();
    }

    private function defaultCutoffLabel(FestEvent $event): string
    {
        if ($event->event_type !== 'sports') {
            return '';
        }

        $temp = new FestEvent([
            'event_type' => 'sports',
            'event_start' => $event->event_start,
            'event_end' => $event->event_end,
            'registration_close' => $event->registration_close,
            'sports_age_cutoff_date' => null,
        ]);

        $cutoff = FestSportsAgeGroup::cutoffDate($temp);

        return $cutoff->format('j M Y');
    }

    public function updateSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'scoring_locked'                      => 'nullable|boolean',
            'appeals_open'                        => 'nullable|boolean',
            'chest_reveal_mode'                   => 'nullable|in:immediate,stage_entry',
            'require_judge_scores_before_publish' => 'nullable|boolean',
            'require_all_marks_before_publish'    => 'nullable|boolean',
            'schedule_published'                  => 'nullable|boolean',
            'appeal_fee_amount'                   => 'nullable|numeric|min:0',
            'certificate_collection_open'         => 'nullable|boolean',
            'registration_locked'                 => 'nullable|boolean',
            'record_tracking_enabled'             => 'nullable|boolean',
            'default_record_prize_label'          => 'nullable|string|max:120',
        ]);

        $data = FestEventSettingsPayload::applyDefaults($data);

        $event->update($data);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('locks'),
            'fest.settings.updated',
            'Event locks & gates saved',
        );

        return back()->with('success', 'Event settings saved.');
    }

    public function updateFeeSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'fee_model' => 'required|in:none,sports_composite,cksc_tiered,item_catalog,flat_school,per_item,per_student',
            'school_registration_flat' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0|max:50',
            'first_item' => 'nullable|numeric|min:0',
            'additional_item' => 'nullable|numeric|min:0',
            'charge_standbys' => 'nullable|boolean',
            'school_registration' => 'nullable|array',
            'school_registration.secondary' => 'nullable|numeric|min:0',
            'school_registration.senior_secondary' => 'nullable|numeric|min:0',
            'flat_amount' => 'nullable|numeric|min:0',
            'per_item_amount' => 'nullable|numeric|min:0',
            'per_student_amount' => 'nullable|numeric|min:0',
            'school_fee_cap' => 'nullable|numeric|min:0',
            'include_school_registration' => 'nullable|boolean',
            'class_group_scheme' => 'nullable|in:cbse,sahodaya',
            'class_group_fees' => 'nullable|array',
            'class_group_fees.*' => 'nullable|numeric|min:0',
            'age_group_fees' => 'nullable|array',
            'age_group_fees.*' => 'nullable|numeric|min:0',
            'participant_type_fees' => 'nullable|array',
            'participant_type_fees.group' => 'nullable|numeric|min:0',
            'participant_type_fees.team' => 'nullable|numeric|min:0',
            'default_item_fee' => 'nullable|numeric|min:0',
            'require_fee_before_registration' => 'nullable|boolean',
            'require_verified_students' => 'nullable|boolean',
            'head_fees' => 'nullable|array',
            'head_fees.*.id' => 'required|exists:fest_item_heads,id',
            'head_fees.*.default_item_fee' => 'nullable|numeric|min:0',
            'head_fees.*.extra_item_fee' => 'nullable|numeric|min:0',
            'item_fees' => 'nullable|array',
            'item_fees.*.id' => 'required|exists:fest_event_items,id',
            'item_fees.*.fee_amount' => 'nullable|numeric|min:0',
        ]);

        $feeSettings = array_merge(
            app(FestEventFeeResolver::class)->normalizeEventFeeSettings($data, $this->sahodaya->id),
            array_filter([
                'require_fee_before_registration' => array_key_exists('require_fee_before_registration', $data)
                    ? (bool) $data['require_fee_before_registration'] : null,
                'require_verified_students' => array_key_exists('require_verified_students', $data)
                    ? (bool) $data['require_verified_students'] : null,
            ], fn ($v) => $v !== null),
        );

        $event->update(['fee_settings' => $feeSettings]);

        foreach ($data['item_fees'] ?? [] as $row) {
            $item = FestEventItem::where('event_id', $event->id)->find($row['id']);
            if (! $item || $item->isStateCatalog()) {
                continue;
            }

            $item->update([
                'fee_amount' => isset($row['fee_amount']) && $row['fee_amount'] !== ''
                    ? (float) $row['fee_amount']
                    : null,
            ]);
        }

        foreach ($data['head_fees'] ?? [] as $row) {
            \App\Models\FestItemHead::where('event_id', $event->id)
                ->where('id', $row['id'])
                ->update([
                    'default_item_fee' => isset($row['default_item_fee']) && $row['default_item_fee'] !== ''
                        ? (float) $row['default_item_fee'] : null,
                    'extra_item_fee' => isset($row['extra_item_fee']) && $row['extra_item_fee'] !== ''
                        ? (float) $row['extra_item_fee'] : null,
                ]);
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.fees_saved',
            'Fee settings saved',
        );

        return back()->with('success', 'Fee settings saved.');
    }

    public function updateLedgerAccount(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $setup = app(\App\Services\Ledger\LedgerAccountSetupService::class);
        $head = $setup->ensureFestEventHead($event);
        $setup->updateHeadName($head, $data['name']);

        return back()->with('success', 'Ledger account name saved.');
    }

    public function updateItemFee(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        $item->update([
            'fee_amount' => isset($data['fee_amount']) && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : null,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.item_fee_updated',
            "Item fee updated: {$item->title}",
            ['item_id' => $item->id],
        );

        return back()->with('success', 'Item fee updated.');
    }

    public function storeVenue(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);

        FestVenue::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'event_id'  => $event->id,
        ]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.venue_created',
            "Venue added: {$data['name']}",
        );

        return back()->with('success', 'Venue added.');
    }

    public function destroyVenue(string $tenantId, FestEvent $event, FestVenue $venue)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($venue->event_id !== $event->id, 404);
        $name = $venue->name;
        $venue->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.venue_deleted',
            "Venue removed: {$name}",
        );

        return back()->with('success', 'Venue removed.');
    }

    public function storeStage(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'venue_id' => 'nullable|exists:fest_venues,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (! empty($data['venue_id'])) {
            abort_unless(
                FestVenue::where('event_id', $event->id)->where('id', $data['venue_id'])->exists(),
                422,
                'Venue does not belong to this event.'
            );
        }

        FestStage::create([
            'event_id'   => $event->id,
            'venue_id'   => $data['venue_id'] ?? null,
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? ((FestStage::where('event_id', $event->id)->max('sort_order') ?? 0) + 1),
            'is_active'  => true,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.stage_created',
            "Stage added: {$data['name']}",
        );

        return back()->with('success', 'Stage added.');
    }

    public function destroyStage(string $tenantId, FestEvent $event, FestStage $stage)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($stage->event_id !== $event->id, 404);
        $name = $stage->name;
        $stage->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.stage_deleted',
            "Stage removed: {$name}",
        );

        return back()->with('success', 'Stage removed.');
    }

    public function storeComboRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'school_id'          => 'nullable|string',
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
            'max_arts_events'    => 'nullable|integer|min:0',
            'max_sports_events'  => 'nullable|integer|min:0',
            'max_common_events'  => 'nullable|integer|min:0',
            'max_on_stage'       => 'nullable|integer|min:0',
            'max_off_stage'      => 'nullable|integer|min:0',
            'max_group'          => 'nullable|integer|min:0',
        ]);

        FestCombinationRule::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('combo'),
            'fest.settings.combo_rule_created',
            'Combination rule saved',
        );

        return back()->with('success', 'Combination rule saved.');
    }

    public function destroyComboRule(string $tenantId, FestEvent $event, FestCombinationRule $comboRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($comboRule->event_id !== $event->id, 404);
        $comboRule->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('combo'),
            'fest.settings.combo_rule_deleted',
            'Combination rule removed',
        );

        return back()->with('success', 'Combination rule removed.');
    }

    public function storeGradeConfig(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'   => 'nullable|exists:fest_event_items,id',
            'grade'     => 'required|in:A_plus,A,B,C',
            'min_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
        ]);

        FestGradeConfig::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_created',
            "Grade band saved: {$data['grade']}",
        );

        return back()->with('success', 'Grade band saved.');
    }

    public function destroyGradeConfig(string $tenantId, FestEvent $event, FestGradeConfig $gradeConfig)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($gradeConfig->event_id !== $event->id, 404);
        $gradeConfig->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_deleted',
            'Grade band removed',
        );

        return back()->with('success', 'Grade band removed.');
    }

    public function storePointRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'grade'    => 'nullable|in:A_plus,A,B,C',
            'position' => 'nullable|integer|min:1|max:10',
            'points'   => 'required|integer|min:0',
            'is_group' => 'nullable|boolean',
        ]);

        FestPointRule::create(array_merge($data, [
            'event_id'  => $event->id,
            'is_group'  => $data['is_group'] ?? false,
        ]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_created',
            'Point rule saved',
            ['points' => $data['points']],
        );

        return back()->with('success', 'Point rule saved.');
    }

    public function destroyPointRule(string $tenantId, FestEvent $event, FestPointRule $pointRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($pointRule->event_id !== $event->id, 404);
        $pointRule->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_deleted',
            'Point rule removed',
        );

        return back()->with('success', 'Point rule removed.');
    }

    public function updateRankPoints(Request $request, string $tenantId, FestEvent $event, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 422, 'Rank points apply to sports events only.');

        $data = $request->validate([
            'ranks'             => 'required|array|min:1',
            'ranks.*.rank'      => 'required|integer|min:1|max:255',
            'ranks.*.points'    => 'required|integer|min:0',
            'ranks.*.is_group'  => 'nullable|boolean',
            'is_group'          => 'nullable|boolean',
        ]);

        $isGroup = (bool) ($data['is_group'] ?? false);
        $count = $rankPoints->replaceForEvent($event, $data['ranks'], $isGroup);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_updated',
            "Rank points master saved ({$count} rank(s))",
            ['count' => $count, 'is_group' => $isGroup],
        );

        return back()->with('success', "Rank points saved ({$count} rank(s)).");
    }

    public function seedRankPoints(string $tenantId, FestEvent $event, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 422, 'Rank points apply to sports events only.');

        $count = $rankPoints->seedAthleticsStandard($event);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_seeded',
            'Athletics standard rank points loaded',
            ['count' => $count],
        );

        return back()->with('success', "Loaded athletics standard ({$count} ranks).");
    }

    public function storeVolunteer(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'duty'  => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        FestVolunteer::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('volunteers'),
            'fest.settings.volunteer_created',
            "Volunteer added: {$data['name']}",
        );

        return back()->with('success', 'Volunteer added.');
    }

    public function destroyVolunteer(string $tenantId, FestEvent $event, FestVolunteer $volunteer)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($volunteer->event_id !== $event->id, 404);
        $name = $volunteer->name;
        $volunteer->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('volunteers'),
            'fest.settings.volunteer_deleted',
            "Volunteer removed: {$name}",
        );

        return back()->with('success', 'Volunteer removed.');
    }

    public function cloneEvent(Request $request, string $tenantId, FestEvent $event, FestCloneService $cloneService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['title' => 'required|string|max:255']);

        $clone = $cloneService->cloneEvent($event, $data['title']);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('clone'),
            'fest.settings.event_cloned',
            "Event cloned as \"{$clone->title}\"",
            ['clone_event_id' => $clone->id],
        );

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$clone->id}/settings")
            ->with('success', "Event cloned as \"{$clone->title}\".");
    }

    public function updateRegistrationSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'require_event_registration' => 'nullable|boolean',
            'event_reg_start' => 'nullable|date',
            'event_reg_end' => 'nullable|date',
            'allow_student_self_register' => 'nullable|boolean',
        ]);

        $event->update([
            'require_event_registration' => (bool) ($data['require_event_registration'] ?? false),
            'event_reg_start' => $data['event_reg_start'] ?? null,
            'event_reg_end' => $data['event_reg_end'] ?? null,
            'allow_student_self_register' => (bool) ($data['allow_student_self_register'] ?? false),
        ]);

        return back()->with('success', 'Registration settings saved.');
    }

    public function updateNumberingSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'event_reg_start' => 'nullable|integer|min:1',
            'event_reg_prefix' => 'nullable|string|max:20',
            'chest_no_start' => 'nullable|integer|min:1',
            'chest_no_prefix' => 'nullable|string|max:20',
            'auto_assign_on_approve' => 'nullable|boolean',
            'auto_assign_chest_on_create' => 'nullable|boolean',
        ]);

        $event->update([
            'numbering_settings' => array_merge(
                app(\App\Services\Events\FestNumberingService::class)->settings($event),
                array_filter($data, fn ($v) => $v !== null)
            ),
        ]);

        return back()->with('success', 'Numbering settings saved.');
    }

    public function updateItemNumbering(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:fest_event_items,id',
            'items.*.chest_no_start' => 'nullable|integer|min:1',
            'items.*.item_reg_id_start' => 'nullable|integer|min:1',
        ]);

        $itemIds = FestEventItem::where('event_id', $event->id)->pluck('id')->all();

        foreach ($data['items'] as $row) {
            if (! in_array((int) $row['id'], $itemIds, true)) {
                continue;
            }
            FestEventItem::where('id', $row['id'])->update([
                'chest_no_start'      => $row['chest_no_start'] ?? null,
                'item_reg_id_start'   => $row['item_reg_id_start'] ?? null,
            ]);
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('numbering'),
            'fest.settings.item_numbering_updated',
            'Per-item chest and registration starts updated',
            ['count' => count($data['items'])],
        );

        return back()->with('success', 'Per-item numbering saved.');
    }

    public function updateItemWindows(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'reg_start' => 'nullable|date',
            'reg_end' => 'nullable|date',
            'competition_start' => 'nullable|date',
            'competition_end' => 'nullable|date|after_or_equal:competition_start',
            'competition_time' => 'nullable|date_format:H:i',
            'item_reg_id_start' => 'nullable|integer|min:1',
            'chest_no_start' => 'nullable|integer|min:1',
            'head_id' => 'nullable|exists:fest_item_heads,id',
            'results_published_at' => 'nullable|date',
            'is_enabled' => 'nullable|boolean',
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('fee_amount', $data)) {
            $data['fee_amount'] = isset($data['fee_amount']) && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : null;
        }

        $item->update($data);

        return back()->with('success', 'Item registration window saved.');
    }

    public function publishItemResults(string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $item->update(['results_published_at' => now()]);

        return back()->with('success', 'Item results marked published.');
    }

    public function backfillLevelRegistrations(string $tenantId, FestEvent $event, FestLevelRegistrationService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $count = $service->backfillEvent($event);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.settings.level_registrations_backfilled',
            "Backfilled {$count} level registration number(s)",
            ['count' => $count],
        );

        return back()->with('success', "Backfilled {$count} level registration number(s).");
    }
}
